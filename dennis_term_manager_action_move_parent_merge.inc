<?php
/**
 * @file
 * Action to move parent of taxonomy term
 */

/**
 * @todo
 * @param unknown_type $data
 */
function _dennis_term_manager_process_action_move_parent_for_merge($data) {
    // Get term by tid.
    $term = taxonomy_term_load($data['moving_child_tid']);
    // Change parent.
    $term->parent = $data['moving_parent_tid'];
    if (!taxonomy_term_save($term)) {
      // @todo: add this message to generated report.
      drupal_set_message(t("Term '@term_name' cannot be moved to @parent", array('@term_name' => $term->name, '@parent' => $data['parent_term'])));
    }
}