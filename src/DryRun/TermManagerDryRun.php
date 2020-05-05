<?php

namespace Drupal\dennis_term_manager\DryRun;


use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\TermManagerTree;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;
use Drupal\dennis_term_manager\Operations\TermManagerOperationItem;


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
      $delimiter = $this->operationList->detectDelimiter(file_get_contents($file_path));
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        $operation = new TermManagerOperationItem();
        // Get list of operation columns.

        foreach ($this->termManagerTree->defaultColumns() as $column) {
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
      $this->termServiceManager->outputCSV($file_path, $delimiter);
    }
    // Output dry run errors in operation CSV.
    $this->termServiceManager->outputCSV($file_path, $delimiter);
  }

  /**
   * Getter for operation List.
   */
  public function getOperationList() {
    return $this->operationList;
  }
}



