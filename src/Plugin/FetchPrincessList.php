<?php

namespace Drupal\servicenow\Plugin;

/**
 * Fetch princess list.
 */
class FetchPrincessList {
  /**
   * Store princess data.
   *
   * @var string
   */
  private $princessData;

  /**
   * Pull Princess List (DDS) from cache or servicenow if not cached.
   */
  public function __construct() {
    if ($cache = \Drupal::cache()->get('princess_list')) {
      $princess_list = $cache->data;
    }
    else {
      $princess = new CachePrincessList();
      $princess_list = $princess->getList();
    }
    $this->princessData = $princess_list;
  }

  /**
   * Get princess list.
   */
  public function getData() {
    return $this->princessData;
  }

}
