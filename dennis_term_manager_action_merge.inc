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

  if (!isset($data['nid'])) {
    // Send to error report.
    $error_message = t('Node ID is not presented in current queue item which belongs to row @row', array(
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }

  // Get list of allowed fields.
  $allowed_target_fields = _dennis_term_manager_get_vocabulary_allowed_fields($data['target_vid']);
  if (empty($allowed_target_fields)) {
    // Send to error report.
    $error_message = t('There is no valid node field for vocabulary (vid:!vocab)', array(
      '!vocab' => $data['target_vid'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
  $data['allowed_target_fields'] = $allowed_target_fields;

  // Load the node.
  $node = node_load($data['nid']);

  // Get list of term reference fields for the node type in context.
  $taxonomy_fields = _dennis_term_manager_get_taxonomy_fields($node->type);

  // If target fields were provided, move them to the top of the list.
  $target_fields = array_reverse(array_map('trim', explode(',', $data['target_field'])));
  foreach ($target_fields as $field_name) {
    if (isset($taxonomy_fields[$field_name])) {
      // Move field to the top of the array.
      $tmp = array($field_name => $taxonomy_fields[$field_name]);
      unset($taxonomy_fields[$field_name]);
      $taxonomy_fields = array_merge($tmp, $taxonomy_fields);
    }
  }

  // Store term reference fields.
  $data['taxonomy_fields'] = $taxonomy_fields;

  // Flag to check if there were changed made on the current node.
  $data['node_changed'] = FALSE;

  _dennis_term_manager_swap_tids($node, $data);

  // If any tid has changed, then save the node.
  if ($data['node_changed']) {
    $node->path['pathauto'] = FALSE;
    node_save($node);
  }
}

/**
 * Helper to swap tids on nodes.
 *
 * @param $node
 *    The node object
 * @param $data
 *    Stores the information related to the current action being executed.
 */
function _dennis_term_manager_swap_tids($node, &$data) {
  $taxonomy_fields = $data['taxonomy_fields'];
  $node_changed = $data['node_changed'];
  $target_id = $data['target_tid'];
  $tid = $data['tid'];
  $allowed_target_fields = $data['allowed_target_fields'];

  // Flag to inform when the tid in context has is already found in any term reference field.
  $tid_used = array();

  // Loop the term reference fields and swap tids.
  foreach ($taxonomy_fields as $field_name => $field_info) {
    if (isset($node->{$field_name})) {
      // If the node has been changed, stop here.
      if ($node_changed) {
        break;
      }

      // Check if target tid already exists in this field.
      if (isset($node->{$field_name}[LANGUAGE_NONE][0])) {
        foreach ($node->{$field_name}[LANGUAGE_NONE] as $key => $value) {
          if ($value['tid'] == $target_id) {
            $tid_used[] = $target_id;
          }
        }
      }

      // Assign term to node if not already being used and field is allowed target vocab.
      if (!in_array($target_id, $tid_used) && in_array($field_name, $allowed_target_fields)) {
        // Swap tid if merging terms from the same vocabulary.
        if (isset($node->{$field_name}[LANGUAGE_NONE][0])) {
          foreach ($node->{$field_name}[LANGUAGE_NONE] as $key => $value) {
            if ($value['tid'] == $tid) {
              // Replace source tid with target tid.
              $node->{$field_name}[LANGUAGE_NONE][$key]['tid'] = $target_id;
              $node_changed = TRUE;
            }
          }
        }
        // If no change was made add the target tid to the allowed field.
        if (!$node_changed) {
          // Set if cardinality allows it.
          $field_full = count($node->{$field_name}[LANGUAGE_NONE]) >= $field_info['cardinality'];
          $unlimited = $field_info['cardinality'] == FIELD_CARDINALITY_UNLIMITED;
          if (!$field_full || $unlimited) {
            $node->{$field_name}[LANGUAGE_NONE][]['tid'] = $target_id;
            $node_changed = TRUE;
          }
        }
      }
    }
  }

  $data['node_changed'] = $node_changed;

  // Remove any left over tids.
  $node_changed = _dennis_term_manager_clean_tids($node, $data);

  return $node_changed;
}