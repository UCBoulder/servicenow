<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Utility\Xss;

/**
 * Provide proper url depending on environment.
 */
class ServicenowUrl {
  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $url;

  /**
   * Function to determine which Servicenow URL to use.
   */
  public function __construct() {
    // Set by Pantheon servers and needs to be set by local server.
    $env = getenv('PANTHEON_ENVIRONMENT');
    $forcedev = Xss::filter(\Drupal::request()->query->get('forcedev'));
    if ($forcedev == "true") {
      $request = 'https://coloradodev.service-now.com';
    }
    elseif ($env == 'live' || $env == 'dev') {
      // Service meow LIVE.
      $request = 'https://colorado.service-now.com';
    }
    else {
      $request = 'https://coloradodev.service-now.com';
    }
    $this->url = $request;
  }

  /**
   * Return proper servicenow url.
   */
  public function getUrl() {
    return $this->url;
  }

}
