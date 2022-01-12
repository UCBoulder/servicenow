<?php

namespace Drupal\servicenow\Plugin;

use Drupal\servicenow\Plugin\ServicenowApiCall;
use Drupal\Component\Utility\Xss;

/**
 * Update users profile info from the SN api.
 */
class ServicenowUserLookup {

  /**
   * Function to determine which Servicenow URL to use.
   */
  public function __construct($account) {
    $acct_name = $account->getAccountName();
    $name = [
      'user_name' => $acct_name,
    ];
    $api_call = new ServicenowApiCall();
    $result = $api_call->apiCallMeMaybe('sys_user', $name);
    if (!empty($result->result)) {
      if (isset($result->result[0]->department->value)) {
        $query_dept = ['sys_id' => $result->result[0]->department->value];
        $dept = $api_call->apiCallMeMaybe('cmn_department', $query_dept);
        $deptartment = !empty($dept->result[0]->name) ? Xss::filter($dept->result[0]->name) : '';
        $account->set('field_user_department', $deptartment , TRUE);
        $account->set('field_service_meow_department_id', Xss::filter($result->result[0]->department->value), TRUE);
        if (!empty($result->result[0]->u_secondarydepartment->value)) {
          $query_dept2 = ['sys_id' => $result->result[0]->u_secondarydepartment->value];
          $dept2 = $api_call->apiCallMeMaybe('cmn_department', $query_dept2);
          $department2 = Xss::filter($dept2->result[0]->name);
          $account->set('field_user_department2', $department2, TRUE);
          $account->set('field_service_meow_department2id', Xss::filter($result->result[0]->department->value), TRUE);
        } else {
          $department2 = '';
        }
        // Set oit_administration role if dept matches correctly.
        if ($deptartment == 'OIT-Administration' || $department2 == 'OIT-Administration') {
          $account->addRole('oit_administration');
        }
      }
      if (!empty($result->result[0]->u_boulderallaffiliations)) {
        $affiliation = array_map('trim', array_filter(explode(',', $result->result[0]->u_boulderallaffiliations)));
        $facstaff = [
          'Employee-Officer/Exempt Professional',
          'Staff-Officer/Exempt Professional',
          'Employee-Officer/Exempt Professional',
          'Employee-Research Faculty',
          'Faculty-Research Faculty',
          'Employee-Faculty',
          'Employee-Staff',
          'Employee-Student Employee',
          'Employee-Research Faculty',
          'Employee-Faculty',
          'Employee-Staff',
          'Employee-Student Employee',
          'Employee-Student Faculty',
          'Faculty-Student Faculty',
          'Employee-Student Faculty',
          'Staff-Staff',
        ];
        $student = [
          'Student-Continuing Ed Credit Student',
          'Student-Student',
          'Affiliate-Confirmed Student',
          'Affiliate-Confirmed Student',
        ];
        $myuserroles = $account->getRoles();
        $affiliation_check = [];
        foreach ($affiliation as $aff) {
          if ((array_search($aff, $facstaff)) && (!array_search('dl facstaff', $myuserroles))) {
            $account->addRole('dl_facstaff');
          }
          if ((array_search($aff, $student)) && (!array_search('dl student', $myuserroles))) {
            $account->addRole('dl_student');
          }
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
      if (!empty($result->result[0]->uniqueid_euuid_)) {
        $account->set('field_service_meow_uuid', Xss::filter($result->result[0]->uniqueid_euuid_));
      }
      if (!empty($result->result[0]->sys_id)) {
        $account->set('field_service_meow_sys_id', Xss::filter($result->result[0]->sys_id));
      }
      if (!empty($result->result[0]->u_dds_group->value)) {
        $account->set('field_service_meow_dds_pod_group', Xss::filter($result->result[0]->u_dds_group->value));
      }
      $account->save();
    }
    // Some unaffilated users have no email. Fix this issue with service now api.
    if (empty($account->getEmail())) {
      $query = ['user_name' => $account->getAccountName()];
      $result = $api_call->apiCallMeMaybe('sys_user', $query);
      $account->setEmail(Xss::filter($result->result[0]->email));
      $account->save();
    }
  }

}
