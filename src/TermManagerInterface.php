<?php

namespace Drupal\dennis_term_manager;

use Drupal\field\Entity\FieldConfig;

/**
 * Interface TermManagerInterface.
 *
 * @package Drupal\dennis_term_manager
 */
interface TermManagerInterface {

  /**
   * Get a term of the given entity reference field.
   *
   * @param \Drupal\field\Entity\FieldConfig $node_config
   *   Node field config.
   * @param string $field
   *   Node field.
   * @param string $value
   *   Term name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\taxonomy\Entity\Term|mixed
   *   Referenced term.
   */
  public function getTermFromNodeField(FieldConfig $node_config, $field, $value);

  /**
   * Get a new or existing term.
   *
   * @param string $term_name
   *   Term name.
   * @param string $vocab_name
   *   Vocabulary name.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Taxonomy term entity.
   */
  public function getTerm($term_name, $vocab_name);

}
