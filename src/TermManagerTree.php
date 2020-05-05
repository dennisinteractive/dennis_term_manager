<?php


namespace Drupal\dennis_term_manager;


use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Vocabulary;
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
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get original term.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param string $tid
   * @return mixed
   */
  public function getOriginalTreeItem($term_name, $vocabulary_name, $tid = '') {

    // Format keys.
    $vocabulary_key = $this->formatKey($vocabulary_name);
    $term_key = $this->formatKey($term_name);

    // Return tree item if it exists.
    if (isset($this->termTree[$vocabulary_key][$term_key])) {

      // if specified tid doesn't exist throw exception
      if (!empty($tid)) {
        if (!isset($this->termTree[$vocabulary_key][$term_key][$tid])) {
          throw new \InvalidArgumentException(t('!tid is not valid for !vocab > !name.', [
            '!tid' => $tid,
            '!name' => $term_name,
            '!vocab' => $vocabulary_name,
          ]));
        }
        // If $tid not empty return tid item
        return $this->termTree[$vocabulary_key][$term_key][$tid];
      }
      // if more than one throw exception
      if (count($this->termTree[$vocabulary_key][$term_key]) > 1) {
        throw new \InvalidArgumentException(t('!vocab > !name is duplicated. Please provide a tid.', [
          '!name' => $term_name,
          '!vocab' => $vocabulary_name,
        ]));
      }
      // else return reset()
      return reset($this->termTree[$vocabulary_key][$term_key]);
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
    if ($term = $this->getOriginalTreeItem($term_name, $vocabulary_name, $tid)) {
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
    $query = $this->dennis_term_manager_export_terms_query();

    // Get taxonomy child count.
    $query->leftJoin('taxonomy_term_hierarchy', 'c', 'c.parent = t.tid');
    $query->addExpression('GROUP_CONCAT(DISTINCT c.tid)', 'child_tids');

    $result = $query->execute();

    // List of columns to include in tree items.
    $id_columns = ['vid', 'target_vid', 'parent_tid'];
    $columns = array_merge($this->dennis_term_manager_default_columns(), $id_columns);
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
  protected function dennis_term_manager_export_terms_query($vocabs = []) {

    $query = $this->connection->select('taxonomy_term_data', 't');

    // Term vocabulary and name.
    $query->addField('v', 'name', 'vocabulary_name');
    $query->addField('t', 'name', 'term_name');

    // Filter by vocabulary
    $machine_names = [];
    if (!empty($vocabs)) {
      foreach ($vocabs as $name) {
        $machine_names[] = $this->termManagerMachineName($name);
      }
      $query->condition('v.machine_name', $machine_names, 'IN');
    }

    // Join alias.
    $query->leftJoin('url_alias', 'ua', "ua.source = CONCAT('taxonomy/term/', CAST(t.tid AS CHAR))");
    $query->addField('ua', 'alias', 'path');

    // TID and VID
    $query->addField('v', 'vid', 'vid');
    $query->addField('t', 'tid', 'tid');

    // Get node count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_index i WHERE i.tid = t.tid)', 'node_count');

    // Get child term count for term.
    $query->addExpression('(SELECT COUNT(1) FROM taxonomy_term_hierarchy h WHERE h.parent = t.tid)', 'term_child_count');

    // Parent term name.
    $query->addExpression('IF(p.name IS NULL, \'\', p.name)', 'parent_term_name');

    // Join on vocabulary of term.
    $query->leftJoin('taxonomy_vocabulary', 'v', 'v.vid = t.vid');

    // Parent information.
    $query->leftJoin('taxonomy_term_hierarchy', 'h', 'h.tid = t.tid');
    $query->leftJoin('taxonomy_term_data', 'p', 'p.tid = h.parent');
    $query->addField('p', 'tid', 'parent_tid');

    // Group by tid to get node counts for each term.
    $query->groupBy('t.tid');

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

    $taxonomy_fields =  \Drupal::entityTypeManager()
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


}
