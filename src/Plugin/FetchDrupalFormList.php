<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\servicenow\Plugin\CacheDrupalFormList;

/**
 * Fetch drupal form list.
 */
class FetchDrupalFormList {

  /**
   * The servicenow formlist cache.
   *
   * @var \Drupal\servicenow\Plugin\CacheDrupalFormList
   */
  protected $formlistCache;

  /**
   * The default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $defaultCache;

  /**
   * Drupal form list stored.
   *
   * @var array
   */
  private $drupalFormData;

  /**
   * Pull the drupal form list from cache or servicenow if not cached.
   */
  public function __construct(
    CacheBackendInterface $default_cache,
    CacheDrupalFormList $formlist_cache
  ) {
    $this->defaultCache = $default_cache;
    $this->formlistCache = $formlist_cache;

    if ($cache = $this->defaultCache->get('drupal_form_list')) {
      $drupal_form_list = $cache->data;
    }
    else {
      $drupal_form = $this->formlistCache;
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
