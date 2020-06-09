<?php

namespace Drupal\dennis_term_manager\Operations;

use Drupal\file\Entity\File;

/**
 * Interface TermManagerBuildInterface
 *
 * @package Drupal\dennis_term_manager\Operations
 */
interface TermManagerBuildInterface {

  /**
   * Execute build using specified CSV file.
   *
   * @param File $file_path
   * @return array
   */
  public function execute(File $file_path);

  /**
   * CSV/TSV files should always have these columns.
   */
  public function defaultColumns();
}
