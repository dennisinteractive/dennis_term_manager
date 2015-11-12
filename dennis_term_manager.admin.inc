<?php
/**
 * @file
 * Contains the administrative functions of the dennis term manager module.
 */

/**
 * Implements hook_admin_settings_form().
 * Used to create the admin form to configure term manager.
 */
function dennis_term_manager_admin_settings_form() {
  // Set the current page title.
  drupal_set_title(t('Term Manager Settings'));

  $form = array();

  // Process Time.
  $form['dennis_term_manager_process_time'] = array(
    '#type' => 'select',
    '#title' => t('Process Time'),
    '#options' => range(0, 60),
    '#default_value' => variable_get('dennis_term_manager_process_time', 60),
    '#description' => 'Number of seconds to spend processing the queue on each cron run',
  );

  // Enabled.
  $form['dennis_term_manager_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#default_value' => variable_get('dennis_term_manager_enabled', 1),
    '#description' => 'Uncheck to pause processes. Enabling will continue the process.',
  );

  return system_settings_form($form);
}
 
