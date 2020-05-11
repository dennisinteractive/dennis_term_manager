<?php

namespace Drupal\dennis_term_manager\DryRun;

use Drupal\file\Entity\File;
use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\TermManagerTree;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;

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
   * Term tree.
   * @var array
   */
  protected $termTree = [];

  /**
   * List of operations.
   * @var TermManagerOperationList
   */
  protected $operationList;


  /**
   * Initialise dry run.
   *
   * TermManagerDryRun constructor.
   *
   * @param Messenger $messenger
   * @param TermManagerTree $termManagerTree
   * @param TermManagerService $termServiceManager
   */
  public function __construct(Messenger $messenger,
                              TermManagerTree $termManagerTree,
                              TermManagerService $termServiceManager) {
    $this->messenger = $messenger;
    $this->termManagerTree = $termManagerTree;
    $this->termServiceManager = $termServiceManager;
    $this->operationList = new TermManagerOperationList();
  }



  /**
   * Execute dry run using specified TSV/CSV file.
   *
   * @param File $file_path
   * @return array|void
   */
  public function execute(File $file_path) {
    if (empty($file_path)) {
      return;
    }

    $term_data = [];

    // Detect line endings.
    ini_set('auto_detect_line_endings',TRUE);

    if (($handle = fopen($file_path->getFileUri(), "r")) !== FALSE) {
      $delimiter = $this->operationList->detectDelimiter(file_get_contents($file_path->getFileUri()));
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      $i = 0;
      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        // Get list of operation columns.
        foreach ($this->termManagerTree->defaultColumns() as $column) {
          if (isset($columns[$column])) {
            $index = $columns[$column];
            try {
              $term_data[$i][$column] = trim($data[$index]);
            }
            catch (\Exception $e) {

              $this->messenger->addError($e->getMessage());



            }
          }
        }

        $i++;
      }
    }

    return $term_data;
    /**
    // Display dry run errors to the end user.
    if ($errorList = $this->operationList->getErrorList()) {
      foreach ($errorList as $error) {
        $this->messenger->addError($error['message']);
      }
      $this->termServiceManager->outputCSV($file_path, $this->operationList, $delimiter);
    }
    // Output dry run errors in operation CSV.
    $this->termServiceManager->outputCSV($file_path, $this->operationList, $delimiter);
     */
  }


}



