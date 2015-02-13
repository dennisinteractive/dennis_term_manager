<?php

/**
 * @file
 * Action to create new taxonomy terms.
 */

/**
 * Helper to create new taxonomy terms.
 * 
 * @param array $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_create($data) {
  // Check if Term already exist.
  if ($term_id = _dennis_term_manager_get_tid($data['term_name'], $data['vid'])) {
    // Write error message into the report file.
    $error_message = t("Term '@term_name' already exists in row @row", array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
  }
  else {
    // Create new term.
    $term = new stdClass();
    $term->name = $data['term_name'];
    $term->vid = $data['vid'];
    // Assign parent if it still exists.
    if (!empty($data['parent_tid']) && $parent_term = taxonomy_term_load($data['parent_tid'])) {
      $term->parent = $parent_term->tid;
    }
    if (!taxonomy_term_save($term)) {
      // Write error message into the report file.
      $error_message = t('Not able to create term @term_name in row @row', array(
        '@term_name' => $data['term_name'],
        '@row' => $data['row'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
    }
  }
}
