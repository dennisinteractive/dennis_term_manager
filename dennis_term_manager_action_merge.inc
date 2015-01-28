<?php
/**
 * @file
 * Action to merge taxonomy terms
 */

function _dennis_term_manager_process_action_merge($data) {
  // Get node data.
  if(isset($data['nid'])) {
    $node = node_load($data['nid']);
  }
  else {
    // @todo: store all messages and send email at the end.
    drupal_set_message(t('Node ID is not presented in current queue item which belongs to row @row', array('@row' => $data['row'])));
  }

  // Set node change status.
  $node_changed = FALSE;
  // Get node type info.
  if ($entity_info = field_info_instances('node', $node->type)) {
    foreach ($entity_info as $info) {
      // Check each node field for term reference.
      if($info['display']['default']['type'] == 'taxonomy_term_reference_link') {
        foreach ($node->$info['field_name'] as $tids) {
          // Loop each item where multiple values exist.
          foreach ($tids as $key => $value) {
            if($value['tid'] == $data['target_tid']) {
              // Replace target tid by source tid.
              $node->{$info['field_name']}[LANGUAGE_NONE][$key]['tid'] = $data['source_tid'];
              $node_changed = TRUE;
            }
          }
        }
      }
    }
    // If any tid has changed, then save the node.
    if ($node_changed) {
      node_save($node);
    }
  }
}
