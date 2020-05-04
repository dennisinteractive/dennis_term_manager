<?php

namespace Drupal\dennis_term_manager;

Use \Drupal\Core\File\FileSystemInterface;

/**
 * @file TermManagerOperationList
 */
class TermManagerOperationList implements \Iterator, \Countable {
  /**
   * Iterator position.
   * @var integer
   */
  private $position = 0;

  /**
   * List of OperationItem.
   * @var array
   */
  protected $operationList = [];

  /**
   * List of errors.
   * @var array
   */
  protected $errorList = [];

  /**
   * Initialize iterator.
   */
  public function __construct() {
    $this->position = 0;
  }

  /**
   * Add OperationItem to List.
   *
   * @param TermManagerOperationItem $operationItem
   */
  public function add(TermManagerOperationItem $operationItem) {
    if (!empty($operationItem->error)) {
      $this->errorList[] = array(
        'vocabulary_name' => $operationItem->vocabulary_name,
        'term_name' => $operationItem->term_name,
        'message' => $operationItem->error,
      );
    }
    $this->operationList[] = $operationItem;
  }

  /**
   * Return array of operation items.
   */
  public function getItems() {
    return $this->operationList;
  }

  /**
   * Return array of errors.
   */
  public function getErrorList() {
    return $this->errorList;
  }

  /**
   * Output queue items as CSV.
   *
   * @param $file_path
   * @param $delimiter
   */

  /**
   * @param $file_path
   * @param $delimiter
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function outputCSV($file_path, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s', \Drupal::time()->getRequestTime());
    $file_name = preg_replace("/[.](.*)/", "-" . $date . "-errors.$1", $file_path);

    // Create managed file and open for writing.
    if (!$file = $this->dennis_term_manager_open_report($file_name)) {
      return;
    }

    $fp = fopen($file->uri, 'w');

    // Add Headings.
    $columns = array_merge($this->dennis_term_manager_default_columns(), ['error']);
    fputcsv($fp, $columns, $delimiter, '"');

    // Output resulting taxonomy.
    foreach ($this->operationList as $item) {
      $row = array();
      foreach ($columns as $key) {
        $row[] = $item->{$key};
      }
      fputcsv($fp, $row, $delimiter, '"');
    }
    fclose($fp);

    // Clear stat cache to get correct filesize.
    clearstatcache(FALSE, $file->uri);

    // Save managed file.
    $file->save();
  }

  /**
   * Iterator::rewind().
   */
  public function rewind() {
    $this->position = 0;
  }

  /**
   * Iterator::current().
   */
  public function current() {
    return $this->operationList[$this->position];
  }

  /**
   * Iterator::key().
   */
  public function key() {
    return $this->position;
  }

  /**
   * Iterator::next().
   */
  public function next() {
    ++$this->position;
  }

  /**
   * Iterator::valid().
   */
  function valid() {
    return isset($this->operationList[$this->position]);
  }

  /**
   * Countable::count().
   */
  function count() {
    return count($this->operationList);
  }



  /**
   * Opens a new report and return fid.
   *
   * @param $file_path
   *
   * @return bool|\Drupal\file\FileInterface|false
   */
  protected function dennis_term_manager_open_report($file_path) {
    // Create new managed file.
    if ($file = file_save_data('', $file_path, FileSystemInterface::EXISTS_RENAME)) {
      // Add file usage.
      $file_usage = \Drupal::service('file.usage');
      $file_usage->add($file, 'dennis_term_manager', 'dennis_term_manager_csv_file', 1);
      return $file;
    }
    else {
      \Drupal::messenger()->addMessage('Could not open %file', ['%file' => $file_path]);
      return FALSE;
    }
  }

  /**
   * CSV/TSV files should always have these columns.
   */
  protected function dennis_term_manager_default_columns() {
    return [
      'vocabulary_name',
      'term_name',
      'tid',
      'path',
      'node_count',
      'term_child_count',
      'parent_term_name',
      'action',
      'target_term_name',
      'target_tid',
      'target_vocabulary_name',
      'target_field',
      'new_name',
      'redirect',
    ];
  }
}
