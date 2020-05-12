<?php

namespace Drupal\dennis_term_manager;

use \Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldConfig;
use \Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldStorageConfig;


/**
 * Interface TermNodeManagerInterface
 *
 * @package Drupal\dennis_term_manager
 */
interface TermNodeManagerInterface {

  /**
   * Get the field settings for the given node.
   *
   * @param $term
   * @return FieldStorageConfig
   */
  public function getFieldSettings($term);

  /**
   * Update the node with the given term.
   *
   * @param EntityInterface $node
   * @param FieldConfig $node_config
   * @param FieldStorageConfig $field_info
   * @param array $term_data
   */
  public function updateNode(EntityInterface $node,
                             FieldConfig $node_config,
                             FieldStorageConfig $field_info,
                             array $term_data);


  /**
   * Check if the node has an existing value of the given term.
   *
   * @param $node
   * @param $tid
   * @param $field
   * @return bool
   */
  public function checkExistingTermInNode(EntityInterface $node, $tid, $field);


  /**
   * @param array $term_data
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function checkNodeStatus(array $term_data);

}
