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
   * Get a term of the given entity reference field
   *
   * @param FieldConfig $node_config
   * @param $field
   * @param $value
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\taxonomy\Entity\Term|mixed
   */
  public function getTermFromNodeField(FieldConfig $node_config, $field, $value);

  /**
   * Get a new or existing term
   *
   * @param $term_name
   * @param $vocab_name
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function getTerm($term_name, $vocab_name);

}

