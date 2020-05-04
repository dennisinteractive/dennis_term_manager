<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Messenger\Messenger;
Use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;


/**
 * Class TermManagerService
 *
 * @package Drupal\dennis_term_manager
 */
class TermManagerService {

  /**
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected $fileUsage;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * TermManagerService constructor.
   *
   * @param DatabaseFileUsageBackend $fileUsage
   * @param Messenger $messenger
   */
  public function __construct(
    DatabaseFileUsageBackend $fileUsage,
    Messenger $messenger) {
    $this->fileUsage = $fileUsage;
    $this->messenger = $messenger;
  }

  /**
   * Opens a new report and return fid.
   *
   * @param $file_path
   *
   * @return bool|\Drupal\file\FileInterface|false
   */
  public function dennis_term_manager_open_report($file_path) {
    // Create new managed file.
    if ($file = file_save_data('', $file_path, FileSystemInterface::EXISTS_RENAME)) {
      // Add file usage.
      $this->fileUsage->add($file, 'dennis_term_manager', 'dennis_term_manager_csv_file', 1);
      return $file;
    }
    else {
      $this->messenger->addMessage('Could not open %file', ['%file' => $file_path]);
      return FALSE;
    }
  }
}
