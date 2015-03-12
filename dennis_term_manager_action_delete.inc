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
      return;
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
    return;
  }
}

/**
 * Remove term from referencing nodes.
 *
 * @param array $data
 *   An array containing node and source taxonomy data.
 */
function _dennis_term_manager_process_action_delete_term_from_node($data) {
  if (!isset($data['nid'])) {
    // Send to error report.
    $error_message = t('Node ID is not presented in current queue item which belongs to row @row', array(
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }

  // Load the node.
  $node = node_load($data['nid']);

  // Get list of term reference fields for the node type in context.
  $taxonomy_fields = _dennis_term_manager_get_taxonomy_fields($node->type);

  // Store term reference fields.
  $data['taxonomy_fields'] = $taxonomy_fields;

  // Flag to check if there were changed made on the current node.
  $data['node_changed'] = FALSE;

  // Remove source tid from field.
  _dennis_term_manager_clean_tids($node, $data);

  // If any tid has changed, then save the node.
  if ($data['node_changed']) {
    $node->path['pathauto'] = FALSE;
    node_save($node);
  }
}
