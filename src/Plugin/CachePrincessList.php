<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Utility\Xss;

/**
 * Cache princess list.
 */
class CachePrincessList {
  /**
   * Princess list cached data.
   *
   * @var array
   */
  private $cachedData;

  /**
   * Pull Princess List (DDS) from Servicenow and cache it.
   */
  public function __construct() {
    $api_call = new ServicenowApiCall();
    $dds_service_member_query = 'u_dds_service_request_group_member';
    // $dds_service_member_query .= '?sysparm_limit=1';
    $dds_service_members = $api_call->apiCallMeMaybe(0, 0, $dds_service_member_query, FALSE);
    $dds_service_group_query = 'u_dds_service_request_group';
    // $dds_service_group_query .= '?sysparm_limit=80';
    $dds_service_group = $api_call->apiCallMeMaybe(0, 0, $dds_service_group_query);
    $service_members = [];
    foreach ($dds_service_members->result as $service_member) {
      if (($service_member->u_active == 'true') && isset($service_member->u_primary_dds_group->value) && isset($service_member->u_assignment_group->value)) {
        $user_key = Xss::filter($service_member->u_user->value);
        $service_members[$user_key]['sys_id'] = Xss::filter($user_key);
        $service_members[$user_key]['user_name'] = Xss::filter($service_member->u_full_name);
        $service_members[$user_key]['email'] = Xss::filter($service_member->u_user_email);
        $service_members[$user_key]['dds_group'] = Xss::filter($service_member->u_primary_dds_group->value);
        $service_members[$user_key]['assignment_group'] = Xss::filter($service_member->u_assignment_group->value);
        $service_members[$user_key]['request_group'][] = Xss::filter($service_member->u_service_request_group->value);
        if ($service_member->u_service_request_group->value == 'ad78a2cd1ba48c10566a43b3cd4bcb33') {
          $sc_user = user_load_by_name(Xss::filter($service_member->u_user_name));
          if (!empty($sc_user)) {
            $sc_user->set('field_dds', 1);
            $sc_user->save();
          }
        }
      }
    }
    // Populate the groups.
    $dept_list = [];
    foreach ($dds_service_group->result as $dept) {
      if ($dept->u_active == 'true') {
        $dept_name = Xss::filter($dept->u_name);
        $dept_code = Xss::filter($dept->sys_id);
        $dept_list[$dept_code] = $dept_name;
      }
    }
    asort($dept_list);
    $princess_list = [
      'departments' => $dept_list,
      'users' => $service_members,
    ];
    // Stores in cache table and expires after 365 days. Cron will remove and
    // replace this daily.
    $expire = \Drupal::time()->getRequestTime() + (3600 * 24 * 365);
    \Drupal::cache()->set('princess_list', $princess_list, $expire);
    $this->cachedData = $princess_list;
  }

  /**
   * Return cached data.
   */
  public function getList() {
    return $this->cachedData;
  }

}
