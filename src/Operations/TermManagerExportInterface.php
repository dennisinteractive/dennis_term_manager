<?php

namespace Drupal\dennis_term_manager\Operations;

/**
 * Interface TermManagerExportInterface
 *
 * @package Drupal\dennis_term_manager\Operations
 */
interface TermManagerExportInterface {

  /**
   * Export the csv with all the term data from the site.
   */
  public function export();

}
