<?php

/**
 * @file
 * Action to move parent of taxonomy term.
 */

/**
 * Action to move parent of taxonomy term.
 *
 * @param array $data
 *   An array containing source and target taxonomy term data
 */
function _dennis_term_manager_process_action_move_parent($data) {
  // Get term by tid.
  $term = taxonomy_term_load($data['tid']);
  // Change parent.
  if ($parent_term = taxonomy_term_load($data['parent_tid'])) {
    $term->parent = $parent_term->tid;
    if (!taxonomy_term_save($term)) {
      // Send to error report.
      $error_message = t("Term '@term_name' cannot be moved to @parent", array(
        '@term_name' => $term->name,
        '@parent' => $data['parent_tid'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
    }
  }
  else {
    // Send to error report.
    $error_message = t("Term '@term_name' cannot be moved to missing parent (tid:@parent)", array(
      '@term_name' => $term->name,
      '@parent' => $data['parent_tid'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
  }
}
