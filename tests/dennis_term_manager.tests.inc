<?php
/**
 * @file
 * Testing suite for Dennis Term Manager.
 */

/**
 * Run CSV to create the initial tree.
 */
function dennis_term_manager_tests_create() {
  $destination = _dennis_term_manager_get_files_folder();
  $actions_file = drupal_get_path('module', 'dennis_term_manager') . '/tests/test_create_run.csv';

  // Initial cleanup of taxonomy tree.
  _dennis_term_manager_cleanup();

  // Copy the CSV file into files folder.
  $file = dennis_term_manager_file_copy($actions_file, $destination);

  // Process file.
  dennis_term_manager_test_process($file);
}

/**
 * Compare with the created tree vs expected tree.
 */
function dennis_term_manager_tests_test_create() {
  // Export CSV of taxonomy tree.
  dennis_term_manager_export_terms(',', array('Category'), DENNIS_TERM_MANAGER_DESTINATION_FILE);

  $destination = _dennis_term_manager_get_files_folder();
  $exported_tree = drupal_realpath($destination) . '/taxonomy_export.csv';

  // The pass tree is stored in the repo and used for the comparison with generated tree.
  $pass_tree = drupal_get_path('module', 'dennis_term_manager') . '/tests/test_create_pass.csv';

  // Compare exported CSV against the CSV saved on the repo.
  // Pass tree must be contained inside the exported tree in order for the test to pass.
  dennis_term_manager_diff($pass_tree, $exported_tree);
}

/**
 * Run CSV with actions.
 */
function dennis_term_manager_tests_actions() {
  $destination = _dennis_term_manager_get_files_folder();
  $actions_file = drupal_get_path('module', 'dennis_term_manager') . '/tests/test_actions_run.tsv';

  // Copy the file into files folder.
  $file = dennis_term_manager_file_copy($actions_file, $destination);

  // Process file.
  dennis_term_manager_test_process($file);
}

/**
 * Test actions against the expected resulting tree.
 */
function dennis_term_manager_tests_test_actions() {
  // Export CSV of taxonomy tree.
  dennis_term_manager_export_terms(',', array('Category'), DENNIS_TERM_MANAGER_DESTINATION_FILE);

  $destination = _dennis_term_manager_get_files_folder();

  // Get the exported terms.
  $exported_tree = drupal_realpath($destination) . '/taxonomy_export.csv';

  // Pass tree is stored in the repo
  $pass_tree = drupal_get_path('module', 'dennis_term_manager') . '/tests/test_actions_pass.csv';

  // Compare exported CSV against the CSV saved on the repo.
  // Pass tree must be contained inside the exported tree in order for the test to pass.
  dennis_term_manager_diff($pass_tree, $exported_tree);
}

/**
 * Process the file
 */
function dennis_term_manager_test_process($file) {
  if (!is_object($file)) {
    return;
  }

  $batch = _dennis_term_manager_batch_init($file);
  batch_set($batch);

  $batch =& batch_get();
  if (!empty($batch)) {
    $batch['progressive'] = FALSE;

    drush_backend_batch_process();

    // Process the queue.
    foreach (dennis_term_manager_cron_queue_info() as $queue_name => $info) {
      $function = $info['worker callback'];
      if ($queue = DrupalQueue::get($queue_name)) {
        while ($item = $queue->claimItem()) {
          $function($item->data);
          $queue->deleteItem($item);
        }
      }
    }
  }

  $date = date('Y-m-d_H-i-s', REQUEST_TIME);
  $errors_file = preg_replace("/(.*)[.](.*)/", "$1-$date-errors.$2", $file->uri);
  $dry_run_file = preg_replace("/(.*)[.](.*)/", "$1-$date-dry_run.$2", $file->uri);
  $report_file = preg_replace("/(.*)[.](.*)/", "$1-$date-report.txt", $file->uri);

  // Test that file with errors doesn't exist.
  if (file_exists($errors_file)) {
    throw new Exception(t('There were errors during execution, see !file_name for more details', array(
      '!file_name' => $errors_file,
    )));
  }
}

/**
 * Helper to do a Diff between files.
 * @todo: Rename this function:
 * Initially this function would do a diff,
 * but now it just tests if the term tree matches
 * the terms created.
 */
function dennis_term_manager_diff($pass_tree, $exported_tree) {
  if (!file_exists($pass_tree)) {
    throw new Exception(t('!file doesn\'t exist', array(
      '!file' => $pass_tree,
    )));
  }
  $test_content = file_get_contents($pass_tree);

  if (!file_exists($exported_tree)) {
    throw new Exception(t('!file doesn\'t exist', array(
      '!file' => $exported_tree,
    )));
  }
  $tree_content = file_get_contents($exported_tree);

  // Remove heading.
  $test_content_lines = explode("\n", $test_content);
  array_shift($test_content_lines);
  $test_content = implode("\n", $test_content_lines);

  // Check if the pass tree is in the exported tree.
  if (strpos($tree_content, $test_content, 0) === FALSE) {
    // Get the failing line.
    $test_content_cumulative = '';
    foreach ($test_content_lines as $line) {
      $test_content_cumulative .= $line . "\n";
      if (strpos($tree_content, $test_content_cumulative, 0) === FALSE) {
        $failing_line = $line;
        break;
      }
    }
    // Throw exception with failing line.
    throw new Exception(t('Exported tree !file1 doesn\'t match !file2 at row !line', array(
      '!file1' => $exported_tree,
      '!file2' => $pass_tree,
      '!line' => $failing_line,
    )));
  }
}

/**
 * Helper to clean up after tests run.
 */
function _dennis_term_manager_cleanup() {
  // Delete terms created during tests.
  $term = reset(taxonomy_get_term_by_name('Temp', 'category'));
  taxonomy_term_delete($term->tid);
  $term = reset(taxonomy_get_term_by_name('TM-Fruits', 'category'));
  taxonomy_term_delete($term->tid);
  $term = reset(taxonomy_get_term_by_name('TM-Fruits2', 'category'));
  taxonomy_term_delete($term->tid);
}
