<?php

namespace Drupal\dennis_term_manager\Operations;

use Drupal\file\Entity\File;
use Drupal\Core\Messenger\Messenger;

/**
 * Class TermManagerBuild.
 *
 * @package Drupal\dennis_term_manager\Operations
 */
class TermManagerBuild implements TermManagerBuildInterface {

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * List of operations.
   *
   * @var TermManagerOperationList
   */
  protected $operationList;

  /**
   * TermManagerBuild constructor.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger service.
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;
    $this->operationList = new TermManagerOperationList();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(File $file_path) {
    if (empty($file_path)) {
      return;
    }

    $term_data = [];

    // Detect line endings.
    ini_set('auto_detect_line_endings', TRUE);

    if (($handle = fopen($file_path->getFileUri(), "r")) !== FALSE) {

      $delimiter = $this->operationList->detectDelimiter(file_get_contents($file_path->getFileUri()));
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      $i = 0;
      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        // Get list of operation columns.
        foreach ($this->defaultColumns() as $column) {
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
  }

  /**
   * {@inheritdoc}
   */
  public function defaultColumns() {
    return [
      'node',
      'field',
      'value',
    ];
  }

}
