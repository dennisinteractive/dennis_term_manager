<?php

namespace Drupal\dennis_term_manager\Process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\dennis_term_manager\TermManagerInterface;
use Drupal\dennis_term_manager\TermNodeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class TermManagerProcessItem
 *
 * @package Drupal\dennis_term_manager\Process
 */
class TermManagerProcessItem  {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var TermManagerInterface
   */
  protected $termManager;

  /**
   * @var TermNodeManagerInterface
   */
  protected $termNodeManager;

  /**
   * TermManagerProcessItem constructor.
   *
   * @param EntityTypeManager $entityTypeManager
   * @param EntityFieldManager $entityFieldManager
   * @param TermManagerInterface $termManager
   * @param TermNodeManagerInterface $termNodeManager
   * @param LoggerChannelFactoryInterface $loggerFactory
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
   */
  public function init(array $term) {
    if ( isset($term['node']) && isset($term['field']) && isset($term['value'])) {
      if ($field_info = $this->termNodeManager->getFieldSettings($term['field'])) {
        if ($node = $this->termNodeManager->checkNodeStatus($term)) {
          if ($node_field = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle())) {
            if ($this->termNodeManager->updateNode($node, $field_info, $node_field, $term)) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }
}
