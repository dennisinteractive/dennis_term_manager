<?php

/**
 * Action to delete taxonomy term.
 * 
 */

/**
 * Helper to process delete taxonomy term action.
 * 
 * @param $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_delete($data) {
  // Get all nodes with target term reference.
  $nodes = _dennis_term_manager_get_associated_nodes($data['source_tid']);
  if (! empty($nodes)) {
    // Send to error report.
    $error_message = t('You can not delete term @term_name because there are nodes associated with it, in row @row.', array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row']
    ));
    _dennis_term_manager_write_report($data['report_filename'], $error_message);
    return;
  }
  // Get parent tid.
  if ($term_id = _dennis_term_manager_get_tid($data['term_name'], $data['vocabulary_machine_name'])) {
    if (taxonomy_term_delete($term_id) != SAVED_DELETED) {
      // Send to error report.
      $error_message = t('Not able to delete term @term_name in row @row', array(
        '@term_name' => $data['term_name'],
        '@row' => $data['row']
      ));
      _dennis_term_manager_write_report($data['report_filename'], $error_message);
    }
  }
  else {
    // Send to error report.
    $error_message = t('Term @term_name not found in row @row', array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row']
    ));
    _dennis_term_manager_write_report($data['report_filename'], $error_message);
  }
}
