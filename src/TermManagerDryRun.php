<?php

namespace Drupal\dennis_term_manager;


use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * @file TermManagerDryRun
 */
class TermManagerDryRun {


  /**
   * @var \Drupal\dennis_term_manager\TermManagerService
   */
  protected $termServiceManager;


  /**
   * Term tree.
   * @var array
   */
  protected $termTree = [];

  /**
   * List of operations.
   * @var TermManagerOperationList
   */
  protected $operationList;

  static $DENNIS_TERM_MANAGER_ACTION_CREATE = 'create';
  static $DENNIS_TERM_MANAGER_ACTION_DELETE = 'delete';
  static $DENNIS_TERM_MANAGER_ACTION_MERGE= 'merge';
  static $DENNIS_TERM_MANAGER_ACTION_RENAME = 'rename';
  static $DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT = 'move parent';



  /**
   * Initialise dry run.
   */
  public function __construct() {
    $this->buildTermTree();
    $this->operationList = new TermManagerOperationList();
    $this->termServiceManager = \Drupal::service('dennis_term_manager.service');
  }

  /**
   * Build a term tree from the DB.
   */
  protected function buildTermTree() {
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
   * Execute dry run using specified TSV/CSV file.
   *
   * @param $file_path
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute($file_path) {
    if (empty($file_path)) {
      return;
    }

    // Get file info.
    $file_info = pathinfo($file_path);

    // Detect line endings.
    ini_set('auto_detect_line_endings',TRUE);

    if (($handle = fopen($file_path, "r")) !== FALSE) {
      $delimiter = $this->dennis_term_manager_detect_delimiter(file_get_contents($file_path));
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        $operation = new TermManagerOperationItem();
        // Get list of operation columns.
        foreach ($this->dennis_term_manager_default_columns() as $column) {
          if (isset($columns[$column])) {
            $index = $columns[$column];
            try {
              $operation->{$column} = isset($data[$index]) ? trim($data[$index]) : '';
            }
            catch (\Exception $e) {
              $operation->error = $e->getMessage();
            }
          }
        }
        if (!empty($operation->action)) {
          // Only process operations with actions.
          $this->processOperation($operation);
        }
      }
    }

    // Display dry run errors to the end user.
    if ($errorList = $this->operationList->getErrorList()) {
      foreach ($errorList as $error) {
        \Drupal::messenger()->addError($error['message']);
      }
      $this->operationList->outputCSV($file_path, $delimiter);
    }
    // Output dry run errors in operation CSV.
    $this->outputCSV($file_path, $delimiter);
  }

  /**
   * Getter for operation List.
   */
  public function getOperationList() {
    return $this->operationList;
  }

  /**
   * Create operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $parent_term_name
   * @throws \Exception
   */
  protected function create($term_name, $vocabulary_name, $parent_term_name) {
    // Check if term already exists.
    if ($this->getOriginalTreeItem($term_name, $vocabulary_name)) {
      throw new \Exception(t('!vocab > !name already exists', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      )));
    }
    // Assert that parents are valid.
    if (!empty($parent_term_name)) {
      $parent_term = $this->getTreeItem($parent_term_name, $vocabulary_name);
    }
    // Check that vocabulary is valid.
    $vocabulary =  $this->dennis_term_manager_get_vocabulary($vocabulary_name);
    if (empty($vocabulary->vid)) {
      throw new \Exception(t('!vocab is not a valid vocabulary', [
        '!vocab' => $vocabulary_name,
      ]));
    }
    // If term doesn't exist and parent is valid, we can safely create.
    $term = new TermManagerDryRunItem();
    $term->term_name = $term_name;
    $term->vid = $vocabulary->vid;
    $term->vocabulary_name = $vocabulary_name;
    $term->parent_term_name = $parent_term_name;
    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_CREATE;
    $this->addTreeItem($term);

