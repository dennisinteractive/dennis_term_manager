<?php
/**
 * @file
 * Testing suite for Dennis Term Manager.
 */

/**
 * Runs the whole test suite.
 *
 * @return bool
 */
function dennis_term_manager_test_suite() {
  $destination = _dennis_term_manager_get_files_folder();

  // Create taxonomy tree.
  _dennis_term_manager_create_taxonomy();

  // Export CSV of taxonomy tree.
  dennis_term_manager_export_terms(',', array('Term Manager Vocab 1', 'Term Manager Vocab 2'), DENNIS_TERM_MANAGER_DESTINATION_FILE);

  // Compare exported CSV against the CSV saved on the repo.
  // If you modify the tree, you will have to replace the file /tests/term_manager_test_original.csv
  // with the modified version and commit it to the repo.
  $expected = drupal_get_path('module', 'dennis_term_manager') . '/tests/term_manager_test_original.csv';
  $outputted = drupal_realpath($destination) . '/term_manager_test_original.csv';

  // Compare exported tree against tree saved on repo.
  dennis_term_manager_diff($expected, $outputted);

  // Load CSV containing actions and execute it.
  $actions_file = drupal_get_path('module', 'dennis_term_manager') . '/tests/term_manager_test_actions_pass.csv';
  $file = dennis_term_manager_file_copy($actions_file, $destination);

  $date = date('Y-m-d_H-i-s', REQUEST_TIME);
  $errors_file = preg_replace("/(.*)[.](.*)/", "$1-$date-errors.$2", $file->uri);
  $dry_run_file = preg_replace("/(.*)[.](.*)/", "$1-$date-dry_run.$2", $file->uri);
  $report_file = preg_replace("/(.*)[.](.*)/", "$1-$date-report.txt", $file->uri);

  // Process the file.
  $batch = _dennis_term_manager_batch_init($file);
  batch_set($batch);

  //_dennis_term_manager_cleanup();
}

/**
 * Helper to do a Diff between files.
 */
function dennis_term_manager_diff($file1, $file2) {
  $diff = shell_exec("diff $file1 $file2");
  if (strlen($diff) > 0) {
    throw new \Exception(sprintf('"%s" files don\'t match "%s"', $diff, $file1 . ' vs ' . $file2));
  }
}

/**
 * Helper to create the initial taxonomy tree.
 */
function _dennis_term_manager_create_taxonomy() {
  // Create vocabs.
  _dennis_term_manager_create_vocabulary('Term Manager Vocab 1');
  _dennis_term_manager_create_vocabulary('Term Manager Vocab 2');

  // Create terms.
  // _dennis_term_manager_create_term($vocabulary_name, $term_name, $parent_name = NULL, $description = '', $format = 'filtered_html') {
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Fruits');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Simple', 'Fruits');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Berries', 'Simple');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Grapes', 'Berries');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Hesperidiums', 'Simple');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Lemon', 'Hesperidiums');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Orange', 'Hesperidiums');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Pepos', 'Simple');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Melon', 'Pepos');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Pumpkin', 'Pepos');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Drupes', 'Simple');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Peach', 'Drupes');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Plum', 'Drupes');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Pomes', 'Simple');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Apple', 'Pomes');
  _dennis_term_manager_create_term('Term Manager Vocab 1', 'Pear', 'Pomes');

  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Fruits');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Aggregate', 'Fruits');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Strawberry', 'Aggregate');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Blackberry', 'Aggregate');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Raspberry', 'Aggregate');

  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Multiple', 'Fruits');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Pineapple', 'Multiple');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Fig', 'Multiple');
  _dennis_term_manager_create_term('Term Manager Vocab 2', 'Mulberry', 'Multiple');
}

/**
 * Helper to clean up after tests run.
 */
function _dennis_term_manager_cleanup() {
  // Clean up vocabs.
  _dennis_term_manager_delete_vocabulary('Term Manager Vocab 1');
  _dennis_term_manager_delete_vocabulary('Term Manager Vocab 2');
}

/**
 *
 * Helper to create vocabs.
 * @param $name
 * @return bool|int
 */
function _dennis_term_manager_create_vocabulary($name) {
  $machine_name = _dennis_term_manager_machine_name($name);

  if (!taxonomy_vocabulary_machine_name_load($machine_name)) {
    $new_vocab = (object) array(
      'name' => $name,
      'description' => 'This vocabulary is used to test Term manager',
      'machine_name' => $machine_name,
    );

   return taxonomy_vocabulary_save($new_vocab);
  }
}

/**
 * Helper to delete vocabs.
 *
 * @param $name
 */
function _dennis_term_manager_delete_vocabulary($name) {
  $machine_name = _dennis_term_manager_machine_name($name);
  $vocab = taxonomy_vocabulary_machine_name_load($machine_name);

  return taxonomy_vocabulary_delete($vocab->vid);
}

/**
 * Helper to Create terms.
 */
function _dennis_term_manager_create_term($vocabulary_name, $term_name, $parent_name = NULL, $description = '', $format = 'filtered_html') {
  $vocabulary_name = _dennis_term_manager_machine_name($vocabulary_name);

  $term = taxonomy_get_term_by_name($term_name, $vocabulary_name);

  if (empty($term)) {
    // Create term
    $vocabulary = taxonomy_vocabulary_machine_name_load($vocabulary_name);
    $term_data = array(
      'name' => $term_name,
      'description' => $description,
      'format' => $format,
      'vid' => $vocabulary->vid,
    );
    if ($parent_name) {
      $parent = taxonomy_get_term_by_name($parent_name, $vocabulary_name);
      $term_data['parent'] = array_keys($parent);
    }
    if (!taxonomy_term_save((object) $term_data)) {
      throw new \Exception(sprintf("Error saving term '%s'", $term_data['name']));
    }
    $term = taxonomy_get_term_by_name($term_name, $vocabulary_name);
  }
  $keys = array_keys($term);

  return $term[$keys[0]];
}
