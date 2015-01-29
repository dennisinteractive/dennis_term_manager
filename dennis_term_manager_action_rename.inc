<?php
/**
 * @file
 * Action to Rename taxonomy term
 */

function _dennis_term_manager_process_action_rename($data) {
  // Check if New term is empty.
  if (empty($data['new_name'])) {
    // @todo: add this message to generated report.
    drupal_set_message(t("New Term Name is empty in row @row", array('@term' => $data['term_name'], '@row' => $data['row'])));
    return;
  }

  // Check if Term already exist.
  if ($term_id = _dennis_term_manager_get_tid($data['new_name'], $data['vocabulary_machine_name'])) {
    // @todo: add this message to generated report.
    drupal_set_message(t("Term '@term_name' already exists", array('@term_name' => $data['new_name'])));
  }
  else {
    if ($term = taxonomy_term_load($data['source_tid'])) {
      // Rename.
      $term->name = $data['new_name'];
      if (!taxonomy_term_save($term)) {
        // @todo: add this message to generated report.
        drupal_set_message(t("Term '@term_name' cannot be renamed", array('@term_name' => $term->name)));
      }
    }
    else {
      // @todo: move to report
      drupal_set_message(t("Term '@term_name' does not exist in vocabulary '@vocab'", array('@term_name' => $data['term_name'], '@vocab' => $data['vocabulary_id'])));
    }

  }
}
