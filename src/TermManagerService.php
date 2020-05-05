<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\File\FileSystem;
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
   * @var FileSystem
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;




  public static $DENNIS_TERM_MANAGER_PRIVATE_FOLDER = 'private://term_manager';

  public static $DENNIS_TERM_MANAGER_PUBLIC_FOLDER = 'public://term_manager';


  /**
   * TermManagerService constructor.
   *
   * @param DatabaseFileUsageBackend $fileUsage
   * @param FileSystem $fileSystem
   * @param Messenger $messenger
   */
  public function __construct(
    DatabaseFileUsageBackend $fileUsage,
    FileSystem $fileSystem,
    Messenger $messenger) {
    $this->fileUsage = $fileUsage;
    $this->fileSystem = $fileSystem;
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



  /**
   * Helper to retrieve the files folder.
   */
  public function dennis_term_manager_get_files_folder() {
    // Store CSV/TSV files and reports in private file location if available.
    if (\Drupal::config('file_private_path')) {
      $location = self::$DENNIS_TERM_MANAGER_PRIVATE_FOLDER;
    }
    else {
      $location = self::$DENNIS_TERM_MANAGER_PUBLIC_FOLDER;
      // Warn user that files will be publicly accessible.
      $config_link = l(
        'Please configure the private file system path to store report privately',
        'admin/config/media/file-system'
      );
      $warning_message = t('Files will be stored in the public file directory. !config_link', array('!config_link' => $config_link));

      $this->messenger->addWarning($warning_message);
    }


    $file_system = \Drupal::service('file_system');
    // Test if folder exists and try to create it if necessary.
    if (!is_dir($location) && !$file_system->mkdir($location, NULL, TRUE)) {
      \Drupal::logger('file system')->warning('The directory %directory does not exist and could not be created.', ['%directory' => $location]);
    }
    if (is_dir($location) && !is_writable($location) && !$this->fileSystem->chmod($location)) {
      // If the directory is not writable and cannot be made so.
      \Drupal::logger('file system')->warning('The directory %directory exists but is not writable and could not be made writable.', ['%directory' => $location]);
    }
    elseif (is_dir($location)) {
      // Create private .htaccess file.
      \Drupal\Component\FileSecurity\FileSecurity::writeHtaccess($location);
      return $location;
    }

    throw new \Exception(t('Error trying to copy files to !name folder. Make sure the folder exists and you have writting permission.', array(
      '!name' => $location,
    )));
  }

}
