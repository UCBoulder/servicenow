<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Utility\Xss;

/**
 * Update users profile info from the SN api.
 */
class ServicenowUserLookup {

  /**
   * Call servicenow api.
   *
   * @var \Drupal\servicenow\Plugin\ServicenowApiCall
   */
  protected $apiCall;

  /**
   * Setup constructor.
   */
  public function __construct(ServicenowApiCall $api_call) {
    $this->apiCall = $api_call;
  }

  /**
   * Update user account using servicenow api.
   */
  public function update($account) {
    $acct_name = $account->getAccountName();
    $name = [
      'user_name' => $acct_name,
    ];
    $api_call = $this->apiCall;
    $result = $api_call->apiCallMeMaybe('sys_user', $name);
    if (!empty($result->result)) {
      if (isset($result->result[0]->department->value)) {
        $query_dept = ['sys_id' => $result->result[0]->department->value];
        $dept = $api_call->apiCallMeMaybe('cmn_department', $query_dept);
        $deptartment = !empty($dept->result[0]->name) ? Xss::filter($dept->result[0]->name) : '';
        $account->set('field_user_department', $deptartment, TRUE);
        $account->set('field_service_meow_department_id', Xss::filter($result->result[0]->department->value), TRUE);
        if (!empty($result->result[0]->u_secondarydepartment->value)) {
          $query_dept2 = ['sys_id' => $result->result[0]->u_secondarydepartment->value];
          $dept2 = $api_call->apiCallMeMaybe('cmn_department', $query_dept2);
          $department2 = Xss::filter($dept2->result[0]->name);
          $account->set('field_user_department2', $department2, TRUE);
          $account->set('field_service_meow_department2id', Xss::filter($result->result[0]->department->value), TRUE);
        }
        else {
          $department2 = '';
        }
      }
      if (!empty($result->result[0]->u_boulderallaffiliations)) {
        $affiliation = array_map('trim', array_filter(explode(',', $result->result[0]->u_boulderallaffiliations)));
        $affiliation_check = [];
        foreach ($affiliation as $aff) {
          $affiliation_check[] = Xss::filter($aff);
        }
        $account->set('field_service_meow_affiliations', $affiliation_check);
      }
      else {
        $account->set('field_service_meow_affiliations', 'unaffiliated');
      }
      if (!empty($result->result[0]->name)) {
        $account->set('field_user_name', Xss::filter($result->result[0]->name));
      }
      if (!empty($result->result[0]->edu_status)) {
        $account->set('field_user_affiliation', Xss::filter($result->result[0]->edu_status));
      }
      if (!empty($result->result[0]->primaryphone)) {
        $account->set('field_user_phone', Xss::filter($result->result[0]->primaryphone));
      }
      if (!empty($result->result[0]->sys_id)) {
        $account->set('field_service_meow_sys_id', Xss::filter($result->result[0]->sys_id));
      }
      if (!empty($result->result[0]->u_dds_group->value)) {
        $account->set('field_service_meow_dds_pod_group', Xss::filter($result->result[0]->u_dds_group->value));
      }
      $account->save();
    }
    // Some unaffilated users have no email.
    // Fix this issue with service now api.
    if (empty($account->getEmail())) {
      $query = ['user_name' => $account->getAccountName()];
      $result = $api_call->apiCallMeMaybe('sys_user', $query);
      if ($result->result[0]->email != NULL) {
        $account->setEmail(Xss::filter($result->result[0]->email));
        $account->save();
      }
    }
  }

}
