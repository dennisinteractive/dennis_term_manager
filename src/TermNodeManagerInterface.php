<?php

namespace Drupal\dennis_term_manager;


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
   * @param $field_name
   * @return FieldStorageConfig
   */
  public function getFieldSettings($field_name);

  /**
   * Update the node with the given term.
   *
   * @param EntityInterface $node
   * @param FieldStorageConfig $field_info
   * @param array $node_fields
   * @param array $term_data
   */
  public function updateNode(EntityInterface $node,
                             FieldStorageConfig $field_info,
                             array $node_fields,
                             array $term_data);


  /**
   * Check if the node has an existing value of the given term.
   *
   * @param $node
   * @param $tid
   * @param $field
   * @return bool
   */
  public function checkExistingTermInField(EntityInterface $node, $tid, $field);


  /**
   * Check for the existence of the term on primary fields
   *
   * @param EntityInterface $node
   * @param array $node_fields
   * @param $tid
   * @return bool
   */
  public function checkPrimaryEntityFields(EntityInterface $node, array $node_fields, $tid);


  /**
   * @param array $term_data
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function checkNodeStatus(array $term_data);


}
