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

  $items['tm-cleanup'] = array(
    'description' => "Clean testing taxonomy tree.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => '_dennis_term_manager_cleanup',
  );
  $items['tm-create'] = array(
    'description' => "Creates the initial tree for testings.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => 'dennis_term_manager_tests_create',
  );
  $items['tm-test-create'] = array(
    'description' => "Tests the result of initial tree creation.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => 'dennis_term_manager_tests_test_create',
  );
  $items['tm-actions'] = array(
    'description' => "Runs the actions CSV.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => 'dennis_term_manager_tests_actions',
  );
  $items['tm-test-actions'] = array(
    'description' => "Test the result of the actions executed.",
    'drupal dependencies' => array('dennis_term_manager'),
    'callback' => 'dennis_term_manager_tests_test_actions',
  );

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function dennis_term_manager_drush_help($section) {
  switch ($section) {
    case 'drush:tm-cleanup':
      return dt("Clean testing taxonomy tree.");
    case 'drush:tm-create':
      return dt("Creates the initial tree for testings.");
    case 'drush:tm-test-create':
      return dt("Tests the initial tree for testings.");
    case 'drush:tm-actions':
      return dt("Runs the actions CSV.");
    case 'drush:tm-test-actions':
      return dt("Test the actions CSV.");
  }
}
