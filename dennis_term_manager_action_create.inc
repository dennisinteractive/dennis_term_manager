<?php
/**
 * @files
 * Action to create new taxonomy terms.
 */

function _dennis_term_manager_process_action_create($data) {
  // Check if vocabulary name exist.
  if (empty($data['vocabulary_name'])) {
    drupal_set_message(t("vocabulary name does not exist in row @row", array('@row' => $data['row'])));
    return;
  }
  // Get vocabulary by vocabulary name.
  $vocabulary = dennis_term_manager_get_vocabulary($data['vocabulary_name']);

  // Check if Vocabulary exist in database.
  if (!isset($vocabulary['machine_name']) || !isset($vocabulary['vid'])) {
    drupal_set_message(t("There is no vocabulary @vocab exists in database for create action on row @row", array('@row' => $data['row'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Check if Term already exist.
  $existing_term = taxonomy_get_term_by_name($data['term_name'], $vocabulary['machine_name']);
  if (!empty($existing_term)) {
    drupal_set_message(t("Term '@term_name' already exist in vocabulary '@vocab'", array('@term_name' => $data['term_name'], '@vocab' => $data['vocabulary_name'])));
    return;
  }
  // Get parent term
  // @todo: check parent term format, if its not "tid>->tid" Then we need to refactor parent tree below
  $parent = array_filter(explode('>->', $data['parent_term']));
  $parent = empty($parent) ? 0 : $parent;

  // Create new term.
  $term = new stdClass();
  $term->name = $data['term_name'];
  $term->vid = $vocabulary['vid'];
  $term->vocabulary_machine_name = $vocabulary['machine_name'];
  $term->parent = $parent;
  taxonomy_term_save($term);

  if(!isset($term->tid)) {
    drupal_set_message(t('Not able to create term @term_name in row @row', array('@term_name' => $data['term_name'], '@row' => $data['row'])));
  }
  else {
    drupal_set_message(t("Term '@term_name' with TID '@tid' created", array('@term_name' => $term->name, '@tid' => $term->tid)));
  }
}