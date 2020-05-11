<?php


namespace Drupal\dennis_term_manager\Progress;


use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Config\ConfigFactoryInterface;


/**
 * Class TermManagerProgressList
 *
 * @package Drupal\dennis_term_manager\Progress
 */
class TermManagerProgressList implements \Iterator, \Countable {


  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var TermManagerProgressItem
   */
  protected $termManagerProgressItem;

  /**
   * Iterator position.
   * @var integer
   */
  private $position = 0;

  /**
   * List of ProgressItem.
   * @var array
   */
  protected $progressList = [];

  /**
   * Initialise current processes.
   *
   * TermManagerProgressList constructor.
   *
   * @param ConfigFactoryInterface $configFactory
   * @param Messenger $messenger
   * @param TermManagerProgressItem $termManagerProgressItem
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              Messenger $messenger,
                              TermManagerProgressItem $termManagerProgressItem) {
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
    $this->termManagerProgressItem = $termManagerProgressItem;

    $this->position = 0;

    $in_progress =  $this->configFactory->get('dennis_term_manager')->get('in_progress', []);

    if (!empty($in_progress)) {
      foreach (array_keys($in_progress) as $fid) {
        try {
          $progress_item = $this->termManagerProgressItem;
          $progress_item->init($fid);
          $this->progressList[] = $progress_item;
        }
        catch (\Exception $e) {
          $this->messenger->addMessage($e->getMessage());
        }
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
