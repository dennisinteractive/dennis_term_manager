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
  if (!$term = taxonomy_term_load($data['tid'])) {
    // Send to error report.
    $error_message = t('Term \'@term_name\' not found in \'move_parent\' action', array(
      '@term_name' => $data['term_name'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }

  // Assign parent if it still exists.
  if (!empty($data['parent_tid'])) {
    // Load parent if tid is available.
    if ($parent_term = taxonomy_term_load($data['parent_tid'])) {
      $term->parent = $parent_term->tid;
    }
    else {
      // Send to error report.
      $error_message = t("Term '@term_name' cannot be moved to missing parent (tid:@parent)", array(
        '@term_name' => $term->name,
        '@parent' => $data['parent_tid'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
      return;
    }
  }
  elseif (!empty($data['parent_term_name'])) {
    // Try to get parent by name.
    if ($vocabulary = taxonomy_vocabulary_load($data['vid'])) {
      if ($parent_terms = taxonomy_get_term_by_name($data['parent_term_name'], $vocabulary->machine_name)) {
        $parent_term = reset($parent_terms);
        if (isset($parent_term->tid)) {
          $term->parent = $parent_term->tid;
        }
      }
      else {
        // Could not find parent term.
        $error_message = t("Parent term '@term_name' could not be found in row @row", array(
          '@term_name' => $data['parent_term_name'],
          '@row' => $data['row'],
        ));
        _dennis_term_manager_write_report($data['report_fid'], $error_message);
        return;
      }
    }
    else {
      // Could not find vocabulary.
      $error_message = t("Vocabulary id '@vid' could not be found in row @row.", array(
        '@vid' => $data['vid'],
        '@row' => $data['row'],
      ));
      _dennis_term_manager_write_report($data['report_fid'], $error_message);
      return;
    }
  }
  else {
    // Move to root.
    $term->parent = 0;
  }

  // Try to save term with new parent.
  if (!taxonomy_term_save($term)) {
    // Send to error report.
    $error_message = t("Term '@term_name' cannot be moved to @parent", array(
      '@term_name' => $term->name,
      '@parent' => $data['parent_tid'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
}
