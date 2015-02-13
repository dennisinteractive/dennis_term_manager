<?php

/**
 * @file
 * Action to merge taxonomy terms
 */

/**
 * Helper to merge taxonomy terms.
 * 
 * @param array $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_merge($data) {
  // Get node data.
  if (isset($data['nid'])) {
    $node = node_load($data['nid']);
  }
  else {
    // Send to error report.
    $error_message = t('Node ID is not presented in current queue item which belongs to row @row', array(
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
  }

  // Set node change status.
  $node_changed = FALSE;
  // Get node type info.
  if ($entity_info = field_info_instances('node', $node->type)) {
    foreach ($entity_info as $info) {
      // Check each node field for term reference.
      if ($info['display']['default']['type'] == 'taxonomy_term_reference_link') {
        foreach ($node->{$info['field_name']} as $tids) {
          // Loop each item where multiple values exist.
          foreach ($tids as $key => $value) {
            if ($value['tid'] == $data['tid']) {
              // Replace target tid by source tid.
              $node->{$info['field_name']}[LANGUAGE_NONE][$key]['tid'] = $data['target_tid'];
              $node_changed = TRUE;
            }
          }
        }
      }
    }
    // If any tid has changed, then save the node.
    if ($node_changed) {
      $node->path['pathauto'] = FALSE;
      node_save($node);
    }
  }
}