    // Add as child of parent item.
    if (!empty($parent_term_name)) {
      $parent_term = $this->getTreeItem($parent_term_name, $vocabulary_name);
      // Using term name for new children.
      $parent_term->addChild($term_name);
    }
  }

  /**
   * Create operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param string $tid
   * @param $target_term_name
   * @param $target_tid
   * @throws \Exception
   */
  protected function moveParent($term_name, $vocabulary_name, $tid = '', $target_term_name, $target_tid) {
    $term = $this->getTreeItem($term_name, $vocabulary_name, $tid);

    // Prevent terms being moved more than once per run.
    if ($term->action == self::$DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT) {
      throw new \Exception(t('!term_name has already been moved to !parent_term_name', array(
        '!term_name' => $term->term_name,
        '!parent_term_name' => $term->parent_term_name,
      )));
    }

    // Get parent and check it's not the same term.
    if (!empty($target_term_name)) {
      $parent_term = $this->getTreeItem($target_term_name, $vocabulary_name, $target_tid);

      if ($parent_term->tid == $term->tid) {
        throw new \Exception(t('!term cannot be parent of self', array(
          '!term' => $term->term_name,
        )));
      }

      // Get parent's parent.
      $parents = $this->getParents($parent_term);
      // Throw exception if term is is the parent of the new parent.
      if (isset($parents[$term->term_name])) {
        throw new \Exception(t('!term is a parent of !parent', array(
          '!term' => $term->term_name,
          '!parent' => $parent_term->term_name,
        )));
      }

      // Add child to new parent.
      $parent_term->addChild($term->tid);
    }

    // Get current parent.
    if (!empty($term->parent_term_name)) {
      $current_parent_term = $this->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
      // Remove child from current parent.
      $current_parent_term->removeChild($term->tid);
    }

    // Store parent term data.
    if (isset($parent_term)) {
      $term->parent_term_name = $parent_term->term_name;
      $term->parent_tid = $parent_term->tid;
    }
    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT;
  }

  /**
   * Get array of parents for specified $term.
   *
   * @param TermManagerDryRunItem $term
   * @return array
   */
  protected function getParents(TermManagerDryRunItem $term) {
    $parents = array();
    try {
      $parent = $this->getTreeItem($term->parent_term_name, $term->vocabulary_name);
      $parents[$term->parent_term_name] = $parent;
      $parents = array_merge($parents, $this->getParents($parent));
    }
    catch (\Exception $e) {
      // No parent found;
    }
    return $parents;
  }

  /**
   * Rename operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $new_name
   * @param $target_tid
   * @throws \Exception
   */
  protected function rename($term_name, $vocabulary_name, $new_name, $target_tid) {
    // Don't allow empty names.
    $new_name = trim($new_name);
    if (empty($new_name)) {
     throw new \Exception(t('New name for !vocab > !term_name is empty', [
        '!vocab' => $vocabulary_name,
        '!term_name' => $term_name,
      ]));
    }

    // Get term to rename.
    $term = $this->getTreeItem($term_name, $vocabulary_name, $target_tid);

    // Assert that the new name doesn't already exist.
    if ($existingTerm =  $this->getOriginalTreeItem($new_name, $vocabulary_name, $target_tid)) {
      // Throw exception if another term exists with the same name or the name isn't being changed.
      if ($existingTerm->tid != $term->tid || $term_name === $new_name) {
        throw new \Exception(t('!vocab > !name already exists', [
          '!vocab' => $vocabulary_name,
          '!name' => $new_name,
        ]));
      }
    }

    // Copy term.
    $renamedTerm = clone $term;
    // Rename term.
    $renamedTerm->term_name = $new_name;
    $renamedTerm->description = t('Renamed from !name', array('!name' => $term_name));
    // Add renamed term to tree (keyed with new name).
    $this->addTreeItem($renamedTerm);

    // Update original term with action.
    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_RENAME;
    $term->new_name = $new_name;
  }

  /**
   * Delete operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $tid
   * @throws \Exception
   */
  protected function delete($term_name, $vocabulary_name, $tid) {
    $term = $this->getTreeItem($term_name, $vocabulary_name, $tid);

    // Assert that the term is not locked.
    $this->assertNotLocked($term);

    // Prevent deleted terms with children.
    if ($term->isParent()) {
      throw new \Exception(t('!vocab > !name cannot be deleted as it has children', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      )));
    }

    // Remove term from parent.
    if (!empty($term->parent_term_name)) {
      $parent_term = $this->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
      if (!empty($term->tid)) {
        $parent_term->removeChild($term->tid);
      }
      else {
        $parent_term->removeChild($term->term_name);
      }
    }

    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_DELETE;
  }

  /**
   * Merge operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $target_term_name
   * @param $target_vocabulary_name
   * @param string $target_field
   * @param string $tid
   * @param string $target_tid
   * @throws \Exception
   */
  protected function merge($term_name, $vocabulary_name, $target_term_name, $target_vocabulary_name, $target_field = '', $tid = '', $target_tid = '') {
    // Prevent merge with self.
    if ($term_name == $target_term_name && $vocabulary_name == $target_vocabulary_name && ($tid == $target_tid)) {
      throw new \Exception(t('Cannot merge with self, please specify a tid.'));
    }

    // Ensure target term can be retrieved.
    $target_term = $this->getTreeItem($target_term_name, $target_vocabulary_name, $target_tid);

    if (empty($target_term->tid)) {
      throw new \Exception(t('!vocab > !name has not been created yet', array(
        '!vocab' => $target_vocabulary_name,
        '!name' => $target_term_name,
      )));
    }

    $term = $this->getTreeItem($term_name, $vocabulary_name, $tid);

    // Assert that the term is not locked.
    $this->assertNotLocked($term);

    // Prevent merging terms with children.
    if ($term->isParent()) {
      throw new \Exception(t('!vocab > !name cannot be merged as it has children', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      )));
    }

    // Ensure target vocabulary is allowed in target field.
    if ($target_vocabulary_name != $vocabulary_name) {
      // Get target vocabulary by name.
      $target_vocabulary = $this->dennis_term_manager_get_vocabulary($target_vocabulary_name);
      if (empty($target_field)) {
        $valid_fields = $this->dennis_term_manager_get_vocabulary_allowed_fields($target_vocabulary->vid);
        throw new ErrorException(t('You must specify a target_field when merge across vocabularies: !valid_fields', array(
          '!valid_fields' => implode(', ', $valid_fields),
        )));
      }
      else {
        // Check that target vocabulary is allowed in the target field.
        $target_fields = array_map('trim', explode(',', $target_field));
        foreach ($target_fields as $field_name) {
          $allowed_vocabularies = $this->dennis_term_manager_get_field_allowed_vocabularies($field_name);
          if (!isset($allowed_vocabularies[$target_vocabulary->vid])) {



            $valid_fields = $this->dennis_term_manager_get_vocabulary_allowed_fields($target_vocabulary->vid);
            throw new ErrorException(t('!field cannot contain !vocab terms, please use one of the following: !valid_fields', array(
              '!vocab' => $target_vocabulary_name,
              '!field' => $field_name,
              '!valid_fields' => implode(', ', $valid_fields),
            )));
          }
        }
      }
    }

    // Update new count on target.
    $target_term->node_count += $term->node_count;
    $term->node_count = 0;

    // Remove term from parent.
    if (!empty($term->parent_term_name)) {
      if (isset($term->parent_tid)) {
        $parent_term = $this->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
      }
      else {
        $parent_term = $this->getTreeItem($term->parent_term_name, $vocabulary_name);
      }
      if (!empty($term->tid)) {
        $parent_term->removeChild($term->tid);
      }
      else {
        $parent_term->removeChild($term->term_name);
      }
    }

    // Set action.
    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_MERGE;

    // Store target term data.
    $term->target_term_name = $target_term->term_name;
    $term->target_vocabulary_name = $target_term->vocabulary_name;
    $term->target_vid = $target_term->vid;
    $term->target_tid = $target_term->tid;

    // Lock target term from being used in other actions.
    $target_term->locked = TRUE;
  }

  /**
   * Get term by vocabulary/name.
   *
   * - Follows merges.
   * - Throws exception if term has been renamed or deleted.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $tid
   *    Optional
   * @throws Exception
   */
  protected function getTreeItem($term_name, $vocabulary_name, $tid  = '') {
    if ($term = $this->getOriginalTreeItem($term_name, $vocabulary_name, $tid)) {
      switch ($term->action) {
        case self::$DENNIS_TERM_MANAGER_ACTION_DELETE:
          // If term has been marked for delete, it cannot be returned.
          throw new \Exception(t('!vocab > !name has been flagged for !operation', array(
            '!operation' => $term->action,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          )));
          break;
        case self::$DENNIS_TERM_MANAGER_ACTION_MERGE:
          // If term has been marked for merge, it cannot be returned.
          throw new \Exception(t('!vocab > !name has been merged into !target_vocab > !target_name', array(
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
            '!target_name' => $term->target_term_name,
            '!target_vocab' => $term->target_vocabulary_name,
          )));
          break;
        case self::$DENNIS_TERM_MANAGER_ACTION_RENAME:
          // If term has been marked for rename, it cannot be returned.
          throw new \Exception(t('!vocab > !name has been renamed to !new_name', array(
            '!new_name' => $term->new_name,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          )));
          break;
      }
      return $term;
    }
    // Cannot find requested term in tree.
    throw new \Exception(t('!vocab => !name does not exist', array(
      '!name' => $term_name,
      '!vocab' => (empty($vocabulary_name)) ? t('Unspecified') : $vocabulary_name,
    )));
  }

  /**
   * Get original term.
   *
   * @param $term_name
   * @param $vocabulary_name
   */
  protected function getOriginalTreeItem($term_name, $vocabulary_name, $tid = '') {

    // Format keys.
    $vocabulary_key = $this->formatKey($vocabulary_name);
    $term_key = $this->formatKey($term_name);

    // Return tree item if it exists.
    if (isset($this->termTree[$vocabulary_key][$term_key])) {

      // if specified tid doesn't exist throw exception
      if (!empty($tid)) {
        if (!isset($this->termTree[$vocabulary_key][$term_key][$tid])) {
          throw new \Exception(t('!tid is not valid for !vocab > !name.', array(
            '!tid' => $tid,
            '!name' => $term_name,
            '!vocab' => $vocabulary_name,
          )));
        }
        // If $tid not empty return tid item
        return $this->termTree[$vocabulary_key][$term_key][$tid];
      }
      // if more than one throw exception
      if (count($this->termTree[$vocabulary_key][$term_key]) > 1) {
            throw new \Exception(t('!vocab > !name is duplicated. Please provide a tid.', array(
            '!name' => $term_name,
            '!vocab' => $vocabulary_name,
          )));
      }

      // else return reset()
      return reset($this->termTree[$vocabulary_key][$term_key]);
    }
  }

  /**
   * Add item to the termTree.
   *
   * @param TermManagerDryRunItem $item
   */
  protected function addTreeItem(TermManagerDryRunItem $item) {
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
   * Output dry run as CSV.
   *
   * @param $file_path
   * @param $delimiter
   */
  protected function outputCSV($file_path, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s', \Drupal::time()->getRequestTime());
    $file_name = preg_replace("/[.](.*)/", "-" . $date . "-dry_run.$1", $file_path);

    // Create managed file and open for writing.
    if (!$file = $this->termServiceManager->dennis_term_manager_open_report($file_name)) {
      return;
    }


    $fp = fopen($file->uri, 'w');

    // Add Headings.
    $columns = array_merge($this->dennis_term_manager_default_columns(), ['description']);
    fputcsv($fp, $columns, $delimiter, '"');

    // Output resulting taxonomy.
    foreach ($this->termTree as $vocabulary_name => $items) {
      foreach ($items as $term_name => $item_array) {
        foreach ($item_array as $item) {
          // Exclude terms that no longer exist.
          $exclude_operations = [
            self::$DENNIS_TERM_MANAGER_ACTION_DELETE,
            self::$DENNIS_TERM_MANAGER_ACTION_MERGE,
            self::$DENNIS_TERM_MANAGER_ACTION_RENAME,
          ];
          if (in_array($item->action, $exclude_operations)) {
            continue 1;
          }
          // Add term to report.
          $row = array();
          foreach ($columns as $key) {
            $row[] = $item->{$key};
          }
          fputcsv($fp, $row, $delimiter, '"');
        }
      }
    }
    fclose($fp);

    // Clear stat cache to get correct filesize.
    clearstatcache(FALSE, $file->uri);
    // Saved managed file.
    $file->save();
  }

  /**
   * @param TermManagerOperationItem $operation
   */
  protected function processOperation(TermManagerOperationItem $operation) {
    switch ($operation->action) {
      case self::$DENNIS_TERM_MANAGER_ACTION_MERGE:
        try {
          $this->merge($operation->term_name, $operation->vocabulary_name, $operation->target_term_name, $operation->target_vocabulary_name, $operation->target_field, $operation->tid, $operation->target_tid);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case self::$DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT:
        try {
          $this->moveParent($operation->term_name, $operation->vocabulary_name, $operation->tid, $operation->target_term_name, $operation->target_tid);
        }
        catch (\Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case self::$DENNIS_TERM_MANAGER_ACTION_CREATE:
        try {
          $this->create($operation->term_name, $operation->vocabulary_name, $operation->parent_term_name);
        }
        catch (\Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case self::$DENNIS_TERM_MANAGER_ACTION_DELETE:
        try {
          $this->delete($operation->term_name, $operation->vocabulary_name, $operation->tid);
        }
        catch (\Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case self::$DENNIS_TERM_MANAGER_ACTION_RENAME:
        try {
          $this->rename($operation->term_name, $operation->vocabulary_name, $operation->new_name, $operation->tid);
        }
        catch (\Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;
    }

    // Only add actions to the operationList.
    // Get original item from term tree.
    try {
      if ($term = $this->getOriginalTreeItem($operation->term_name, $operation->vocabulary_name, $operation->tid)) {
        // Add ID data to operation.
        $operation->tid = $term->tid;
        $operation->vid = $term->vid;
        $operation->target_vid = $term->target_vid;
        $operation->parent_tid = $term->parent_tid;
        if (empty($operation->target_tid)) {
          $operation->target_tid = $term->target_tid;
        }
      }
    }
    catch (\Exception $e) {
      $operation->error = $e->getMessage();
    }

    // Add operationItem to operationList.
    $this->operationList->add($operation);
  }

  /**
   * Asserts that a term is not locked.
   *
   * @param TermManagerDryRunItem $term
   * @throws \Exception
   */
  protected function assertNotLocked(TermManagerDryRunItem $term) {
    if ($term->locked) {
      throw new \Exception(t('!vocab > !name is locked. This may be due to a chained action.', array(
        '!name' => $term->term_name,
        '!vocab' => $term->vocabulary_name,
      )));
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

    $connection = Database::getConnection();

    $query = $connection->select('taxonomy_term_data', 't');

    // Term vocabulary and name.
    $query->addField('v', 'name', 'vocabulary_name');
    $query->addField('t', 'name', 'term_name');

    // Filter by vocabulary
    $machine_names = [];
    if (!empty($vocabs)) {
      foreach ($vocabs as $name) {
        $machine_names[] = $this->dennis_term_manager_machine_name($name);
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
   * Helper to generate machine names.
   *
   * @param $name
   * @return string
   */
  protected function dennis_term_manager_machine_name($name) {
    return strtolower(preg_replace('@[^a-zA-Z0-9_]+@', '_', $name));
  }


  /**
   * CSV/TSV files should always have these columns.
   */
  protected function dennis_term_manager_default_columns() {
    return array(
      'vocabulary_name',
      'term_name',
      'tid',
      'path',
      'node_count',
      'term_child_count',
      'parent_term_name',
      'action',
      'target_term_name',
      'target_tid',
      'target_vocabulary_name',
      'target_field',
      'new_name',
      'redirect',
    );
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
  protected function dennis_term_manager_get_vocabulary($vocabulary_name) {
    // Return static if possible.
    $vocabulary = &drupal_static(__FUNCTION__ . $vocabulary_name, FALSE);
    if ($vocabulary !== FALSE) {
      return $vocabulary;
    }

    $connection = Database::getConnection();
    // Get vocabulary by vocabulary name.
    $query = $connection->select('taxonomy_vocabulary', 'tv');
    $query->fields('tv', array(
      'machine_name',
      'vid',
    ));
    $query->condition('tv.name', $vocabulary_name, '=');
    return $query->execute()->fetchObject();

  }


  /**
   * Helper to count delimiters and see what is the mostly used.
   */
  protected function dennis_term_manager_detect_delimiter($str, $delimiter = NULL) {
    // Get number of rows.
    preg_match_all("/\n/", $str, $matches);
    $rows = count($matches[0]);

    $cur_cnt = 0;
    foreach ($this->dennis_term_manager_get_delimiters() as $key => $value) {
      preg_match_all("/\\$value/", $str, $matches);
      $count = count($matches[0]);

      if ($count > $cur_cnt) {
        $cur_cnt = $count;

        // Only use this delimiter if it happens at least once per row.
        if ($count > $rows) {
          $delimiter = $value;
        }
      }
    }

    return $delimiter;
  }


  /**
   * Helper to return list of delimiters.
   */
  protected function dennis_term_manager_get_delimiters() {
    return [
      'comma' => ",",
      'semicolon' => ";",
      'tab' => "\t",
      'pipe' => "|",
      'colon' => ":",
      'space' => " ",
    ];
  }


  /**
   * Get allowed fields for specified vocabulary.
   *
   * @param $vid
   * @return array|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected  function dennis_term_manager_get_vocabulary_allowed_fields($vid) {
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
      $allowed_vocabularies = $this->dennis_term_manager_get_field_allowed_vocabularies($field_info['field_name']);
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
  protected function dennis_term_manager_get_field_allowed_vocabularies($field_name) {
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



