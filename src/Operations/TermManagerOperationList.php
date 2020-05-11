<?php

namespace Drupal\dennis_term_manager\Operations;


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
   */
  public function add($operationItem) {
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
   * Helper to count delimiters and see what is the mostly used.
   *
   * @param $str
   * @param null $delimiter
   * @return mixed|null
   */
  public function detectDelimiter($str, $delimiter = NULL) {
    // Get number of rows.
    preg_match_all("/\n/", $str, $matches);
    $rows = count($matches[0]);

    $cur_cnt = 0;
    foreach ($this->getDelimiters() as $key => $value) {
      preg_match_all("/\\$value/", $str, $matches);
      $count = count($matches[0]);

      if ($count > $cur_cnt) {
        $cur_cnt = $count;

        // Only use this delimiter if it happens at least once per row.
        if ($count > $rows) {
          $delimiter = $value;
        }
      }
    }
    return $delimiter;
  }

  /**
   * Helper to return list of delimiters.
   */
  public function getDelimiters() {
    return [
      'comma' => ",",
      'semicolon' => ";",
      'tab' => "\t",
      'pipe' => "|",
      'colon' => ":",
      'space' => " ",
    ];
  }
}
