<?php

/**
 * @file
 * Action to delete taxonomy term.
 */

/**
 * Helper to process delete taxonomy term action.
 * 
 * @param array $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_delete($data) {
  if (isset($data['nid'])) {
    // If queue item contains nid, remove term from term reference fields.
    _dennis_term_manager_process_action_delete_term_from_node($data);
  }
  else {
    // Delete term if nid is not specified.
    // - This should occur after it has been removed from term reference fields.
    _dennis_term_manager_process_action_delete_term($data);
  }
}

/**
 * Delete term.
 *
 * @param $data
 */
function _dennis_term_manager_process_action_delete_term($data) {
  // Get all nodes with target term reference.
  $nodes = _dennis_term_manager_get_associated_nodes($data['tid']);
  if (!empty($nodes)) {
    // Send to error report.
    $error_message = t('You can not delete term @term_name because there are nodes associated with it, in row @row.', array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
  // Check if term has children.
  if ($children = taxonomy_get_children($data['tid'])) {
    // Send to error report.
    $error_message = t('You can not delete term @term_name because it has children, in row @row.', array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
  // Attempt delete.
  if ($term = taxonomy_term_load($data['tid'])) {
    if (taxonomy_term_delete($term->tid) != SAVED_DELETED) {
      // Send to error report.
      $error_message = t('Not able to delete term @term_name (tid:@tid) in row @row', array(
        '@term_name' => $term->name,
        '@tid' => $term->tid,
        '@row' => $data['row'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
    }
  }
  else {
    // Send to error report.
    $error_message = t('Term @term_name (tid:@tid) not found in row @row', array(
      '@term_name' => $data['term_name'],
      '@tid' => $data['tid'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
  }
}

/**
 * Remove term from referencing nodes.
 *
 * @param array $data
 *   An array containing node and source taxonomy data.
 */
function _dennis_term_manager_process_action_delete_term_from_node($data) {
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

  // Build array of term reference fields.
  $taxonomy_fields = array();

  // Get all term reference fields.
  if ($entity_info = field_info_instances('node', $node->type)) {
    foreach ($entity_info as $info) {
      // Check each node field for term reference.
      if ($info['display']['default']['type'] == 'taxonomy_term_reference_link') {
        $taxonomy_fields[] = $info['field_name'];
      }
    }
  }

  // Remove source tid from field.
  foreach ($taxonomy_fields as $field_name) {
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

  // If any tid has changed, then save the node.
  if ($node_changed) {
    $node->path['pathauto'] = FALSE;
    node_save($node);
  }
}
