<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Cache drupal list.
 */
class CacheDrupalFormList {

  /**
   * Princess list settings.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowFetchSettings
   */
  private $princessSettings;

  /**
   * The default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $defaultCache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Call servicenow api.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowApiCall
   */
  protected $apiCall;

  /**
   * Drupal list cached.
   *
   * @var array
   */
  private $cachedData;

  /**
   * Pull drupal form list from Servicenow and cache it.
   */
  public function __construct(
    ServicenowApiCall $api_call,
    TimeInterface $time,
    CacheBackendInterface $default_cache,
    ServicenowFetchSettings $fetch_settings,
  ) {
    $this->apiCall = $api_call;
    $this->time = $time;
    $this->defaultCache = $default_cache;
    $this->princessSettings = $fetch_settings;

    $drupal_form_query = 'u_drupal_form';
    // $drupal_form_query .= '?sysparm_limit=1';
    $drupal_forms = $this->apiCall->apiCallMeMaybe(0, 0, $drupal_form_query, FALSE);
    $drupal_list = [];
    foreach ($drupal_forms->result as $drupal_form) {
      if (preg_match("/^https?\:\/\/oit.colorado.edu/", $drupal_form->u_url_alias, $matches)) {
        $type = Xss::filter($drupal_form->u_table);
        $sys_id = Xss::filter($drupal_form->sys_id);
        $url = isset($drupal_form->u_url_alias) ? Xss::filter($drupal_form->u_url_alias) : 0;
        if ($url) {
          $pattern = '/\d.*$/';
          preg_match($pattern, $url, $matches);
          $array_id = $matches[0] . '_' . $type;
          $drupal_list[$array_id] = $sys_id;
        }
      }
    }

    // Stores in cache table and expires after 365 days.
    $expire = $this->time->getRequestTime() + (3600 * 24 * 365);
    $this->defaultCache->set('drupal_form_list', $drupal_list, $expire);
    $sn_settings = $this->princessSettings;
    $sn_settings->set(0);
    $this->cachedData = $drupal_list;
  }

  /**
   * Return cached drupal list.
   */
  public function getList() {
    return $this->cachedData;
  }

}
