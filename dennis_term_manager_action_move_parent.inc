<?php
/**
 * @file
 * Action to move parent of taxonomy term
 */

function _dennis_term_manager_process_action_move_parent($data) {
  //check if vocab name exists.
  if (empty($data['vocabulary_name'])) {
    drupal_set_message(t("Vocabulary name is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  //check if term name exists.
  if (empty($data['term_name'])) {
    drupal_set_message(t("Term name is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  //check parent vocab
  if (empty($data['parent_vocabulary'])) {
    drupal_set_message(t("Parent vocabulary is empty in row @row", array('@row' => $data['row'])));
    return;
  }
  // Check parent term
  if (empty($data['parent_term'])) {
    drupal_set_message(t("Parent Term is empty in row @row", array('@row' => $data['row'])));
    return;
  }

  // Get vocabulary
  $vocabulary = dennis_term_manager_get_vocabulary($data['vocabulary_name']);
  if (!isset($vocabulary['machine_name']) || !isset($vocabulary['vid'])) {
    drupal_set_message(t("There is no vocabulary @vocab exists in database for create action on row @row", array('@row' => $data['row'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Get Parent Vocabulary
  $parent_vocabulary = dennis_term_manager_get_vocabulary($data['parent_vocabulary']);
  if (!isset($parent_vocabulary['machine_name']) || !isset($parent_vocabulary['vid'])) {
    drupal_set_message(t("There is no vocabulary @vocab exists in database for create action on row @row", array('@row' => $data['row'], '@vocab' => $data['parent_vocabulary'])));
    return;
  }

  //Get parent tid
  $parent_terms = taxonomy_get_term_by_name($data['parent_term'], $parent_vocabulary['machine_name']);
  reset($parent_terms);
  $parent_term_id = key($parent_terms);
  if (!isset($parent_term_id)) {
    drupal_set_message(t("Parent Term '@term_name' does not exist in vocabulary '@vocab'", array('@term_name' => $data['parent_term'], '@vocab' => $data['parent_vocabulary'])));
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

  // Move the term.
  $first_term = array_shift($terms);

  $first_term->parent = $parent_term_id;
  $first_term->vid = $parent_vocabulary['vid'];
  //print_r($first_term);return;
  $res= taxonomy_term_save($first_term);
  //pathauto_taxonomy_term_update_alias($first_term, 'update');
  drupal_set_message(t("Term '@term_name' successfully moved", array('@term_name' => $first_term->name)));
}