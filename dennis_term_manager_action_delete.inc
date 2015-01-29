<?php
/**
 * @file
 * Action to delete taxonomy term
 */

function _dennis_term_manager_process_action_delete($data) {
  // Get all nodes with target term reference.
  $nodes = _dennis_term_manager_get_associated_nodes($data['source_tid']);
  if(!empty($nodes)) {
    // @todo: move to report.
    drupal_set_message(t('You can not delete term @term_name because there are nodes associated with it, in row @row.', array('@term_name' => $data['term_name'], '@row' => $data['row'])));
    return;
  }  
  // Get parent tid.
  if ($term_id = _dennis_term_manager_get_tid($data['term_name'], $data['vocabulary_machine_name'])) {
    if(taxonomy_term_delete($term_id) != SAVED_DELETED) {
      // @todo: move into report
      drupal_set_message(t('Not able to delete term @term_name in row @row', array('@term_name' => $data['term_name'], '@row' => $data['row'])));
    }
  }
  else {
    // @todo: move into report
    drupal_set_message(t('Term @term_name not found in row @row', array('@term_name' => $data['term_name'], '@row' => $data['row'])));
  }
}
