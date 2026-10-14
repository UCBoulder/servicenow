<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Update users profile info from the SN api.
 */
class ServicenowUserLookup {

  /**
   * Refresh interval for users that have a sys_id.
   */
  const LOOKUP_INTERVAL_WITH_SYS_ID = 120 * 86400;

  /**
   * Refresh interval for users without a sys_id.
   */
  const LOOKUP_INTERVAL_WITHOUT_SYS_ID = 30 * 86400;

  /**
   * Total timeout in seconds for each lookup request.
   */
  const LOOKUP_TIMEOUT = 5;

  /**
   * Call servicenow api.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowApiCall
   */
  protected $apiCall;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The servicenow logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Setup constructor.
   */
  public function __construct(ServicenowApiCall $api_call, UserDataInterface $user_data, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory) {
    $this->apiCall = $api_call;
    $this->userData = $user_data;
    $this->time = $time;
    $this->logger = $logger_factory->get('servicenow');
  }

  /**
   * Determines whether a lookup is due for the account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if a lookup should run.
   */
  public function isDue(UserInterface $account): bool {
    $stamp = $this->userData->get('servicenow', $account->id(), 'lookup_last');
    if (empty($stamp)) {
      return TRUE;
    }
    $interval = $account->get('field_service_meow_sys_id')->isEmpty()
      ? self::LOOKUP_INTERVAL_WITHOUT_SYS_ID
      : self::LOOKUP_INTERVAL_WITH_SYS_ID;
    return ($this->time->getRequestTime() - (int) $stamp) > $interval;
  }

  /**
   * Update user account using servicenow api.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function update(UserInterface $account): void {
    $start = microtime(TRUE);
    $uid = $account->id();
    $result = $this->apiCall->apiCallMeMaybe('sys_user', ['user_name' => $account->getAccountName()], NULL, TRUE, self::LOOKUP_TIMEOUT);
    if ($result === NULL) {
      $this->logOutcome($uid, $start, 'fail');
      return;
    }
    if (empty($result->result) || !is_array($result->result)) {
      $this->stamp($uid);
      $this->logOutcome($uid, $start, 'none');
      return;
    }

    $r = $result->result[0];
    $dept_id = $this->value($r->department ?? NULL);
    if ($dept_id === NULL) {
      $account->set('field_user_department', NULL);
    }
    else {
      $dept = $this->apiCall->apiCallMeMaybe('cmn_department', ['sys_id' => $dept_id], NULL, TRUE, self::LOOKUP_TIMEOUT);
      if ($dept !== NULL) {
        $name = $dept->result[0]->name ?? '';
        $account->set('field_user_department', $this->clean($name));
      }
    }
    $account->set('field_user_name', $this->clean($r->name ?? NULL));
    $account->set('field_user_affiliation', $this->clean($r->edu_status ?? NULL));
    $account->set('field_user_phone', $this->clean($r->primaryphone ?? NULL));
    $sys_id = $this->clean($r->sys_id ?? NULL);
    if ($sys_id !== NULL) {
      $account->set('field_service_meow_sys_id', $sys_id);
    }
    $email = $this->clean($r->email ?? NULL);
    if (empty($account->getEmail()) && $email !== NULL) {
      $account->setEmail($email);
    }
    $account->save();
    $this->stamp($uid);
    $this->logOutcome($uid, $start, 'record');
  }

  /**
   * Extracts the value from a reference field or plain string.
   *
   * @param mixed $field
   *   An object with a value property, a string, or empty.
   *
   * @return string|null
   *   The value, or NULL when empty.
   */
  protected function value($field): ?string {
    if (is_object($field)) {
      $field = $field->value ?? NULL;
    }
    return is_string($field) && $field !== '' ? $field : NULL;
  }

  /**
   * Filters a scalar value, returning NULL when empty.
   *
   * @param mixed $value
   *   The raw value.
   *
   * @return string|null
   *   The filtered value or NULL.
   */
  protected function clean($value): ?string {
    if (!is_scalar($value) || $value === '') {
      return NULL;
    }
    $filtered = Xss::filter((string) $value);
    return $filtered === '' ? NULL : $filtered;
  }

  /**
   * Records the lookup time.
   *
   * @param int|string $uid
   *   The user ID.
   */
  protected function stamp($uid): void {
    $this->userData->set('servicenow', $uid, 'lookup_last', $this->time->getRequestTime());
  }

  /**
   * Logs the lookup duration and outcome.
   *
   * @param int|string $uid
   *   The user ID.
   * @param float $start
   *   Start time from microtime(TRUE).
   * @param string $outcome
   *   One of fail, none, record.
   */
  protected function logOutcome($uid, float $start, string $outcome): void {
    $this->logger->info('User lookup for uid @uid took @ms ms, outcome=@outcome', [
      '@uid' => $uid,
      '@ms' => (int) round((microtime(TRUE) - $start) * 1000),
      '@outcome' => $outcome,
    ]);
  }

}
