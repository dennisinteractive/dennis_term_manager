<?php

namespace Drupal\dennis_term_manager;

use \Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldConfig;
use \Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Class TermNodeManager
 *
 * @package Drupal\dennis_term_manager
 */
class TermNodeManager implements TermNodeManagerInterface {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var TermManagerInterface
   */
  protected $termManager;

  /**
   * TermManagerProcessItem constructor.
   *
   * @param EntityTypeManager $entityTypeManager
   * @param TermManagerInterface $termManager
   */
  public function __construct(EntityTypeManager $entityTypeManager,
                              TermManagerInterface $termManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->termManager = $termManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettings($term) {
    if ($field_info = FieldStorageConfig::loadByName('node', $term['field'])) {
      if ($field_info->getSetting('target_type') == 'taxonomy_term') {
        return $field_info;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws EntityStorageException
   */
  public function updateNode(EntityInterface $node,
                             FieldConfig $node_config,
                             FieldStorageConfig $field_info,
                             array $term_data) {
    if ($node->hasField($term_data['field'])) {
      if ($term = $this->termManager->getTermFromNodeField($node_config, $term_data)) {
        if ($field_info->getCardinality() == 1) {
          $node->set($term_data['field'], ['target_id' => $term->id()]);
        } else {
          if (!$this->checkExistingTermInNode($node, $term->id(), $term_data['field'])) {
            $node->get($term_data['field'])->appendItem(['target_id' => $term->id()]);
          }
        }
        $node->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkExistingTermInNode(EntityInterface $node, $tid, $field) {
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
   *
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkNodeStatus(array $term_data) {
    if (isset($term_data['node'])) {
      if ($node = $this->entityTypeManager->getStorage('node')->load($term_data['node'])) {
        return $node;
      }
    }
  }
}
