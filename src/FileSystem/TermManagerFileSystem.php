<?php

namespace Drupal\dennis_term_manager\FileSystem;

use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;


/**
 * Class TermManagerFileSystem
 *
 * @package Drupal\dennis_term_manager
 */
class TermManagerFileSystem implements TermManagerFileSystemInterface {


  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  public $fileUsage;

  /**
   * @var FileSystem
   */
  protected $fileSystem;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;


  public static $DENNIS_TERM_MANAGER_PRIVATE_FOLDER = 'private://term_manager';

  public static $DENNIS_TERM_MANAGER_PUBLIC_FOLDER = 'public://term_manager';

  /**
   * TermManagerFileSystem constructor.
   *
   * @param Connection $connection
   * @param DatabaseFileUsageBackend $fileUsage
   * @param FileSystem $fileSystem
   * @param LoggerChannelFactoryInterface $loggerFactory
   */
  public function __construct(Connection $connection,
                              DatabaseFileUsageBackend $fileUsage,
                              FileSystem $fileSystem,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->connection = $connection;
    $this->fileUsage = $fileUsage;
    $this->fileSystem = $fileSystem;
    $this->logger = $loggerFactory->get('dennis_term_manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilesDir() {
    // Store CSV/TSV files and reports in private file location if available.
    if ($this->fileSystem->realpath("private://")) {
      $location = self::$DENNIS_TERM_MANAGER_PRIVATE_FOLDER;
    }
    else {
      $location = self::$DENNIS_TERM_MANAGER_PUBLIC_FOLDER;
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

    throw new FileWriteException($this->t('Error trying to copy files to @name folder. Make sure the folder exists and you have writing permission.', [
      '@name' => $location,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousUpload() {
    $query = $this->connection->select('file_managed', 'fm')
      ->fields('fm', ['uri']);
    $query->join('file_usage', 'fu', "fm.fid = fu.fid");
    return $query->condition('fu.module', 'dennis_term_manager')
      ->orderBy('fu.fid', 'DESC')
      ->range(0,1)
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function saveFile(File $file) {
    if (is_object($file)) {
      $this->fileUsage->add($file, 'dennis_term_manager', 'dennis_term_manager_csv_file', 1);
    }
    else {
      throw new FileWriteException($this->t('!file_name cannot be saved', [
        '!file_name' => $file->getFileUri(),
      ]));
    }
    return $file;
  }
}
