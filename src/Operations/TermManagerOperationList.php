<?php

namespace Drupal\dennis_term_manager\Operations;

/**
 * Class TermManagerOperationList.
 *
 * @package Drupal\dennis_term_manager\Operations
 */
class TermManagerOperationList {

  /**
   * List of OperationItem.
   *
   * @var array
   */
  protected $operationList = [];

  /**
   * List of errors.
   *
   * @var array
   */
  protected $errorList = [];

  /**
   * Count delimiters and see what is the mostly used.
   *
   * @param string $str
   *   Input string.
   * @param string|null $delimiter
   *   Predefined delimiter. Empty by the default.
   *
   * @return mixed|null
   *   Detected delimiter based on the content of the input string.
   */
  public function detectDelimiter($str, $delimiter = NULL) {
    // Get number of rows.
    preg_match_all("/\n/", $str, $matches);
    $rows = count($matches[0]);

    $cur_cnt = 0;
    foreach ($this->getDelimiters() as $key => $value) {
      preg_match_all("/\\$value/", $str, $matches);
      $count = count($matches[0]);

      if ($count > $cur_cnt) {
        $cur_cnt = $count;

        // Only use this delimiter if it happens at least once per row.
        if ($count > $rows) {
          $delimiter = $value;
        }
      }
    }
    return $delimiter;
  }

  /**
   * Return list of delimiters.
   */
  protected function getDelimiters() {
    return [
      'comma' => ",",
      'semicolon' => ";",
      'tab' => "\t",
      'pipe' => "|",
      'colon' => ":",
      'space' => " ",
    ];
  }

}
