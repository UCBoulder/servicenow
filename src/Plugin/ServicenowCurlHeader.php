<?php

namespace Drupal\servicenow\Plugin;

/**
 * Servicenow curl header.
 */
class ServicenowCurlHeader {
  /**
   * Headers.
   *
   * @var string
   */
  private $headers;

  /**
   * Function to sort the curl headers.
   */
  public function __construct($response) {
    $headers = [];

    $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

    foreach (explode("\r\n", $header_text) as $i => $line) {
      if ($i === 0) {
        $headers['http_code'] = $line;
      }
      else {
        list ($key, $value) = explode(': ', $line);

        $headers[$key] = $value;
      }
    }

    $this->headers = $headers;
  }

  /**
   * Return headers.
   */
  public function getHeaders() {
    return $this->headers;
  }

}
