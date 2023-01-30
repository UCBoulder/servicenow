<?php

namespace Drupal\servicenow\Plugin;

use Drupal\encrypt\Entity\EncryptionProfile;

/**
 * Provide proper url depending on environment.
 */
class ServicenowKey {
  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $key;

  /**
   * Function to decrypt key and set.
   */
  public function __construct() {
    $key_encrypted = trim(\Drupal::service('key.repository')->getKey('servicenow_key')->getKeyValue());
    $encryption_profile = EncryptionProfile::load('key_encryption');
    $this->key = \Drupal::service('encryption')->decrypt($key_encrypted, $encryption_profile);
  }

  /**
   * Return key.
   */
  public function getKey() {
    return $this->key;
  }

}
