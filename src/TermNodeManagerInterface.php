<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Interface TermNodeManagerInterface.
 *
 * @package Drupal\dennis_term_manager
 */
interface TermNodeManagerInterface {

  /**
   * Get the field settings for the given node.
   *
   * @param string $field_name
   *   Field name.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   Field storage config instance.
   */
  public function getFieldSettings($field_name);

  /**
   * Update the node with the given term.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node instance.
   * @param \Drupal\field\Entity\FieldStorageConfig $field_info
   *   Field storage config.
   * @param array $node_fields
   *   Node fields.
   * @param array $term_data
   *   Term data.
   */
  public function updateNode(EntityInterface $node,
                             FieldStorageConfig $field_info,
                             array $node_fields,
                             array $term_data);

  /**
   * Check if the node has an existing value of the given term.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node instance.
   * @param string|int $tid
   *   Term ID.
   * @param string $field
   *   Field name.
   *
   * @return bool
   *   Is term exists in field.
   */
  public function checkExistingTermInField(EntityInterface $node, $tid, $field);

  /**
   * Check for the existence of the term on primary fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Node instance.
   * @param array $node_fields
   *   Node fields.
   * @param string|int $tid
   *   Term ID to ckeck.
   *
   * @return bool
   *   Is term primary.
   */
  public function checkPrimaryEntityFields(EntityInterface $node, array $node_fields, $tid);

  /**
   * Check node status.
   *
   * @param array $term_data
   *   Term data array.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Node entity or null.
   */
  public function checkNodeStatus(array $term_data);

}
