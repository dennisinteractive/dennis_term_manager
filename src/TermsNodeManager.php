<?php

namespace Drupal\dennis_term_manager;

use Drupal\Component\Datetime\Time;
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
   * @var Time
   */
  protected $time;

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


  protected static $MODULES = ['scheduler'];


  /**
   * TermsNodeManager constructor.
   *
   * @param Connection $connection
   * @param Time $time
   * @param EntityTypeManager $entityTypeManager
   * @param EntityFieldManager $entityFieldManager
   * @param ModuleHandler $moduleHandler
   */
  public function __construct(Connection $connection,
                              Time $time,
                              EntityTypeManager $entityTypeManager,
                              EntityFieldManager $entityFieldManager,
                              ModuleHandler $moduleHandler) {
    $this->connection = $connection;
    $this->time = $time;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Helper to retrieve nodes that are not published and scheduled to be published.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getScheduledNodes() {
    $nids = [];
    foreach (self::$MODULES as $name) {
      // Check if the module is enabled.
      if ($this->moduleHandler->moduleExists($name)) {

        switch ($name) {

          case 'scheduler':
            $action = 'publish';

            $scheduler_enabled_types = array_keys(_scheduler_get_scheduler_enabled_node_types($action));
            if (!empty($scheduler_enabled_types)) {
              $query = $this->entityTypeManager->getStorage('node')->getQuery()
                ->exists('publish_on')
                ->condition('publish_on', $this->time->getRequestTime(), '<=')
                ->condition('type', $scheduler_enabled_types, 'IN')
                ->latestRevision()
                ->sort('publish_on')
                ->sort('nid');
              // Disable access checks for this query.
              // @see https://www.drupal.org/node/2700209
              $query->accessCheck(FALSE);
              $nids = $query->execute();
            }
            break;
        }
      }
    }

    return array_unique($nids);
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
