<?php


namespace Drupal\dennis_term_manager;

use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\dennis_term_manager\DryRun\TermManagerDryRunItem;

/**
 * Class TermManagerTree
 *
 * @package Drupal\dennis_term_manager
 */
class TermManagerTree {

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


  protected $entityTypeBundleInfo;

  /**
   * Term tree.
   * @var array
   */
  protected $termTree = [];

  static $DENNIS_TERM_MANAGER_ACTION_CREATE = 'create';
  static $DENNIS_TERM_MANAGER_ACTION_DELETE = 'delete';
  static $DENNIS_TERM_MANAGER_ACTION_MERGE= 'merge';
  static $DENNIS_TERM_MANAGER_ACTION_RENAME = 'rename';
  static $DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT = 'move parent';

  /**
   * TermManagerTree constructor.
   *
   * @param Connection $connection
   * @param EntityTypeManager $entityTypeManager
   * @param EntityFieldManager $entityFieldManager
   * @param EntityTypeBundleInfo $entityTypeBundleInfo
   */
  public function __construct(Connection $connection,
                              EntityTypeManager $entityTypeManager,
                              EntityFieldManager $entityFieldManager,
                              EntityTypeBundleInfo $entityTypeBundleInfo) {
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * @param $field
   * @return string
   */
  public function getTermVocabulary($field) {
    $target_bundle = '';
    $bundles = $this->entityTypeBundleInfo->getAllBundleInfo()['node'];
    foreach ($bundles as $id => $value) {
      $node_fields = $this->entityFieldManager->getFieldDefinitions('node', $id);
      /** @var \Drupal\Core\Field\BaseFieldDefinition  $node_field */
      foreach ($node_fields as $node_field) {
        if ($field == $node_field->getName()) {
          if ($node_field instanceof \Drupal\field\Entity\FieldConfig) {
            if ($node_field->getType() == 'entity_reference') {
              $target_bundle = $node_field->getSettings()['handler_settings']['target_bundles'];
            }
          }
        }
      }
    }
    return $target_bundle;
  }

  /**
   * Get original term.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param string $tid
   * @return \Drupal\Core\Entity\EntityInterface|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTerm($term_name, $vocabulary_name, $tid ='') {
    if (!empty($term_name) && !empty($vocabulary_name)) {
      if ($terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
        [
          'name' => $term_name,
          'vid' => $vocabulary_name,

        ]
      )) {
        return reset($terms);
      }
    }
  }

  /**
   * Get term by vocabulary/name.
   *
   * - Follows merges.
   * - Throws exception if term has been renamed or deleted.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param string $tid
   * @return mixed
   */
  public function getTreeItem($term_name, $vocabulary_name, $tid  = '') {
    if ($term = $this->getTerm($term_name, $vocabulary_name, $tid)) {
      switch ($term->action) {
        case self::$DENNIS_TERM_MANAGER_ACTION_DELETE:
          // If term has been marked for delete, it cannot be returned.
          throw new \InvalidArgumentException(t('!vocab > !name has been flagged for !operation', [
            '!operation' => $term->action,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          ]));
          break;
        case self::$DENNIS_TERM_MANAGER_ACTION_MERGE:
          // If term has been marked for merge, it cannot be returned.
          throw new \InvalidArgumentException(t('!vocab > !name has been merged into !target_vocab > !target_name', [
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
            '!target_name' => $term->target_term_name,
            '!target_vocab' => $term->target_vocabulary_name,
          ]));
          break;
        case self::$DENNIS_TERM_MANAGER_ACTION_RENAME:
          // If term has been marked for rename, it cannot be returned.
          throw new \InvalidArgumentException(t('!vocab > !name has been renamed to !new_name', [
            '!new_name' => $term->new_name,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          ]));
          break;
      }
      return $term;
    }
    // Cannot find requested term in tree.
    throw new \InvalidArgumentException(t('!vocab => !name does not exist', [
      '!name' => $term_name,
      '!vocab' => (empty($vocabulary_name)) ? t('Unspecified') : $vocabulary_name,
    ]));
  }

  /**
   * Build a term tree from the DB.
   */
  public function buildTermTree() {
    // Get current taxonomy from DB.
    $query = $this->exportTermsQuery();


    // Get taxonomy child count.
   $query->leftJoin('taxonomy_term__parent', 'c', 'c.parent_target_id = t.tid');
    $query->addExpression('GROUP_CONCAT(DISTINCT c.entity_id)', 'child_tids');


    $result = $query->execute();

    // List of columns to include in tree items.
    $id_columns = ['vid', 'target_vid', 'parent_tid'];
    $columns = array_merge($this->defaultColumns(), $id_columns);
    while ($row = $result->fetchObject()) {
      // Add report data to corresponding column.
      $item = new TermManagerDryRunItem();
      foreach ($columns as $column) {
        $item->{$column} = isset($row->{$column}) ? $row->{$column} : '';
      }

      // Add children if available.
      if (!empty($row->child_tids)) {
        $tids = array_map('intval', explode(',', $row->child_tids));
        foreach ($tids as $tid) {
          $item->addChild($tid);
        }
      }
      // Add tree item.
      $this->addTreeItem($item);
    }

  }


  /**
   * Get existing taxonomy usage.
   *
   * @param $vocabs
   *    Array of vocabulary names, used to limit the results.
   * @return \Drupal\Core\Database\Query\Select
   */
  public function exportTermsQuery($vocabs = []) {

    drupal_set_message($vocabs, 'error');


    $query = $this->connection->select('taxonomy_term_data', 't');

    // Term vocabulary and name.
    $query->addField('t', 'vid', 'vocabulary_name');
    $query->addField('td', 'name', 'term_name');
    $query->addField('ua', 'alias', 'path');
    // TID and VID
    $query->addField('td', 'vid', 'vid');
    $query->addField('t', 'tid', 'tid');
    $query->addField('p', 'tid', 'parent_tid');
    // Get node count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_index i WHERE i.tid = t.tid)', 'node_count');
    // Parent term name.
    $query->addExpression('IF(td.name IS NULL, \'\', td.name)', 'parent_term_name');
    // Get child term count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_term__parent h WHERE h.parent_target_id = t.tid)', 'term_child_count');
    // Join alias.
    $query->leftJoin('url_alias', 'ua', "ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))");
    // Join on vocabulary of term.
    $query->leftJoin('taxonomy_term_field_data', 'td', 'td.vid = t.vid');
    // Parent information.
    $query->leftJoin('taxonomy_term__parent', 'h', 'h.entity_id = t.tid');
    $query->leftJoin('taxonomy_term_data', 'p', 'p.tid = h.parent_target_id');

    // Filter by vocabulary
    $machine_names = [];
    if (!empty($vocabs)) {
      foreach ($vocabs as $name) {
        $machine_names[] = $this->termManagerMachineName($name);
      }
      $query->condition('t.vid', $machine_names, 'IN');
    } else {
      drupal_set_message('is empty', 'error');
    }



    // Group by tid to get node counts for each term.
    $query->groupBy('t.tid');
    $query->groupBy('t.vid');
    $query->groupBy('td.name');
    $query->groupBy('ua.alias');
    $query->groupBy('td.vid');
    $query->groupBy('p.tid');

    //echo $query->__toString() . "\n";

/**

    "SELECT t.vid AS vocabulary_name, td.name AS term_name, ua.alias AS path, td.vid AS vid, t.tid AS tid, p.tid AS parent_tid, (SELECT COUNT(1)
     FROM taxonomy_index i
     WHERE i.tid = t.tid) AS node_count, (SELECT COUNT(1)
     FROM taxonomy_term__parent h
     WHERE h.parent_target_id = t.tid) AS term_child_count, IF(td.name IS NULL, '', td.name) AS parent_term_name
     FROM taxonomy_term_data t
     LEFT OUTER JOIN url_alias ua ON ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))
     LEFT OUTER JOIN taxonomy_term_field_data td ON td.vid = t.vid
     LEFT OUTER JOIN taxonomy_term__parent h ON h.entity_id = t.tid
     LEFT OUTER JOIN taxonomy_term_data p ON p.tid = h.parent_target_id
     GROUP BY t.tid"
 */


    /**
     *
     SELECT t.vid AS vocabulary_name, td.name AS term_name, ua.alias AS path, td.vid AS vid, t.tid AS tid, p.tid AS parent_tid, (SELECT COUNT(1)
     FROM taxonomy_index i
     WHERE i.tid = t.tid) AS node_count, IF(td.name IS NULL, '', td.name) AS parent_term_name, (SELECT COUNT(1)
     FROM taxonomy_term__parent h
     WHERE h.parent_target_id = t.tid) AS term_child_count
     FROM {taxonomy_term_data} t
     LEFT OUTER JOIN {url_alias} ua ON ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))
     LEFT OUTER JOIN {taxonomy_term_field_data} td ON td.vid = t.vid
     LEFT OUTER JOIN {taxonomy_term__parent} h ON h.entity_id = t.tid
     LEFT OUTER JOIN {taxonomy_term_data} p ON p.tid = h.parent_target_id
     GROUP BY t.tid
     */




    return $query;
  }


  /**
   * Add item to the termTree.
   *
   * @param TermManagerDryRunItem $item
   */
  public function addTreeItem(TermManagerDryRunItem $item) {
    // Format keys.
    $vocabulary_key = $this->formatKey($item->vocabulary_name);
    $term_key = $this->formatKey($item->term_name);
    // Add item to tree.
    $this->termTree[$vocabulary_key][$term_key][$item->tid] = $item;
  }


  /**
   * Formats string into reliable key.
   *
   * @param $str
   */
  protected function formatKey($str) {
    // Trim.
    return trim($str);
  }

  /**
   * Helper to generate machine names.
   *
   * @param $name
   * @return string
   */
  protected function termManagerMachineName($name) {
    return strtolower(preg_replace('@[^a-zA-Z0-9_]+@', '_', $name));
  }


  /**
   * Helper to get vocabulary.
   *
   * @param : $vocabulary_name
   *   Vocabulary Name
   *
   * @return : $vocabulary
   *   array containing vocabulary
   */
  public function getVocabulary($vocabulary_name) {

    return \Drupal\taxonomy\Entity\Vocabulary::load($vocabulary_name);

    // Return static if possible.
    $vocabulary = &drupal_static(__FUNCTION__ . $vocabulary_name, FALSE);
    if ($vocabulary !== FALSE) {
      return $vocabulary;
    }
    // Get vocabulary by vocabulary name.
    $query = $this->connection->select('taxonomy_vocabulary', 'tv');
    $query->fields('tv', array(
      'machine_name',
      'vid',
    ));
    $query->condition('tv.name', $vocabulary_name, '=');
    return $query->execute()->fetchObject();


  }


  /**
   * Get allowed fields for specified vocabulary.
   *
   * @param $vid
   * @return array|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getVocabularyAllowedFields($vid) {
    // Return cached allowed fields if available.
    $allowed_fields = &drupal_static(__FUNCTION__ . $vid, FALSE);
    if ($allowed_fields !== FALSE) {
      return $allowed_fields;
    }

    // Build array of allowed fields for this vocabulary.
    $allowed_fields = [];

    $taxonomy_fields =  $this->entityTypeManager
      ->getStorage('field_entity')
      ->loadByProperties(['type' => 'taxonomy_term_reference']);


    foreach ($taxonomy_fields as $field_info) {
      $allowed_vocabularies = $this->getFieldAllowedVocabularies($field_info['field_name']);
      if (isset($allowed_vocabularies[$vid])) {
        $allowed_fields[] = $field_info['field_name'];
      }
    }
    return $allowed_fields;
  }

  /**
   * Get allowed vocabularies for specified field.
   *
   * @param $field_name
   * @return array|mixed
   */
  public function getFieldAllowedVocabularies($field_name) {
    // Return cached allowed vocabularies if available.
    $allowed_vocabularies = &drupal_static(__FUNCTION__ . $field_name, FALSE);
    if ($allowed_vocabularies !== FALSE) {
      return $allowed_vocabularies;
    }
    // Build arry of allowed vocabularies.
    $allowed_vocabularies = [];
    if ($field_info = FieldStorageConfig::loadByName('taxonomy_term', $field_name)) {
      if (isset($field_info['settings']['allowed_values']) && is_array($field_info['settings']['allowed_values'])) {
        foreach ($field_info['settings']['allowed_values'] as $allowed_value) {
          if (isset($allowed_value['vocabulary'])) {
            if ($vocabulary = Vocabulary::load($allowed_value['vocabulary'])) {
              $allowed_vocabularies[$vocabulary->id()] = $allowed_value['vocabulary'];
            }
          }
        }
      }
    }
    return $allowed_vocabularies;
  }

  /**
   * CSV/TSV files should always have these columns.
   */
  public function defaultColumns() {
    return [
      'node',
      'field',
      'value',
    ];
  }
}
