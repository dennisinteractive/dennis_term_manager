<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * TermManagerProgressItem
 */
class TermManagerProgressItem {

  /**
   * Fid of file being processed.
   * @var int
   */
  protected $fid;

  /**
   * Stores item data.
   * @var array
   */
  protected $data = array();

  /**
   * Initialise progress item.
   *
   * TermManagerProgressItem constructor.
   *
   * @param int $fid
   * @throws \Exception
   */
  public function __construct($fid) {
    // Load data.
    if (empty($fid)) {
      throw new \Exception('File ID must be provided');
    }
    // Set the fid and store in data.
    $this->fid = $fid;
    $this->load();
  }

  /**
   * Load current progress data.
   */
  public function load() {
    //TODO - fix the variable thingy
  //  $in_progress = variable_get('dennis_term_manager_in_progress', array());

    $config = \Drupal::config('dennis_term_manager');
    $in_progress = $config->get('in_progress');
    if (!empty($in_progress[$this->fid]) && is_array($in_progress[$this->fid])) {
      foreach ($in_progress[$this->fid] as $key => $value) {
        // Load progress data from database.
        $this->setData($key, $value);
      }
    }
  }

  /**
   * Delete all queued items for the current file being processed.
   */
  public function deleteQueuedItems() {
    //TODO - fix the variable thingy
   // $progress = variable_get('dennis_term_manager_in_progress', array());
    $progress = [];

    if (!isset($progress[$this->fid])) {
      return;
    }
    $item = $progress[$this->fid];
    $from = $item['offset_queue_id'];
    $to = $item['final_queue_id'];
/*
    // Clear queue.
    db_delete('queue')
      ->condition('name', 'dennis_term_manager_queue')
      ->condition('item_id', array($from, $to), 'BETWEEN')
      ->execute();
 //   drupal_set_message('The queue has been deleted.');
*/
    $connection = Database::getConnection();

    $connection->delete('queue')
      ->condition('name', 'dennis_term_manager_queue')
      ->condition('item_id', [$from, $to], 'BETWEEN')
      ->execute();
    \Drupal::messenger()->addMessage('The queue has been deleted.');
  }

  /**
   * @deprecated
   *
   * @throws \Exception
   */
  public function delete() {
    $this->deleteProgressData();
  }

  /**
   * Delete current progress data.
   */
  public function deleteProgressData() {

    $config = \Drupal::config('dennis_term_manager');
    $in_progress = $config->get('in_progress');


    if (isset($in_progress[$this->fid])) {
      unset($in_progress[$this->fid]);
     // variable_set('dennis_term_manager_in_progress', $in_progress);

      $config->set('in_progress', $in_progress);
    }
    else {
      throw new \Exception(t('File !fid does not exist', array(
        '!fid' => $this->fid,
      )));
    }
  }

  /**
   * Save current progress data.
   */
  public function save() {
   // $in_progress = variable_get('dennis_term_manager_in_progress', array());

    $config = \Drupal::config('dennis_term_manager');
    $in_progress = $config->get('in_progress');

    $in_progress[$this->fid]['fid'] = $this->fid;
    foreach ($this->data as $key => $value) {
      $in_progress[$this->fid][$key] = $value;
    }
  //  variable_set('dennis_term_manager_in_progress', $in_progress);
    $config->set('in_progress', $in_progress);
  }

  /**
   * Set fid of progress report.
   *
   * @param $report_fid
   */
  public function setReportFid($report_fid) {
    $this->setData('report_fid', $report_fid);
  }

  /**
   * Set the final queue ID to be last item in queue.
   */
  public function setFinalQueueId() {
    $this->setData('final_queue_id', $this->getLastQueueId());
  }

  /**
   * Set the offset queue ID to be last item in queue.
   */
  public function setOffsetQueueId() {
    $this->setData('offset_queue_id', $this->getLastQueueId());
  }

  /**
   * Get the qid of the last queue item.
   *
   * - Returns 0 if not available.
   */
  public function getLastQueueId() {

    /*
    $query = db_query_range('SELECT item_id
      FROM {queue} q
      WHERE
        expire = 0 AND
        name = :name
      ORDER BY created DESC',
      0, 1,
      array(':name' => 'dennis_term_manager_queue')
    );
*/
    $connection = Database::getConnection();

    $query = $connection->queryRange('SELECT item_id
      FROM {queue} q
      WHERE
        expire = 0 AND
        name = :name
      ORDER BY created DESC', 0, 1, [':name' => 'dennis_term_manager_queue']);

    if ($item = $query->fetchObject()) {
      return $item->item_id;
    }

    return 0;
  }

  /**
   * Get the number of items left to process.
   */
  public function getQueueCount() {
    $offset = $this->getData('offset_queue_id');
    $final = $this->getData('final_queue_id');

    // If there is no final queue ID we cannot get a count.
    if (empty($final)) {
      return 0;
    }

    /**
    $query = db_query('SELECT COUNT(item_id) AS item_count
      FROM {queue} q
      WHERE
        expire = 0 AND
        name = :name AND
        item_id > :offset AND
        item_id <= :final',
      array(
        ':name' => 'dennis_term_manager_queue',
        ':offset' => $offset,
        ':final' => $final,
      )
    );

*/

    $connection = Database::getConnection();
    // Set the first entry to have the admin as author.
    $query = $connection->query('SELECT COUNT(item_id) AS item_count
      FROM {queue} q
      WHERE
        expire = 0 AND
        name = :name AND
        item_id > :offset AND
        item_id <= :final', [
      ':name' => 'dennis_term_manager_queue',
      ':offset' => $offset,
      ':final' => $final,
    ]);

    if ($row = $query->fetchObject()) {
      return $row->item_count;
    }

    return 0;
  }

  /**
   * Display status using drupal_set_message().
   */
  public function displayStatus() {
    $report_link = '';
    if ($report_fid = $this->getData('report_fid')) {
    //  $report_file = file_load($report_fid);

      $report_file = \Drupal\file\Entity\File::load($report_fid);
      if (isset($report_file->uri)) {
     //   $report_link = l('View report', file_create_url($report_file->uri)) . ' &raquo;';

        $report_link = Link::fromTextAndUrl(t('View report'), Url::fromUri('internal:/' . $report_file->uri . ' &raquo;'))->toString();
      }
    }
    $message = t('There is currently an active process with !item_count items left to process. !report_link !delete_queue', [
      '!report_link' => $report_link,
      '!delete_queue' =>  Link::fromTextAndUrl(t('Delete queue'), Url::fromUri('admin/structure/taxonomy/term_manager/' . $this->fid . '/delete'))->toString(),
      '!item_count' => $this->getQueueCount(),
    ]);
  //  drupal_set_message($message, 'status');

    \Drupal::messenger()->addStatus($message);
  }

  /**
   * Get data by key.
   *
   * @param $key
   */
  protected function getData($key) {
    if (isset($this->data[$key])) {
      return $this->data[$key];
    }
  }

  /**
   * Wrapper to only allow certain data keys.
   *
   * @param $key
   * @param $value
   */
  protected function setData($key, $value) {
    $data_keys = [
      'report_fid',
      'offset_queue_id',
      'final_queue_id',
    ];
    if (in_array($key, $data_keys)) {
      $this->data[$key] = $value;
    }
  }
}
