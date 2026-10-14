<?php

/**
 * @file
 * Post update functions for the servicenow module.
 */

/**
 * Seeds a random lookup_last stamp for users that have a sys_id.
 */
function servicenow_post_update_seed_lookup_last(&$sandbox) {
  $database = \Drupal::database();
  $now = \Drupal::time()->getRequestTime();
  if (!isset($sandbox['max'])) {
    $sandbox['max'] = (int) $database->query('SELECT COUNT(DISTINCT entity_id) FROM {user__field_service_meow_sys_id}')->fetchField();
    $sandbox['progress'] = 0;
    $sandbox['last_uid'] = 0;
  }

  $uids = $database->select('user__field_service_meow_sys_id', 'f')
    ->fields('f', ['entity_id'])
    ->condition('f.entity_id', $sandbox['last_uid'], '>')
    ->distinct()
    ->orderBy('f.entity_id', 'ASC')
    ->range(0, 1000)
    ->execute()
    ->fetchCol();

  if ($uids) {
    $database->delete('users_data')
      ->condition('module', 'servicenow')
      ->condition('name', 'lookup_last')
      ->condition('uid', $uids, 'IN')
      ->execute();
    $insert = $database->insert('users_data')
      ->fields(['uid', 'module', 'name', 'value', 'serialized']);
    foreach ($uids as $uid) {
      $insert->values([
        'uid' => $uid,
        'module' => 'servicenow',
        'name' => 'lookup_last',
        'value' => $now - random_int(0, 120 * 86400),
        'serialized' => 0,
      ]);
    }
    $insert->execute();
    $sandbox['last_uid'] = (int) end($uids);
    $sandbox['progress'] += count($uids);
  }

  $sandbox['#finished'] = $uids ? ($sandbox['progress'] >= $sandbox['max'] ? 1 : $sandbox['progress'] / $sandbox['max']) : 1;
  if ($sandbox['#finished'] >= 1) {
    return "Seeded servicenow lookup_last for {$sandbox['progress']} users.";
  }
}
