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
