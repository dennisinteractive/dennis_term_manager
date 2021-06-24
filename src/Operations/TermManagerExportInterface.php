<?php

namespace Drupal\dennis_term_manager\Operations;

/**
 * Interface TermManagerExportInterface.
 *
 * @package Drupal\dennis_term_manager\Operations
 */
interface TermManagerExportInterface {

  /**
   * Export the csv with all the term data from the site.
   */
  public function export();

  /**
   * The query for the term export.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query object.
   */
  public function query();

  /**
   * The columns for the CSV.
   *
   * @return array
   *   Array of required columns.
   */
  public function getColumns();

  /**
   * Get the index page value.
   *
   * Transforms 1 or 0 to Y or N.
   *
   * @param object $row
   *   The data from the row.
   *
   * @return string
   *   The index page value, Y or N.
   */
  public function getIndexPage(object $row);

  /**
   * Get the count of the nodes tagged with children of the term.
   *
   * @param mixed $tid
   *   The term id.
   *
   * @return mixed
   *   The results of the query ie the count.
   */
  public function getNodeCountWithChildren($tid);

}
