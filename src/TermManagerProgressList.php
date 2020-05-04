<?php


namespace Drupal\dennis_term_manager;


/**
 * TermManagerProgressList
 */
class TermManagerProgressList implements \Iterator, \Countable {
  /**
   * Iterator position.
   * @var integer
   */
  private $position = 0;

  /**
   * List of ProgressItem.
   * @var array
   */
  protected $progressList = array();

  /**
   * Initialise current processes.
   *
   * TermManagerProgressList constructor.
   *
   * @throws \Exception
   */
  public function __construct() {
    $this->position = 0;


    //TODO - fix the variable thingy
    // Load list of current processes.
  //  $in_progress = variable_get('dennis_term_manager_in_progress', []);

    $config = \Drupal::config('dennis_term_manager');
    $in_progress = $config->get('in_progress');


    //$in_progress = [];





    foreach (array_keys($in_progress) as $fid) {
      try {
        $progress_item = new TermManagerProgressItem($fid);
        $this->progressList[] = $progress_item;
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addMessage($e->getMessage());
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
