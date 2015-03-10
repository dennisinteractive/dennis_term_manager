<?php
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
   * @param int $fid
   */
  public function __construct($fid) {
    // Load data.
    if (empty($fid)) {
      throw new Exception('File ID must be provided');
    }
    // Set the fid and store in data.
    $this->fid = $fid;
    $this->load();
  }

  /**
   * Load current progress data.
   */
  public function load() {
    $in_progress = variable_get('dennis_term_manager_in_progress', array());
    if (!empty($in_progress[$this->fid]) && is_array($in_progress[$this->fid])) {
      foreach ($in_progress[$this->fid] as $key => $value) {
        // Load progress data from database.
        $this->setData($key, $value);
      }
    }
  }

  /**
   * Delete current progress data.
   */
  public function delete() {
    $in_progress = variable_get('dennis_term_manager_in_progress', array());
    if (isset($in_progress[$this->fid])) {
      unset($in_progress[$this->fid]);
      variable_set('dennis_term_manager_in_progress', $in_progress);
    }
    else {
      throw new Exception(t('File !fid does not exist', array(
        '!fid' => $this->fid,
      )));
    }
  }

  /**
   * Save current progress data.
   */
  public function save() {
    $in_progress = variable_get('dennis_term_manager_in_progress', array());
    $in_progress[$this->fid]['fid'] = $this->fid;
    foreach ($this->data as $key => $value) {
      $in_progress[$this->fid][$key] = $value;
    }
    variable_set('dennis_term_manager_in_progress', $in_progress);
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
    $query = db_query_range('SELECT item_id
      FROM {queue} q
      WHERE
        expire = 0 AND
        name = :name
      ORDER BY created DESC',
      0, 1,
      array(':name' => 'dennis_term_manager_queue')
    );
  
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

    if ($row = $query->fetchObject()) {
      return $row->item_count;
    }

    return 0;
  }

  /**
   * Display status using drupal_set_message().
   */
  public function displayStatus() {
    if ($report_fid = $this->getData('report_fid')) {
      $report_file = file_load($report_fid);
      $report_link = l('View report', file_create_url($report_file->uri)) . ' &raquo;';
    }
    else {
      $report_link = '';
    }
    $message = t('There is currently an active process with !item_count items left to process. !report_link', array(
      '!report_link' => $report_link,
      '!item_count' => $this->getQueueCount(),
    ));
    drupal_set_message($message, 'status');
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
    $data_keys = array(
      'report_fid',
      'offset_queue_id',
      'final_queue_id',
    );
    if (in_array($key, $data_keys)) {
      $this->data[$key] = $value;
    }
  }
}
