<?php

namespace Drupal\dennis_term_manager\Process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\dennis_term_manager\TermManagerInterface;
use Drupal\dennis_term_manager\TermNodeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class TermManagerProcessItem.
 *
 * @package Drupal\dennis_term_manager\Process
 */
class TermManagerProcessItem {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Term manager.
   *
   * @var \Drupal\dennis_term_manager\TermManagerInterface
   */
  protected $termManager;

  /**
   * Term node manager.
   *
   * @var \Drupal\dennis_term_manager\TermNodeManagerInterface
   */
  protected $termNodeManager;

  /**
   * TermManagerProcessItem constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   Entity field manager.
   * @param \Drupal\dennis_term_manager\TermManagerInterface $termManager
   *   Term manager.
   * @param \Drupal\dennis_term_manager\TermNodeManagerInterface $termNodeManager
   *   Term node manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger factory.
   */
  public function __construct(EntityTypeManager $entityTypeManager,
                              EntityFieldManager $entityFieldManager,
                              TermManagerInterface $termManager,
                              TermNodeManagerInterface $termNodeManager,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->termManager = $termManager;
    $this->termNodeManager = $termNodeManager;
    $this->logger = $loggerFactory->get('dennis_term_manager');
  }

  /**
   * Initiate the updating of terms and nodes.
   *
   * @param array $term
   *   Nodes to proceed.
   */
  public function init(array $term) {
    return isset($term['node'], $term['field'], $term['value'])
      && ($field_info = $this->termNodeManager->getFieldSettings($term['field']))
      && ($node = $this->termNodeManager->checkNodeStatus($term))
      && ($node_field = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle()))
      && $this->termNodeManager->updateNode($node, $field_info, $node_field, $term);
  }

}
