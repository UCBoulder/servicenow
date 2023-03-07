<?php

namespace Drupal\servicenow\Plugin;


/**
 * Update servicenow nodes to combine all fields into the body.
 */
class ServicenowNodeUpdate {
  /**
   * Servicenow URL.
   *
   * @var string
   */
  private $key;

  /**
   * Function to decrypt key and set.
   */
  public function __construct() {
    $query = \Drupal::entityQuery('node');
    $group = $query
      ->orConditionGroup()
      ->condition('field_service_alert_impact', '', '<>')
      ->condition('field_service_alert_scope', '', '<>')
      ->condition('field_service_alert_aff_serv', '', '<>')
      ->condition('field_service_alert_aff_bldg', '', '<>')
      ->condition('field_service_alert_main_impact', '', '<>')
      ->condition('field_service_alert_add_vendor', '', '<>')
      ->condition('field_service_alert_add_uis', '', '<>');
    $results = $query->condition($group)
                     ->condition('type', 'service_alert')
                     ->sort('nid', 'DESC')
                     ->range(0, 10)
                     ->execute();
    foreach ($results as $result) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($result);
      $body = $node->get('body')->value;
      $impact_field = $node->get('field_service_alert_impact')->value;
      $impact = $impact_field !== NULL ? "<h2>Impact</h2><p>$impact_field</p>" : '';
      $scope_field = $node->get('field_service_alert_scope')->value;
      $scope = $scope_field !== NULL ? "<h2>Scope</h2><p>$scope_field</p>" : '';
      $aff_services_field = $node->get('field_service_alert_aff_serv')->value;
      $aff_services = $aff_services_field !== NULL ? "<h2>Affected Services</h2><p>$aff_services_field</p>" : '';
      $aff_buildings_field = $node->get('field_service_alert_aff_bldg')->value;
      $aff_buildings = $aff_buildings_field !== NULL ? "<h2>Affected Buildings</h2><p>$aff_buildings_field</p>" : '';
      $main_impact_field = $node->get('field_service_alert_main_impact')->value;
      $main_impact = $main_impact_field !== NULL ? "<h2>Main Impact</h2><p>$main_impact_field</p>" : '';
      $vendor_field = $node->get('field_service_alert_add_vendor')->value;
      $vendor = $vendor !== NULL ? "<h2>Vendor</h2><p>$vendor_field</p>" : '';
      $updated_body = $body . $impact . $scope . $aff_services . $aff_buildings . $main_impact . $vendor_field;
      $nid = $node->id();
      kint($nid);
      kint($vendor);
      kint($updated_body);
    }
  }

  /**
   * Return key.
   */
  public function getKey() {
    return $this->key;
  }

}
