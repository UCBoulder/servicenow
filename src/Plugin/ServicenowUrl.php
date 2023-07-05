<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provide proper url depending on environment.
 */
class ServicenowUrl {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $url;

  /**
   * Function to determine which Servicenow URL to use.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Return proper servicenow url.
   */
  public function getUrl($forcedev = 'false') {
    $config = $this->configFactory->get('servicenow.settings');
    $servicenow_api_prod = $config->get('servicenow_api_prod');
    if (!$servicenow_api_prod || $forcedev == 'true') {
      $request = 'https://coloradodev.service-now.com';
    }
    else {
      // Service meow LIVE.
      $request = 'https://colorado.service-now.com';
    }
    $this->url = $request;
    return $this->url;
  }

}
