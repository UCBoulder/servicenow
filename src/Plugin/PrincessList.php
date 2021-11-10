<?php

namespace Drupal\servicenow\Plugin;

use Drupal\Component\Utility\Xss;
use Drupal\oit\Plugin\TeamsAlert;

/**
 * Combined Princess functions into one class.
 */
class PrincessList {
  /**
   * Establish Database connection.
   *
   * @var object
   */
  private $princessDbConnection;

  /**
   * Store princess data.
   *
   * @var string
   */
  private $princessData;

  /**
   * Store princess data.
   *
   * @var string
   */
  private $princessReload;

  /**
   * Princess list settings.
   *
   * @var object
   */
  private $princessSettings;

  /**
   * Princess first key.
   *
   * @var string
   */
  private $princessFirstKey;

  /**
   * Princess last key.
   *
   * @var string
   */
  private $princessLastKey;

  /**
   * Princess data last row.
   *
   * @var string
   */
  private $plEnd;

  /**
   * Princess data second to last row.
   *
   * @var string
   */
  private $plPenultimate;

  /**
   * Princess set offset.
   *
   * @var int
   */
  private $plOffset;

  /**
   * Pull Princess List (DDS) from cache or servicenow if not cached.
   */
  public function __construct() {
    $this->princessDbConnection = \Drupal::database();
    $result = $this->princessDbConnection->select('princess_list', 'pl')
      ->fields('pl', ['id', 'data'])
      ->execute();
    $pl_data = $result->fetchAllKeyed();
    $this->princessFirstKey = count($pl_data) >= 3 ? array_key_first($pl_data) : 0;
    $this->princessLastKey = array_key_last($pl_data);
    $this->princessSettings = new ServicenowFetchSettings();
    $this->princessReload = $this->princessSettings->getpr();

    $offset = $this->princessDbConnection->select('princess_list', 'pl')
      ->condition('id', $this->princessLastKey)
      ->fields('pl', ['offset'])
      ->execute()->fetch();
    $this->plOffset = $offset->offset;
    // Use Last entry when already built.
    $this->plEnd = end($pl_data);
    // Use Second to last entry when rebuilding.
    $this->plPenultimate = prev($pl_data);

    $this->princessData = $this->princessReload ? $this->plPenultimate : $this->plEnd;
  }

  /**
   * Cron function to clean and insert into table.
   */
  public function cron() {
    // Tidy up and clean out entries above 2.
    if ($this->princessFirstKey) {
      $this->princessDbConnection->delete('princess_list')
        ->condition('id', $this->princessFirstKey)
        ->execute();
    }
    if ($this->princessReload) {
      $pl_data = json_decode($this->plEnd, TRUE);
      $api_call = new ServicenowApiCall();
      $dds_service_member_query = 'u_dds_service_request_group_member';
      $set_limit = 500;
      $query_limit = [
        'sysparm_limit' => $set_limit,
        'sysparm_offset' => $this->plOffset,
      ];
      $dds_service_members = $api_call->apiCallMeMaybe($dds_service_member_query, $query_limit, 0, FALSE);
      $dds_service_group_query = 'u_dds_service_request_group';
      $dds_service_group = $api_call->apiCallMeMaybe($dds_service_group_query, $query_limit, 0);
      if (!empty($dds_service_members->result)) {
        foreach ($dds_service_members->result as $service_member) {
          if (($service_member->u_active == 'true') && isset($service_member->u_primary_dds_group->value) && isset($service_member->u_assignment_group->value)) {
            $user_key = Xss::filter($service_member->u_user->value);
            $pl_data['users'][$user_key]['sys_id'] = Xss::filter($user_key);
            $pl_data['users'][$user_key]['user_name'] = Xss::filter($service_member->u_full_name);
            $pl_data['users'][$user_key]['email'] = Xss::filter($service_member->u_user_email);
            $pl_data['users'][$user_key]['dds_group'] = Xss::filter($service_member->u_primary_dds_group->value);
            $pl_data['users'][$user_key]['assignment_group'] = Xss::filter($service_member->u_assignment_group->value);
            $pl_data['users'][$user_key]['request_group'][] = Xss::filter($service_member->u_service_request_group->value);
            if ($service_member->u_service_request_group->value == 'ad78a2cd1ba48c10566a43b3cd4bcb33') {
              $sc_user = user_load_by_name(Xss::filter($service_member->u_user_name));
              if (!empty($sc_user)) {
                $sc_user->set('field_dds', 1);
                $sc_user->save();
              }
            }
          }
        }
      }
      // Populate the groups.
      if (!empty($dds_service_group->result)) {
        foreach ($dds_service_group->result as $dept) {
          if ($dept->u_active == 'true') {
            $dept_name = Xss::filter($dept->u_name);
            $dept_code = Xss::filter($dept->sys_id);
            $pl_data['departments'][$dept_code] = $dept_name;
          }
        }
        asort($pl_data['departments']);
      }
      if (empty($dds_service_members->result) && empty($dds_service_group->result)) {
        $this->princessSettings->setpr(0);
        $teams = new TeamsAlert();
        $teams->sendMessage("Princess Data loaded into id: $this->princessLastKey with offset: $this->plOffset");
        \Drupal::logger('servicenow')->notice("Princess Data loaded into id: $this->princessLastKey with offset: $this->plOffset");
      }
      else {
        $new_offset = $set_limit + $this->plOffset;
        $princess_list = json_encode($pl_data);
        $row = ['data' => $princess_list, 'offset' => $new_offset];
        $this->princessDbConnection->update('princess_list')
          ->condition('id', $this->princessLastKey)
          ->fields($row)
          ->execute();
      }
    }
  }

  /**
   * Get princess list.
   */
  public function getData() {
    $pl_data = json_decode($this->princessData, TRUE);
    return $pl_data;
  }

  /**
   * Reload princess list.
   */
  public function reload() {
    $this->princessSettings->setpr(1);
    $princess_list = [
      'departments' => [],
      'users' => [],
    ];
    $princess_list = json_encode($princess_list);
    $row = ['data' => $princess_list];
    $this->princessDbConnection->insert('princess_list')->fields($row)->execute();
    $teams = new TeamsAlert();
    $teams->sendMessage("Princess reload start");
    \Drupal::logger('servicenow')->notice("Princess reload start");
  }

}
