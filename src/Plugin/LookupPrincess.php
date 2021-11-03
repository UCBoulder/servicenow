<?php

namespace Drupal\servicenow\Plugin;

/**
 * Lookup specific princess.
 */
class LookupPrincess {
  /**
   * Princess data.
   *
   * @var array
   */
  private $princess;

  /**
   * Look up selected user by sysID.
   */
  public function __construct($sys_id) {
    $princess = new PrincessList();
    $princess_list = $princess->getData();
    $princess_list = $princess_list['users'];
    $this->princess = $princess_list[$sys_id];
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
