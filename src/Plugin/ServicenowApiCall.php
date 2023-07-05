<?php

namespace Drupal\servicenow\Plugin;

use Drupal\oit\Plugin\TeamsAlert;
use Drupal\Component\Utility\Xss;

/**
 * Make servicenow api call.
 */
class ServicenowApiCall {
  /**
   * Return api results.
   *
   * @var array
   */
  private $apiResult;
  /**
   * Servicenow url.
   *
   * @var string
   */
  private $meowUrl;
  /**
   * Servicenow api key.
   *
   * @var string
   */
  private $meowKey;

  /**
   * Function to sort the curl headers.
   */
  public function __construct() {
    $forcedev = !empty(\Drupal::request()->query->get('forcedev')) ?
                Xss::filter(\Drupal::request()->query->get('forcedev')->get('forcedev')) :
                0;
    $this->meowUrl = \Drupal::service('servicenow.url')->getUrl($forcedev);
    $this->meowKey = \Drupal::service('servicenow.key')->getKey();
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
      \Drupal::logger('servicenow')->notice('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $request);
      $teams = new TeamsAlert();
      $teams->sendMessage('servicenow api not communicating correctly \_(ツ)_/¯ api call: ' . $request);
      return NULL;
    }
    else {
      return $result;
    }
  }

}
