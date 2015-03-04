<?php
/**
 * TermManagerProgressList
 */
class TermManagerProgressList implements Iterator, Countable {
  /**
   * Iterator position.
   * @var integer
   */
  private $position = 0;

  /**
   * List of OperationItem.
   * @var array
   */
  protected $progressList = array();

  /**
   * Initialise current processes.
   */
  public function __construct() {
    $this->position = 0;

    // Load list of current processes.
    $in_progress = variable_get('dennis_term_manager_in_progress', array());
    foreach (array_keys($in_progress) as $fid) {
      try {
        $progress_item = new TermManagerProgressItem($fid);
        $this->progressList[] = $progress_item;
      }
      catch (Exception $e) {
        drupal_set_message($e->getMessage());
      }
    }
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
    return $this->progressList[$this->position];
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
    return isset($this->progressList[$this->position]);
  }

  /**
   * Countable::count().
   */
  function count() {
    return count($this->progressList);
  }
}
