<?php

namespace Drupal\dennis_term_manager;

use Drupal\field\Entity\FieldConfig;

/**
 * Interface TermManagerInterface
 *
 * @package Drupal\dennis_term_manager
 */
interface TermManagerInterface {

  /**
   * @param FieldConfig $node_config
   * @param array $term_data
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function getTermFromNodeField(FieldConfig $node_config, array $term_data);

  /**
   * Get a new or existing term
   *
   * @param $term_name
   * @param $vocab_name
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function getTerm($term_name, $vocab_name);

}

