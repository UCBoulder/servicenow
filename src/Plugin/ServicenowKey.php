<?php

namespace Drupal\servicenow\Plugin;

use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\key\KeyRepositoryInterface;

/**
 * Provide proper url depending on environment.
 */
class ServicenowKey {

  /**
   * Encryption Interface.
   *
   * @var \Drupal\encrypt\EncryptServiceInterface
   */
  protected $encryptService;

  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $key;

  /**
   * Key repository object.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $repository;

  /**
   * Function to decrypt key and set.
   */
  public function __construct(KeyRepositoryInterface $repository, EncryptServiceInterface $encryptService) {
    $this->encryptService = $encryptService;
    $this->repository = $repository;
    $key_encrypted = trim($this->repository->getKey('servicenow_key')->getKeyValue());
    $encryption_profile = EncryptionProfile::load('key_encryption');
    $this->key = $this->encryptService->decrypt($key_encrypted, $encryption_profile);
  }

  /**
   * Return key.
   */
  public function getKey() {
    return $this->key;
  }

}
