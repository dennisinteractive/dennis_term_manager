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

  // Get node data.
  $node = node_load($data['nid']);

  // Set node change status.
  $node_changed = FALSE;

  // Get info of each field on the current node type.
  $taxonomy_fields = array();
  if ($entity_info = field_info_instances('node', $node->type)) {
    foreach ($entity_info as $field) {
      $field_name = $field['field_name'];
      $field_info = field_info_field($field_name);
      if ($field_info['type'] == 'taxonomy_term_reference') {
        $taxonomy_fields[$field_name] = $field_info;
      }
    }
  }

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

  // Loop the term reference fields and swap tids.
  $tid_used = array();
  foreach ($taxonomy_fields as $field_name => $field_info) {
    if (isset($node->{$field_name})) {
      // If the node has been changed, stop here.
      if ($node_changed) {
        break;
      }

      // Check if target tid already exists in this field.
      if (isset($node->{$field_name}[LANGUAGE_NONE][0])) {
        foreach ($node->{$field_name}[LANGUAGE_NONE] as $key => $value) {
          if ($value['tid'] == $data['target_tid']) {
            $tid_used[] = $data['target_tid'];
          }
        }
      }

      // Assign term to node if not already being used and field is allowed target vocab.
      if (!in_array($data['target_tid'], $tid_used) && in_array($field_name, $allowed_target_fields)) {
        // Swap tid if merging terms from the same vocabulary.
        if (isset($node->{$field_name}[LANGUAGE_NONE][0])) {
          foreach ($node->{$field_name}[LANGUAGE_NONE] as $key => $value) {
            if ($value['tid'] == $data['tid']) {
              // Replace source tid with target tid.
              $node->{$field_name}[LANGUAGE_NONE][$key]['tid'] = $data['target_tid'];
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
            $node->{$field_name}[LANGUAGE_NONE][]['tid'] = $data['target_tid'];
            $node_changed = TRUE;
          }
        }
      }
    }
  }

  // Remove source tid from field.
  foreach ($taxonomy_fields as $field_name => $field_info) {
    // Check each node field for term reference.
    if (isset($node->{$field_name}[LANGUAGE_NONE][0])) {
      foreach ($node->{$field_name}[LANGUAGE_NONE] as $key => $value) {
        if ($value['tid'] == $data['tid']) {
          unset($node->{$field_name}[LANGUAGE_NONE][$key]);
          $node_changed = TRUE;
        }
      }
    }
  }

krumo($node);

  // If any tid has changed, then save the node.
  if ($node_changed) {
    $node->path['pathauto'] = FALSE;
    node_save($node);
  }
}
