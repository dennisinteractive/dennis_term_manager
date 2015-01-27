<?php
/**
 * @file
 * Action to delete taxonomy term
 */

function _dennis_term_manager_process_action_delete($data) {

  // Validate vocabulary column.
  if (empty($data['vocabulary_name'])) {
    drupal_set_message(t("Vocabulary name is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  // Validate term name.
  if (empty($data['term_name'])) {
    drupal_set_message(t("Empty term name in row @row", array('@row' => $data['row'])));
    return;
  }
  // Get vocabulary
  $vocabulary = dennis_term_manager_get_vocabulary($data['vocabulary_name']);
  if (!isset($vocabulary['machine_name']) || !isset($vocabulary['vid'])) {
    drupal_set_message(t("There is no vocabulary @vocab exists in database for create action on row @row", array('@row' => $row, '@vocab' => $data['vocabulary_name'])));
  }
  // Get tid by term_name
  $terms = taxonomy_get_term_by_name($data['term_name'], $vocabulary['machine_name']);
  reset($terms);
  $term_id = key($terms);

  if (!isset($term_id)) {
    drupal_set_message(t("Term '@term_name' does not exist in vocabulary '@vocab'", array('@term_name' => $data['term_name'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Delete the terms
  $result = taxonomy_term_delete($term_id);
  if($result == SAVED_DELETED) {
    drupal_set_message(t("Term '@term_name' successfully deleted in row @row", array('@row' => $data['row'], '@term_name' => $data['term_name'])));
  }
  else {
    drupal_set_message(t('Not able to delete term in row @row', array('@row' => $data['row'])));
  }
  // Check if related node doesnt exist
  if ($related_nodes != 0) {
    drupal_set_message(t("There are related nodes exists for @term actioned to be deleted in row @row", array('@term' => $data['term_name'], '@row' => $data['row'])));
  }
}