<?php

namespace Drupal\servicenow\Plugin;

/**
 * Lookup specific princess.
 */
class LookupPrincess {

  /**
   * Princess list data.
   *
   * @var \Drupal\servicenow\Plugin\PrincessList
   */
  private $princessList;

  /**
   * Princess data.
   *
   * @var array
   */
  private $princess;

  /**
   * Constructor.
   */
  public function __construct(PrincessList $princess_list) {
    $this->princessList = $princess_list;
  }

  /**
   * Look up selected user by sysID.
   */
  public function userLookup($sys_id) {
    $princess_list = $this->princessList->getData();
    $princess_users = $princess_list['users'];
    $this->princess = $princess_users[$sys_id];
  }

  /**
   * Get princess data.
   */
  public function getData() {
    return $this->princess;
  }

  /**
   * Get princess name.
   */
  public function getName() {
    return $this->princess['user_name'];
  }

  /**
   * Get princess email.
   */
  public function getEmail() {
    return $this->princess['email'];
  }

  /**
   * Get princess dds group.
   */
  public function getDdsGroup() {
    return $this->princess['dds_group'];
  }

  /**
   * Get princess assignment group.
   */
  public function getAssignmentGroup() {
    return $this->princess['assignment_group'];
  }

  /**
   * Get princess request group.
   */
  public function getRequestGroup() {
    return $this->princess['request_group'];
  }

}
