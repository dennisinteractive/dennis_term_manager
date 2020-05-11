<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;
use Drupal\Component\Datetime\Time;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
Use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;

/**
 * Class TermManagerService
 *
 * @package Drupal\dennis_term_manager
 */
class TermManagerService {


  use StringTranslationTrait;

  /**
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  public $fileUsage;

  /**
   * @var FileSystem
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var Time
   */
  protected $time;

  /**
   * @var TermManagerTree
   */
  protected $termManagerTree;


  public static $DENNIS_TERM_MANAGER_PRIVATE_FOLDER = 'private://term_manager';

  public static $DENNIS_TERM_MANAGER_PUBLIC_FOLDER = 'public://term_manager';


  /**
   * TermManagerService constructor.
   *
   * @param DatabaseFileUsageBackend $fileUsage
   * @param FileSystem $fileSystem
   * @param Messenger $messenger
   * @param LoggerChannelFactoryInterface $loggerFactory
   * @param Time $time
   * @param TermManagerTree $termManagerTree
   */
  public function __construct(DatabaseFileUsageBackend $fileUsage,
                              FileSystem $fileSystem,
                              Messenger $messenger,
                              LoggerChannelFactoryInterface $loggerFactory,
                              Time $time,
                              TermManagerTree $termManagerTree) {
    $this->fileUsage = $fileUsage;
    $this->fileSystem = $fileSystem;
    $this->messenger = $messenger;
    $this->logger = $loggerFactory->get('dennis_term_manager');
    $this->time = $time;
    $this->termManagerTree = $termManagerTree;
  }


  /**
   * Helper to retrieve the files folder.
   */
  public function getFilesDir() {
    // Store CSV/TSV files and reports in private file location if available.
    if ($this->fileSystem->realpath("private://")) {
      $location = self::$DENNIS_TERM_MANAGER_PRIVATE_FOLDER;
    }
    else {
      $location = self::$DENNIS_TERM_MANAGER_PUBLIC_FOLDER;
      // Warn user that files will be publicly accessible.
      $config_link = Link::fromTextAndUrl($this->t('Please configure the private file system path to store report privately'),
        Url::fromUri('internal:/admin/config/media/file-system'))->toString();
      $warning_message = $this->t('Files will be stored in the public file directory. @config_link', ['@config_link' => $config_link]);

      $this->messenger->addWarning($warning_message);
    }

    // Test if folder exists and try to create it if necessary.
    if (!is_dir($location) && !$this->fileSystem->mkdir($location, 0766, TRUE)) {
      $this->logger->warning('The directory %directory does not exist and could not be created.', ['%directory' => $location]);
    }
    if (is_dir($location) && !is_writable($location) && !$this->fileSystem->chmod($location)) {
      // If the directory is not writable and cannot be made so.
      $this->logger->warning('The directory %directory exists but is not writable and could not be made writable.', ['%directory' => $location]);
    }
    elseif (is_dir($location)) {
      // Create private .htaccess file.
      \Drupal\Component\FileSecurity\FileSecurity::writeHtaccess($location);
      return $location;
    }

    throw new \Exception($this->t('Error trying to copy files to @name folder. Make sure the folder exists and you have writting permission.', [
      '@name' => $location,
    ]));
  }

  /**
   * Output queue items as CSV.
   *
   * @param File $file_path
   * @param $operation_list
   * @param $delimiter
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function outputCSV(File $file_path, $operation_list, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s', $this->time->getRequestTime());
    $file_name = $file_path->getFileUri();

    // Create managed file and open for writing.
    if (!$file = $this->openReport($file_name)) {
      return;
    }

    $fp = fopen($file->getFileUri(), 'w');

    // Add Headings.
    $columns = array_merge($this->termManagerTree->defaultColumns(), ['error']);
    fputcsv($fp, $columns, $delimiter, '"');

    // Output resulting taxonomy.
    foreach ($operation_list as $item) {
      $row = [];
      foreach ($columns as $key) {
        $row[] = $item->{$key};
      }
      fputcsv($fp, $row, $delimiter, '"');
    }
    fclose($fp);
    // Clear stat cache to get correct filesize.
    clearstatcache(FALSE, $file->getFileUri());
    // Save managed file.
    $file->save();
  }

  /**
   * Opens a new report and return fid.
   *
   * @param $file_path
   *
   * @return bool|\Drupal\file\FileInterface|false
   */
  public function openReport($file_path) {
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
