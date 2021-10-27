<?php

namespace Drupal\servicenow\Plugin;

/**
 * Config pull settings.
 */
class ServicenowFetchSettings {
  /**
   * Servicenow settings stored in variable.
   *
   * @var string
   */
  private $servicenowSettings;

  /**
   * Pull the drupal form list from cache or servicenow if not cached.
   */
  public function __construct() {
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('servicenow.settings');
    $this->servicenowSettings = $config;
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
