<?php

namespace Drupal\dennis_term_manager\FileSystem;

use Drupal\file\Entity\File;

interface TermManagerFileSystemInterface {

  /**
   * Retrieve and creates the files folder.
   */
  public function getFilesDir();

  /**
   * Get the path for the last uploaded CSV.
   *
   * @return mixed
   */
  public function getPreviousUpload();

  /**
   * Save the processed file.
   *
   * @param File $file
   * @return File
   */
  public function saveFile(File $file);

}
