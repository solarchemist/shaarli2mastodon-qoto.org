<?php
require_once 'HttpRequest.php';
require_once 'Toot.php';

class MastodonClient_qotoorg {

  /**
   * Mastodon Instance Name, like 'mastodon.social'
   * @var string
   */
  protected $domain;

  /**
   * HttpRequest_qotoorg Instance
   * @var \HttpRequest_qotoorg
   */
  protected $http;

  /**
   * Defaults headers for HttpRequest_qotoorg
   * @var array
   */
  protected $headers = [
    'Content-Type' => 'application/json; charset=utf-8',
    'Accept'       => '*/*'
  ];

  /**
   * Credentials to use Mastodon API
   * @var array
   */
  protected $appCredentials = [];

  /**
   * Setting Domain, like 'mastodon.social'
   * @param string $domain
   */
  public function __construct($domain, $token) {
    $this->domain = $domain;

    $this->http = new HttpRequest_qotoorg($this->domain);

    $this->appCredentials['bearer'] = $token;
    $this->headers['Authorization'] = $token;
  }

  /**
   * Post a new status
   *
   * Post a new status in Mastodon instance
   *
   * Return entire status as an array
   *
   * @param string $content Toot_qotoorg content
   * @param string $visibility Toot_qotoorg visibility (optionnal)
   * Values are :
   * - public
   * - unlisted
   * - private
   * - direct
   * @param array $medias Medias IDs
   * @return array
   */
  public function postStatus (Toot_qotoorg $toot) {
    $body = [
      'visibility' => 'public'
    ];

    if ($toot->hasContentWarning()) {
      $body = array_merge($body, [
        'status' => $toot->getContentWarningText(),
        'spoiler_text' => $toot->getMainText()
      ]);
    } else {
      $body['status'] = $toot->getText();
    }

    return $this->http->post(
      $this->http->apiURL . 'statuses',
      $this->headers,
      $body
    );
  }
}
