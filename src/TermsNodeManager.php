<?php

namespace Drupal\dennis_term_manager;

use Drupal\Core\Language\Language;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\field\Entity\FieldStorageConfig;


/**
 * Class TermsNodeManager
 *
 * @package Drupal\dennis_term_manager
 */
class TermsNodeManager {


  /**
   * @var Connection
   */
  protected $connection;

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * @var ModuleHandler
   */
  protected $moduleHandler;


  protected static $MODULES = ['rules_scheduler', 'scheduler'];


  /**
   * TermsNodeManager constructor.
   *
   * @param Connection $connection
   * @param EntityTypeManager $entityTypeManager
   * @param EntityFieldManager $entityFieldManager
   * @param ModuleHandler $moduleHandler
   */
  public function __construct(Connection $connection,
                              EntityTypeManager $entityTypeManager,
                              EntityFieldManager $entityFieldManager,
                              ModuleHandler $moduleHandler) {
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Helper to retrieve nodes that are not published and scheduled to be published.
   *
   * Since we use the taxonomy_index table on _dennis_term_manager_get_associated_nodes(),
   * it will only return nodes that are published. This is a workaround to get the unpublished
   * nodes that are scheduled to be published.
   *
   * @return array
   *   List of nodes.
   */
  public function getScheduledNodes() {
    $nodes = [];
    foreach (self::$MODULES as $name) {
      // Check if the module is enabled.
      if ($this->moduleHandler->moduleExists($name)) {
        $query = $this->connection->select('node', 'n');
        $query->fields('n', array(
          'nid',
        ));
        $query->condition('n.status', 0, '=');

        switch ($name) {
          case 'rules_scheduler':
            // Check if field exists, as rule_scheduler could be enabled but not setup.
            $map = $this->entityFieldManager->getFieldMap();
            if (!empty($map['field_schedule_publish']['bundles']['node'])) {
              // Inner join node that are scheduled to be published.
              $query->innerJoin('field_data_field_schedule_publish', 's', 's.entity_id = n.nid');
              $query->condition('s.field_schedule_publish_value', 1, '=');
              $nodes = array_merge($nodes, $query->execute()->fetchCol('nid'));
            }
            break;

          case 'scheduler':
            // Inner join node that are scheduled to be published.
            $query->innerJoin('scheduler', 's', 's.nid = n.nid AND s.publish_on > 0');
            $nodes = array_merge($nodes, $query->execute()->fetchCol('nid'));
            break;
        }
      }
    }
    return array_unique($nodes);
  }


  /**
   * Helper to find tids used on nodes that are not published but scheduled to be published.
   *
   * @param $nodes
   *    The node to be cleaned up.
   * @param $list
   *    The list of tids used by the recursive call.
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function listNodeTids($nodes, $list = []) {

    if ($nid = array_shift($nodes)) {

      /** @var \Drupal\node\Entity\Node $node */
      $node = $this->entityTypeManager->getStorage('node')->load($nid);

      $taxonomy_fields = $this->getTaxonomyFields($node->getEntityType());

      // Remove source tid from fields.
      foreach ($taxonomy_fields as $field_name => $field_info) {
        // Check each node field for term reference.
        if (isset($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0])) {
          foreach ($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED] as $value) {
            if (!empty($value['tid'])) {
              $tid = $value['tid'];

              // No need to return the full node, just nid will be enough.
              $new_node = new \stdClass();
              $new_node->nid = $node->nid;

              $list[$tid][] = $new_node;// $node
            }
          }
        }
      }
      return $this->listNodeTids($nodes, $list);
    }

    return $list;
  }


  /**
   * Helper to get list of term reference fields.
   *
   * @param $node_type
   *    The node type.
   * @return
   *    List of term reference field names.
   */
  protected function getTaxonomyFields($node_type) {
    $taxonomy_fields = &drupal_static(__FUNCTION__ . $node_type, []);
    if (!empty($taxonomy_fields[$node_type])) {
      return $taxonomy_fields[$node_type];
    }
    if ($entity_info = $this->entityFieldManager->getFieldDefinitions('node', $node_type)) {
      foreach ($entity_info as $field) {
        $field_name = $field['field_name'];

        $field_info = FieldStorageConfig::loadByName('node', $field_name);
        if ($field_info['type'] == 'taxonomy_term_reference') {
          $taxonomy_fields[$node_type][$field_name] = $field_info;
        }
      }
    }
    return $taxonomy_fields[$node_type];
  }
}
