<?php
/**
 * @file TermManagerOperationList
 */
class TermManagerOperationList implements Iterator, Countable  {
  /**
   * Iterator position.
   * @var integer
   */
  private $position = 0;

  /**
   * List of OperationItem.
   * @var array
   */
  protected $operationList = array();

  /**
   * List of errors.
   * @var array
   */
  protected $errorList = array();

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
  public function outputCSV($file_path, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s');
    $file_name = preg_replace("/[.](.*)/", "-" . $date . "-errors.$1", $file_path);
    $fp = fopen($file_name, 'w');
    $columns = array_merge(dennis_term_manager_default_columns(), array('error'));
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

    // Create managed file to show in report list.
    $file = new stdClass();
    $file->filename = basename($file_name);
    $file->status = 1;
    $file->uri = $file_name;
    file_save($file);
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
}
