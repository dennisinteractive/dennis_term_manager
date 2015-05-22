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

  $items['term-manager-tests'] = array(
      'description' => "Runs the Term manager test suite",
  		'drupal dependencies' => array('dennis_term_manager'),
      'callback' => 'drush_dennis_term_manager_tests',
  		'options' => array(
  				'all' => "Run all tests",
  				),
  );
  
  return $items;
}

/**
 * Implements hook_drush_help().
 */
function dennis_term_manager_drush_help($section) {
  switch ($section) {
    case 'drush:term-manager-test':
      return dt("Runs the Term manager test suite");
  }
}

/**
 * Helper to do the actions.
 */
function drush_dennis_term_manager_tests() {
	if (drush_get_option('all')) {
		drush_print('Running all tests');
	}

  dennis_term_manager_test_suite();
  //drush_print('call main function here');
}
