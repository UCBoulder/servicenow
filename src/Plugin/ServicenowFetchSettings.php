<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Config pull settings.
 */
class ServicenowFetchSettings {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Servicenow settings stored in variable.
   *
   * @var string
   */
  private $servicenowSettings;

  /**
   * Pull the drupal form list from cache or servicenow if not cached.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->servicenowSettings = $this->configFactory->getEditable('servicenow.settings');
  }

  /**
   * Servicenow set settings.
   */
  public function setpr($setting) {
    $this->servicenowSettings->set('princess_rebuild', $setting);
    $this->servicenowSettings->save(TRUE);
  }

  /**
   * SErvicenow get settings.
   */
  public function getpr() {
    return $this->servicenowSettings->get('princess_rebuild');
  }

  /**
   * Servicenow set settings.
   */
  public function set($setting) {
    $this->servicenowSettings->set('drupal_form_list', $setting);
    $this->servicenowSettings->save(TRUE);
  }

  /**
   * SErvicenow get settings.
   */
  public function get() {
    return $this->servicenowSettings->get('drupal_form_list');
  }

}
