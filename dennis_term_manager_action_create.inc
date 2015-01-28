<?php
/**
 * @files
 * Action to create new taxonomy terms.
 */

function _dennis_term_manager_process_action_create($data) {

  // Check if Term already exist.
  if ($term_id = _dennis_term_manager_get_tid($data['term_name'], $data['vocabulary_id'])) {
    drupal_set_message(t("Term '@term_name' already exists", array('@term_name' => $data['term_name'])));
  }
  else {
    // Create new term.
    $term = new stdClass();
    $term->name = $data['term_name'];
    $term->vid = $data['vocabulary_id'];
    $term->vocabulary_machine_name = $data['vocabulary_machine_name'];
    $term->parent = _dennis_term_manager_get_tid($data['parent_term'], $data['vocabulary_id']);
    if (!taxonomy_term_save($term)) {
      // @todo: move to report.
      drupal_set_message(t('Not able to create term @term_name in row @row', array('@term_name' => $data['term_name'], '@row' => $data['row'])));
    }
  }
}