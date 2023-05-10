<?php

namespace Drupal\servicenow\Plugin;

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
  public function __construct($forcedev = 'false') {
    $config = \Drupal::config('servicenow.settings');
    $servicenow_api_prod = $config->get('servicenow_api_prod');
    if (!$servicenow_api_prod || $forcedev == 'true') {
      $request = 'https://coloradodev.service-now.com';
    } else {
      // Service meow LIVE.
      $request = 'https://colorado.service-now.com';
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
