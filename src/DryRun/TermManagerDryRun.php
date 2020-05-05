<?php

namespace Drupal\dennis_term_manager\DryRun;



use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\TermManagerTree;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\TermManagerOperationList;
use Drupal\dennis_term_manager\TermManagerOperationItem;


/**
 * @file TermManagerDryRun
 */
class TermManagerDryRun {

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var \Drupal\dennis_term_manager\TermManagerTree
   */
  protected $termManagerTree;

  /**
   * @var \Drupal\dennis_term_manager\TermManagerService
   */
  protected $termServiceManager;

  /**
   * @var TermManagerDryRunProcess
   */
  protected $termManagerDryRunProcess;

  /**
   * Term tree.
   * @var array
   */
  protected $termTree = [];

  /**
   * List of operations.
   * @var TermManagerOperationList
   */
  protected $operationList;

  static $DENNIS_TERM_MANAGER_ACTION_CREATE = 'create';
  static $DENNIS_TERM_MANAGER_ACTION_DELETE = 'delete';
  static $DENNIS_TERM_MANAGER_ACTION_MERGE= 'merge';
  static $DENNIS_TERM_MANAGER_ACTION_RENAME = 'rename';
  static $DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT = 'move parent';

  /**
   * Initialise dry run.
   *
   * TermManagerDryRun constructor.
   *
   * @param Messenger $messenger
   * @param TermManagerTree $termManagerTree
   * @param TermManagerService $termServiceManager
   */
  /***
   * TermManagerDryRun constructor.
   * @param Messenger $messenger
   * @param TermManagerTree $termManagerTree
   * @param TermManagerService $termServiceManager
   * @param TermManagerDryRunProcess $termManagerDryRunProcess
   */
  public function __construct(Messenger $messenger,
                              TermManagerTree $termManagerTree,
                              TermManagerService $termServiceManager,
                              TermManagerDryRunProcess $termManagerDryRunProcess) {
    $this->messenger = $messenger;
    $this->termManagerTree = $termManagerTree;
    $this->termServiceManager = $termServiceManager;
    $this->termManagerDryRunProcess = $termManagerDryRunProcess;
    $this->termManagerTree->buildTermTree();
    $this->operationList = new TermManagerOperationList();
  }



  /**
   * Execute dry run using specified TSV/CSV file.
   *
   * @param $file_path
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute($file_path) {
    if (empty($file_path)) {
      return;
    }

    // Get file info.
    $file_info = pathinfo($file_path);

    // Detect line endings.
    ini_set('auto_detect_line_endings',TRUE);

    if (($handle = fopen($file_path, "r")) !== FALSE) {
      $delimiter = $this->detectDelimiter(file_get_contents($file_path));
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        $operation = new TermManagerOperationItem();
        // Get list of operation columns.
        foreach ($this->dennis_term_manager_default_columns() as $column) {
          if (isset($columns[$column])) {
            $index = $columns[$column];
            try {
              $operation->{$column} = isset($data[$index]) ? trim($data[$index]) : '';
            }
            catch (\Exception $e) {
              $operation->error = $e->getMessage();
            }
          }
        }
        if (!empty($operation->action)) {
          // Only process operations with actions.
          $this->termManagerDryRunProcess->processOperation($operation);
        }
      }
    }

    // Display dry run errors to the end user.
    if ($errorList = $this->operationList->getErrorList()) {
      foreach ($errorList as $error) {
        $this->messenger->addError($error['message']);
      }
      $this->operationList->outputCSV($file_path, $delimiter);
    }
    // Output dry run errors in operation CSV.
    $this->outputCSV($file_path, $delimiter);
  }

  /**
   * Getter for operation List.
   */
  public function getOperationList() {
    return $this->operationList;
  }




