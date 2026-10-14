<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oit\Plugin\TeamsAlert;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Make servicenow api call.
 */
class ServicenowApiCall {

  /**
   * The Teams logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Servicenow url.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowUrl
   */
  private $meowUrl;

  /**
   * Servicenow api key.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowKey
   */
  private $meowKey;

  /**
   * Servicenow Teams Alert.
   *
   * @var \Drupal\oit\Plugin\TeamsAlert
   */
  private $teamsAlert;

  /**
   * The default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Constructs the API call service.
   */
  public function __construct(
    RequestStack $request_stack,
    ServicenowUrl $servicenow_url,
    ServicenowKey $servicenow_key,
    LoggerChannelFactoryInterface $channelFactory,
    TeamsAlert $teams_alert,
    CacheBackendInterface $cache,
    TimeInterface $time,
  ) {
    $this->logger = $channelFactory->get('servicenow');
    $this->requestStack = $request_stack;
    $this->meowUrl = $servicenow_url;
    $this->meowKey = $servicenow_key;
    $this->teamsAlert = $teams_alert;
    $this->cache = $cache;
    $this->time = $time;

    $forcedev = !empty($this->requestStack->getCurrentRequest()->get('forcedev')) ?
                Xss::filter($this->requestStack->getCurrentRequest()->get('forcedev')) :
                0;

    $this->meowUrl = $this->meowUrl->getUrl($forcedev);
    $this->meowKey = $this->meowKey->getKey();
  }

  /**
   * Function to make servicenow api call.
   *
   * @param string $table
   *   The ServiceNow table.
   * @param array $query
   *   Query parameters.
   * @param string|null $string
   *   Optional raw path to use instead of the table and query.
   * @param bool $close
   *   Whether to close the curl handle.
   * @param int $timeout
   *   Total request timeout in seconds.
   *
   * @return object|null
   *   The decoded response with status 'success', or NULL on failure.
   */
  public function apiCallMeMaybe($table, $query, $string = NULL, $close = TRUE, $timeout = 30) {
    $meow_url = $this->meowUrl;
    $this->logger->notice('Using servicenow URL: ' . $meow_url);
    $meow_key = $this->meowKey;
    if ($string == NULL) {
      $query_string = http_build_query($query);
      $request = "$meow_url/api/now/table/$table?$query_string";
    }
    else {
      $request = "$meow_url/api/now/table/$string";
    }
    // Initialize curl handle.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept:application/json"]);
    curl_setopt($ch, CURLOPT_USERPWD, $meow_key);
    curl_setopt($ch, CURLOPT_URL, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    $response = curl_exec($ch);
    $result = $response === FALSE ? NULL : json_decode($response);
    if (!is_object($result) || isset($result->status)) {
      $this->logger->notice('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $request);
      $cid = 'servicenow:api_alert';
      if (!$this->cache->get($cid)) {
        $alert_url = strtok($request, '?');
        $this->teamsAlert->sendMessage('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $alert_url);
        $this->cache->set($cid, 1, $this->time->getRequestTime() + 900);
      }
      return NULL;
    }
    $result->status = 'success';
    return $result;
  }

}
