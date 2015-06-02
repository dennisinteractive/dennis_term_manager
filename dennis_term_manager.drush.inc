<?php

/**
 * @file
 *   drush integration.
 */

/**
 * Implements hook_drush_command().
 *
 * @return
 *   An associative array describing your command(s).
 */
function dennis_term_manager_drush_command() {
  $items = array();

  $items['term-manager-cleanup'] = array(
    'description' => "Clean testing taxonomy tree.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => '_dennis_term_manager_cleanup',
  );
  $items['term-manager-init'] = array(
      'description' => "Creates the initial tree for testings.",
  		'drupal dependencies' => array('dennis_term_manager'),
      'callback' => 'dennis_term_manager_test_create_tree',
  );
  $items['term-manager-actions'] = array(
    'description' => "Runs the actions CSV.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => 'dennis_term_manager_test_actions',
  );
  
  return $items;
}

/**
 * Implements hook_drush_help().
 */
function dennis_term_manager_drush_help($section) {
  switch ($section) {
    case 'drush:term-manager-cleanup':
      return dt("Clean testing taxonomy tree.");
    case 'drush:term-manager-init':
      return dt("Creates the initial tree for testings.");
    case 'drush:term-manager-init':
      return dt("Runs the actions CSV.");
  }
}