  /**
   * Output dry run as CSV.
   *
   * @param $file_path
   * @param $delimiter
   */
  protected function outputCSV($file_path, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s', \Drupal::time()->getRequestTime());
    $file_name = preg_replace("/[.](.*)/", "-" . $date . "-dry_run.$1", $file_path);

    // Create managed file and open for writing.
    if (!$file = $this->termServiceManager->dennis_term_manager_open_report($file_name)) {
      return;
    }


    $fp = fopen($file->uri, 'w');

    // Add Headings.
    $columns = array_merge($this->dennis_term_manager_default_columns(), ['description']);
    fputcsv($fp, $columns, $delimiter, '"');

    // Output resulting taxonomy.
    foreach ($this->termTree as $vocabulary_name => $items) {
      foreach ($items as $term_name => $item_array) {
        foreach ($item_array as $item) {
          // Exclude terms that no longer exist.
          $exclude_operations = [
            self::$DENNIS_TERM_MANAGER_ACTION_DELETE,
            self::$DENNIS_TERM_MANAGER_ACTION_MERGE,
            self::$DENNIS_TERM_MANAGER_ACTION_RENAME,
          ];
          if (in_array($item->action, $exclude_operations)) {
            continue 1;
          }
          // Add term to report.
          $row = array();
          foreach ($columns as $key) {
            $row[] = $item->{$key};
          }
          fputcsv($fp, $row, $delimiter, '"');
        }
      }
    }
    fclose($fp);

    // Clear stat cache to get correct filesize.
    clearstatcache(FALSE, $file->uri);
    // Saved managed file.
    $file->save();
  }

  /**
   * Get existing taxonomy usage.
   *
   * @param $vocabs
   *    Array of vocabulary names, used to limit the results.
   * @return \Drupal\Core\Database\Query\Select
   */
  protected function dennis_term_manager_export_terms_query($vocabs = []) {

    $connection = Database::getConnection();

    $query = $connection->select('taxonomy_term_data', 't');

    // Term vocabulary and name.
    $query->addField('v', 'name', 'vocabulary_name');
    $query->addField('t', 'name', 'term_name');

    // Filter by vocabulary
    $machine_names = [];
    if (!empty($vocabs)) {
      foreach ($vocabs as $name) {
        $machine_names[] = $this->dennis_term_manager_machine_name($name);
      }
      $query->condition('v.machine_name', $machine_names, 'IN');
    }

    // Join alias.
    $query->leftJoin('url_alias', 'ua', "ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))");
    $query->addField('ua', 'alias', 'path');

    // TID and VID
    $query->addField('v', 'vid', 'vid');
    $query->addField('t', 'tid', 'tid');

    // Get node count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_index i WHERE i.tid = t.tid)', 'node_count');

    // Get child term count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_term_hierarchy h WHERE h.parent = t.tid)', 'term_child_count');

    // Parent term name.
    $query->addExpression('IF(p.name IS NULL, \'\', p.name)', 'parent_term_name');

    // Join on vocabulary of term.
    $query->leftJoin('taxonomy_vocabulary', 'v', 'v.vid = t.vid');

    // Parent information.
    $query->leftJoin('taxonomy_term_hierarchy', 'h', 'h.tid = t.tid');
    $query->leftJoin('taxonomy_term_data', 'p', 'p.tid = h.parent');
    $query->addField('p', 'tid', 'parent_tid');

    // Group by tid to get node counts for each term.
    $query->groupBy('t.tid');

    return $query;
  }






  /**
   * CSV/TSV files should always have these columns.
   */
  protected function dennis_term_manager_default_columns() {
    return array(
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
    );
  }


  /**
   * Helper to count delimiters and see what is the mostly used.
   *
   * @param $str
   * @param null $delimiter
   * @return mixed|null
   */
  protected function detectDelimiter($str, $delimiter = NULL) {
    // Get number of rows.
    preg_match_all("/\n/", $str, $matches);
    $rows = count($matches[0]);

    $cur_cnt = 0;
    foreach ($this->dennis_term_manager_get_delimiters() as $key => $value) {
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
  protected function dennis_term_manager_get_delimiters() {
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



