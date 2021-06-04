<?php

namespace Drupal\dennis_term_manager\FileSystem;

use Drupal\file\Entity\File;

/**
 * Term manager file system interface.
 */
interface TermManagerFileSystemInterface {

  /**
   * Retrieve and creates the files folder.
   */
  public function getFilesDir();

  /**
   * Get the path for the last uploaded CSV.
   *
   * @return mixed
   *   File URI.
   */
  public function getPreviousUpload();

  /**
   * Save the processed file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File to process.
   *
   * @return \Drupal\file\Entity\File
   *   Processed file.
   */
  public function saveFile(File $file);

}
