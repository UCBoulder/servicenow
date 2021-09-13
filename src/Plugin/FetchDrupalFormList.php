<?php

namespace Drupal\servicenow\Plugin;

/**
 * Fetch drupal form list.
 */
class FetchDrupalFormList {
  /**
   * Drupal form list stored.
   *
   * @var array
   */
  private $drupalFormData;

  /**
   * Pull the drupal form list from cache or servicenow if not cached.
   */
  public function __construct() {
    if ($cache = \Drupal::cache()->get('drupal_form_list')) {
      $drupal_form_list = $cache->data;
    }
    else {
      $drupal_form = new CacheDrupalFormList();
      $drupal_form_list = $drupal_form->getList();
    }
    $this->drupalFormData = $drupal_form_list;
  }

  /**
   * Return drupal form list.
   */
  public function getList() {
    return $this->drupalFormData;
  }

}
