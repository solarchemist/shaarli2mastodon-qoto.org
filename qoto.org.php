<?php

/**
 * shaarli2mastodon
 *
 * Automatically publishes your new Shaarli links to your Mastodon timeline.
 * Get Shaarli at https://github.com/shaarli/shaarli
 *
 * Uses TootoPHP - https://framagit.org/MaxKoder/TootoPHP
 * Largely inspired by ArthurHoaro's shaarli2twitter - https://github.com/ArthurHoaro/shaarli2twitter
 *
 * See README.md for instructions.
 *
 * @author kalvn <kalvnthereal@gmail.com>
 */

use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

require_once 'src/Toot.php';
require_once 'src/MastodonClient.php';
require_once 'src/Utils.php';

/**
 * The default toot format if none is specified.
 */
const QOTOORG_DEFAULT_FORMAT = '#Shaarli: ${title} ${url} ${tags}';

const DIRECTORY_PATH = __DIR__;

/**
 * Init function: check settings, and set default format.
 *
 * @param ConfigManager $conf instance.
 *
 * @return array|void Error if config is not valid.
 */
function s2m_qotoorg_init ($conf) {
    $format = $conf->get('plugins.QOTOORG_TOOT_FORMAT');
    if (empty($format)) {
        $conf->set('plugins.QOTOORG_TOOT_FORMAT', QOTOORG_DEFAULT_FORMAT);
    }

    if (!Utils_qotoorg::isConfigValid($conf)) {
        return array('Please set up your Mastodon parameters in plugin administration page.');
    }
}

function hook_s2m_qotoorg_render_includes ($data) {
    if (in_array($data['_PAGE_'], [TemplatePage::EDIT_LINK, TemplatePage::EDIT_LINK_BATCH])) {
        $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/qoto.org/qoto.org.css';
    }

    return $data;
}

/**
 * Add the JS file: disable the toot button if the link is set to private.
 *
 * @param array         $data New link values.
 * @param ConfigManager $conf instance.
 *
 * @return array $data with the JS file.
 */
function hook_s2m_qotoorg_render_footer ($data, $conf) {
    if (in_array($data['_PAGE_'], [TemplatePage::EDIT_LINK, TemplatePage::EDIT_LINK_BATCH])) {
        $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/qoto.org/qoto.org.js';
    }

    return $data;
}

/**
 * Hook save link: will automatically publish a tweet when a new public link is shaared.
 *
 * @param array         $data New link values.
 * @param ConfigManager $conf instance.
 *
 * @return array $data not altered.
 */
function hook_s2m_qotoorg_save_link ($data, $conf) {
    // No toot without config, for private links, or on edit.
    if (!Utils_qotoorg::isConfigValid($conf)
        || $data['private']
        || !isset($_POST['toot'])
    ) {
        return $data;
    }

    // We make sure not to alter data
    $link = array_merge(array(), $data);
    $tagsSeparator = $conf->get('general.tags_separator', ' ');
    $maxLength = intval($conf->get('plugins.QOTOORG_TOOT_MAX_LENGTH'));

    $data['permalink'] = index_url($_SERVER) . 'shaare/' . $data['shorturl'];

    // If the link is a note, we use the permalink as the url.
    if(Utils_qotoorg::isLinkNote($data)){
        $data['url'] = $data['permalink'];
    }

    $format = isset($_POST['toot-format']) ? $_POST['toot-format'] : $conf->get('plugins.QOTOORG_TOOT_FORMAT', QOTOORG_DEFAULT_FORMAT);
    $toot = new Toot_qotoorg($data, $format, $tagsSeparator, $maxLength);
    $mastodonInstance = $conf->get('plugins.QOTOORG_INSTANCE', false);
    $appToken = $conf->get('plugins.QOTOORG_APPTOKEN', false);

    $mastodonClient = new MastodonClient_qotoorg($mastodonInstance, $appToken);
    $response = $mastodonClient->postStatus($toot);

    // If an error has occurred, not blocking: just log it.
    if (isset($response['error'])) {
        error_log('Mastodon API error: '. $response['error']);

        if (session_status() == PHP_SESSION_ACTIVE) {
          $_SESSION['errors'][] = 'Something went wrong when publishing the link on Mastodon. ' . $response['error'];
        }
    }

    return $link;
}

/**
 * Hook render_editlink: add a checkbox to toot the new link or not.
 *
 * @param array         $data New link values.
 * @param ConfigManager $conf instance.
 *
 * @return array $data with `edit_link_plugin` placeholder filled.
 */
function hook_s2m_qotoorg_render_editlink ($data, $conf) {
    if (!Utils_qotoorg::isConfigValid($conf)) {
        return $data;
    }

    $private = $conf->get('privacy.default_private_links', false);
    $checked = $data['link_is_new'] && !$private;

    $html = file_get_contents(DIRECTORY_PATH . '/edit_link.html');

    $html = str_replace([
      '##checked##',
      '##toot-format##',
      '##id##',
      '##max-length##',
      '##tags-separator##',
      '##is-note##',
    ], [
      $checked ? 'checked="checked"' : '',
      $conf->get('plugins.QOTOORG_TOOT_FORMAT', QOTOORG_DEFAULT_FORMAT),
      uniqid(),
      $conf->get('plugins.QOTOORG_TOOT_MAX_LENGTH'),
      $conf->get('general.tags_separator', ' '),
      Utils_qotoorg::isLinkNote($data['link']) ? 'true' : 'false',
    ], $html);

    $data['edit_link_plugin'][] = $html;

    return $data;
}
