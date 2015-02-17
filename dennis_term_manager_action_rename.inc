<?php
/**
 * @file
 * Action to rename taxonomy term.
 */

/**
 * Rename taxonomy term.
 * 
 * @param array $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_rename($data) {
  // Check if New term is empty.
  if (empty($data['new_name'])) {
    // Send to error report.
    $error_message = t("New Term Name is empty in row @row", array(
      '@term' => $data['term_name'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }

  // Check if Term already exist.
  if ($term_id = _dennis_term_manager_get_tid($data['new_name'], $data['vid'])) {
    if ($term_id != $data['tid']) {
      // Send to error report.
      $error_message = t("Term '@term_name' already exists", array(
        '@term_name' => $data['new_name'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
      return;
    }
  }

  if ($term = taxonomy_term_load($data['tid'])) {
    // Rename.
    $term->name = _dennis_term_manager_decode_specific_chars($data['new_name']);
    if (!taxonomy_term_save($term)) {
      $error_message = t("Term '@term_name' cannot be renamed", array(
        '@term_name' => $term->name,
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
      return;
    }
  }
  else {
    // Send to error report.
    $error_message = t("Term '@term_name' does not exist in vocabulary @vocab", array(
      '@term_name' => $data['term_name'],
      '@vocab' => $data['vid'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
}
