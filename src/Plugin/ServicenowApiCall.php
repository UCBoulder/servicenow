<?php

namespace Drupal\servicenow\Plugin;

use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\servicenow\Plugin\ServicenowUrl;
use Drupal\servicenow\Plugin\ServicenowKey;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * Function to sort the curl headers.
   */
  public function __construct(
    RequestStack $request_stack,
    ServicenowUrl $servicenow_url,
    ServicenowKey $servicenow_key,
    LoggerChannelFactoryInterface $channelFactory,
    TeamsAlert $teams_alert
  ) {
    $this->logger = $channelFactory->get('servicenow');
    $this->requestStack = $request_stack;
    $this->meowUrl = $servicenow_url;
    $this->meowKey = $servicenow_key;
    $this->teamsAlert = $teams_alert;

    $forcedev = !empty($this->requestStack->getCurrentRequest()->get('forcedev')) ?
                Xss::filter($this->requestStack->getCurrentRequest()->get('forcedev')) :
                0;

    $this->meowUrl = $this->meowUrl->getUrl($forcedev);
    $this->meowKey = $this->meowKey->getKey();
  }

  /**
   * Function to make servicenow api call.
   */
  public function apiCallMeMaybe($table, $query, $string = NULL, $close = TRUE) {
    $meow_url = $this->meowUrl;
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    // Run the whole process.
    $result = curl_exec($ch);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    // Run the whole process.
    $header = curl_exec($ch);
    if ($close) {
      // Close cURL handler.
      curl_close($ch);
    }
    $construct_headers = new ServicenowCurlHeader($header);
    $headers = $construct_headers->getHeaders();
    $result = json_decode($result);
    $result->headers = $headers;
    if ((!isset($result->status)) && (!empty($result))) {
      $result->status = 'success';
    }
    else {
      $result->status = 'failure';
    }
    if (($result->status == 'failure') || (empty($result))) {
      $this->logger->notice('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $request);
      $this->teamsAlert->sendMessage('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $request);
      return NULL;
    }
    else {
      return $result;
    }
  }

}
