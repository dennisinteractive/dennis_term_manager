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

/**
 * Form with confirmation to pass queue_id and file_id
 * @param $form
 * @param $form_state
 * @return mixed
 */
function dennis_term_manager_delete_form($form, &$form_state) {
  $form = array();
  $queue_id = arg(4);
  $file_id = arg(5);
  $form['queue_id'] = array(
    '#type' => 'value',
    '#value' => $queue_id
  );
  $form['file_id'] = array(
    '#type' => 'value',
    '#value' => $file_id
  );
  $form['#submit'][] = 'dennis_term_manager_delete_form_submit';
  return confirm_form(
    $form,
    t('Are you sure you want to delete this queue?'),
    'admin/structure/taxonomy/term_manager',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Submit handler for dennis_term_manager_delete_form.
 * @param $form
 * @param $form_state
 */
function dennis_term_manager_delete_form_submit($form, &$form_state) {
  form_state_values_clean($form_state);
  extract($form_state['values']);
  $item = new TermManagerProgressItem($file_id);
  $item->setFinalQueueId($queue_id);
  $item->delete();
  $item->deleteFromDb($queue_id);
  drupal_set_message('The queue has been deleted');
  drupal_goto('admin/structure/taxonomy/term_manager');
}
