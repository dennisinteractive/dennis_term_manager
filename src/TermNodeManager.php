<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Class TermNodeManager.
 *
 * @package Drupal\dennis_term_manager
 */
class TermNodeManager implements TermNodeManagerInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Term manager.
   *
   * @var TermManagerInterface
   */
  protected $termManager;

  /**
   * TermManagerProcessItem constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   * @param TermManagerInterface $termManager
   *   Term manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager,
                              TermManagerInterface $termManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->termManager = $termManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettings($field_name) {
    if ($field_info = FieldStorageConfig::loadByName('node', $field_name)) {
      if ($field_info->getSetting('target_type') == 'taxonomy_term') {
        return $field_info;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateNode(EntityInterface $node,
                             FieldStorageConfig $field_info,
                             array $node_fields,
                             array $term_data) {
    $updated = FALSE;
    if ($node->hasField($term_data['field'])) {
      if ($term = $this->termManager->getTermFromNodeField($node_fields[$term_data['field']], $term_data['field'], $term_data['value'])) {
        if ($field_info->getCardinality() == 1) {
          $node->set($term_data['field'], ['target_id' => $term->id()]);
          $node->save();
          $updated = TRUE;
        }
        else {
          // Check the term does not all ready exist on the multi field.
          if (!$this->checkExistingTermInField($node, $term->id(), $term_data['field'])) {
            // Check the term does not already exist on a primary field.
            if (!$this->checkPrimaryEntityFields($node, $node_fields, $term->id())) {
              $node->get($term_data['field'])->appendItem(['target_id' => $term->id()]);
              $node->save();
              $updated = TRUE;
            }
          }
        }
      }
    }
    return $updated;
  }

  /**
   * {@inheritdoc}
   */
  public function checkExistingTermInField(EntityInterface $node, $tid, $field) {
    $existing_values = [];
    if ($node->hasField($field)) {
      $field_values = $node->get($field)->getValue();
      if (!empty($field_values)) {
        foreach ($field_values as $value) {
          $existing_values[] = $value['target_id'];
        }
        if (!empty($existing_values)) {
          if (in_array($tid, $existing_values)) {
            return TRUE;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkPrimaryEntityFields(EntityInterface $node, array $node_fields, $tid) {
    foreach ($node_fields as $field) {
      /** @var \Drupal\field\Entity\FieldStorageConfig $node_field */
      if (
        $this->checkNodeFieldName($field->getName(), 'field')
        && ($node_field = $this->getFieldSettings($field->getName()))
        && ($node_field->getCardinality() == 1)
        && ($check_field_tid = $node->get($field->getName())->getString())
        && $tid == $check_field_tid
      ) {
        return TRUE;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkNodeStatus(array $term_data) {
    if (isset($term_data['node']) && $node = $this->entityTypeManager->getStorage('node')->load($term_data['node'])) {
      return $node;
    }
    return NULL;
  }

  /**
   * Compare the beginning of a string with a given needle.
   *
   * @param string $string
   *   Actual string.
   * @param string $needle
   *   Expected needle string.
   *
   * @return bool
   *   True if the given string matching the needle or the needle is the prefix
   *   for the given string. False - otherwise.
   */
  public function checkNodeFieldName($string, $needle) {
    return strpos($string, $needle) === 0;
  }

}
