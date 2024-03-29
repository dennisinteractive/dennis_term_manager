<?php

namespace Drupal\dennis_term_manager\FileSystem;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;

/**
 * Class TermManagerFileSystem.
 *
 * @package Drupal\dennis_term_manager
 */
class TermManagerFileSystem implements TermManagerFileSystemInterface {

  use StringTranslationTrait;

  // phpcs:ignore
  public static $DENNIS_TERM_MANAGER_PRIVATE_FOLDER = 'private://term_manager';

  // phpcs:ignore
  public static $DENNIS_TERM_MANAGER_PUBLIC_FOLDER = 'public://term_manager';

  /**
   * File usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  public $fileUsage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * TermManagerFileSystem constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection instance.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $fileUsage
   *   File usage service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger factory.
   */
  public function __construct(Connection $connection,
                              DatabaseFileUsageBackend $fileUsage,
                              FileSystemInterface $fileSystem,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->connection = $connection;
    $this->fileUsage = $fileUsage;
    $this->fileSystem = $fileSystem;
    $this->logger = $loggerFactory->get('dennis_term_manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilesDir(): string {
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
      FileSecurity::writeHtaccess($location);
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
      ->range(0, 1)
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function saveFile(File $file): File {
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
