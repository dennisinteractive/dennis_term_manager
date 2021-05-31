<?php

namespace Drupal\dennis_term_manager\Operations;

use Drupal\file\Entity\File;

/**
 * Interface TermManagerBuildInterface.
 *
 * @package Drupal\dennis_term_manager\Operations
 */
interface TermManagerBuildInterface {

  /**
   * Execute build using specified CSV file.
   *
   * @param \Drupal\file\Entity\File $file_path
   *   File entity represents csv file.
   *
   * @return array
   *   Term data array ready for the batch builder.
   */
  public function execute(File $file_path);

  /**
   * CSV/TSV files should always have these columns.
   */
  public function defaultColumns();

}
