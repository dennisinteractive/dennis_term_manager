<?php
/**
 * @file
 * Action to Rename taxonomy term
 */

function _dennis_term_manager_process_action_rename($data) {
  //check if vocab name exist
  if (empty($data['vocabulary_name'])) {
    drupal_set_message(t("Vocabulary name is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  //check if term name exist
  if (empty($data['term_name'])) {
    drupal_set_message(t("Term name is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  //check if New term name exists
  if (empty($data['new_name'])) {
    drupal_set_message(t("New Term Name is empty in row @row", array('@term' => $data['term_name'], '@row' => $data['row'])));
    return;
  }
  // Get Vocabulary
  $vocabulary = dennis_term_manager_get_vocabulary($data['vocabulary_name']);
  if (!isset($vocabulary['machine_name']) || !isset($vocabulary['vid'])) {
    drupal_set_message(t("There is no vocabulary @vocab exists in database for create action on row @row", array('@row' => $data['row'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Get tid by term name
  $terms = taxonomy_get_term_by_name($data['term_name'], $vocabulary['machine_name']);
  reset($terms);
  $term_id = key($terms);
  if (!isset($term_id)) {
    drupal_set_message(t("Term '@term_name' does not exist in vocabulary '@vocab'", array('@term_name' => $data['term_name'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Rename the term.
  $first_term = array_shift($terms);
  $first_term->name = $data['new_name'];
  $res= taxonomy_term_save($first_term);
  pathauto_taxonomy_term_update_alias($first_term, 'update');
  drupal_set_message(t("Term '@term_name' successfully renamed", array('@term_name' => $first_term->name)));
}