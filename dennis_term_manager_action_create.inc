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
    return;
  }

  // Create new term.
  $term = new stdClass();
  $term->name = $data['term_name'];
  $term->vid = $data['vid'];

  // Assign parent if it still exists.
  if (!empty($data['parent_tid'])) {
    // Load parent if tid is available.
    if ($parent_term = taxonomy_term_load($data['parent_tid'])) {
      $term->parent = $parent_term->tid;
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
        $error_message = t("Parent term '@term_name' could not be found in row @row. @term_name has been created without a parent.", array(
          '@term_name' => $data['parent_term_name'],
          '@row' => $data['row'],
        ));
        _dennis_term_manager_write_report($data['report_fid'], $error_message);
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

  if (!taxonomy_term_save($term)) {
    // Write error message into the report file.
    $error_message = t('Not able to create term @term_name in row @row', array(
      '@term_name' => $data['term_name'],
      '@row' => $data['row'],
    ));
    _dennis_term_manager_write_report($data['report_fid'], $error_message);
    return;
  }
}
