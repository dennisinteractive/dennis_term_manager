<?php
/**
 * @file TermManagerDryRun
 */
class TermManagerDryRun {
  /**
   * Term tree.
   * @var array
   */
  protected $termTree = array();

  /**
   * Keep track of merges to prevent recursion.
   * @var array
   */
  protected $mergeChain = array();

  /**
   * List of operations.
   * @var TermManagerOperationList
   */
  protected $operationList;

  /**
   * Initialise dry run.
   */
  public function __construct(){
    $this->buildTermTree();
    $this->operationList = new TermManagerOperationList();
  }

  /**
   * Build a term tree from the DB.
   */
  protected function buildTermTree() {
    // Get current taxonomy from DB.
    $query = _dennis_term_manager_export_terms_query();
    $result = $query->execute();

    // List of columns to include in tree items.
    $id_columns = array('tid', 'vid', 'target_tid', 'target_vid');
    $columns = array_merge(dennis_term_manager_default_columns(), $id_columns);

    while ($row = $result->fetchObject()) {
      // Add report data to corresponding column.
      $row_data = array();
      $item = new TermManagerDryRunItem();
      foreach ($columns as $column) {
        $item->{$column} = isset($row->{$column}) ? $row->{$column} : '';
      }
      $this->termTree[$row->vocabulary_name][$row->term_name] = $item;
    }
  }

  /**
   * Execute dry run using specified TSV/CSV file.
   *
   * @param $file_path
   */
  public function execute($file_path) {
    // Get file info.
    $file_info = pathinfo($file_path);
    $delimiter = $file_info['extension'] == 'csv' ? ',' : "\t";

    // Detect line endings.
    ini_set('auto_detect_line_endings',TRUE);

    if (($handle = fopen($file_path, "r")) !== FALSE) {
      $heading_row = fgetcsv($handle, 1000, $delimiter);
      $columns = array_flip($heading_row);

      while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Create operationList item.
        $operation = new TermManagerOperationItem();
        foreach (dennis_term_manager_default_columns() as $column) {
          if (isset($columns[$column])) {
            $index = $columns[$column];
            try {
              $operation->{$column} = isset($data[$index]) ? $data[$index] : '';
            }
            catch (Exception $e) {
              $operation->error = $e->getMessage();
            }
          }
        }
        $this->processOperation($operation);
      }
    }
  
    // Display dry run errors to the end user.
    if ($errorList = $this->operationList->getErrorList()) {
      drupal_set_message('Please address the following errors: ', 'error');
      foreach ($errorList as $error) {
        drupal_set_message($error['vocabulary_name'] . ' > ' . $error['term_name'] . ': ' . $error['message'], 'error');
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
   */
  public function create($term_name, $vocabulary_name, $parent_term_name) {
    // Check if term already exists.
    if ($this->getOriginalTerm($term_name, $vocabulary_name)) {
      throw new Exception(t('!vocab > !name already exists', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      )));
    }
    // Assert that parents are valid.
    if (!empty($parent_term_name)) {
      $parent = $this->getTerm($parent_term_name, $vocabulary_name);
      if (empty($parent->tid)) {
        throw new Exception(t('!vocab > !name has not been created yet', array(
          '!vocab' => $vocabulary_name,
          '!name' => $parent_term_name,
        )));
      }
    }
    // Check that vocabulary is valid.
    $vocabulary = _dennis_term_manager_get_vocabulary($vocabulary_name);
    if (empty($vocabulary->vid)) {
      throw new Exception(t('!vocab is not a valid vocabulary', array(
        '!vocab' => $vocabulary_name,
      )));
    }
    // If term doesn't exist and parent is valid, we can safely create.
    $term = new TermManagerDryRunItem();
    $term->term_name = $term_name;
    $term->vid = $vocabulary->vid;
    $term->vocabulary_name = $vocabulary_name;
    $term->parent_term_name = $parent_term_name;
    $term->action = DENNIS_TERM_MANAGER_ACTION_CREATE;
    $this->termTree[$vocabulary_name][$term_name] = $term;
  }

  /**
   * Create operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   */
  public function moveParent($term_name, $vocabulary_name, $parent_term_name) {
    $term = $this->getTerm($term_name, $vocabulary_name);

    // Get parent and check it's not the same term.
    $parent_term = $this->getTerm($parent_term_name, $vocabulary_name);
    if ($parent_term->tid == $term->tid) {
      throw new Exception(t('Cannot be parent of self'));
    }

    // Get parent's parent.
    $parents = $this->getParents($parent_term);
    // Throw exception if term is is the parent of the new parent.
    if (isset($parents[$term->term_name])) {
      throw new Exception(t('!term is a parent of !parent', array(
        '!term' => $term->term_name,
        '!parent' => $parent_term->term_name,
      )));
    }

    // Store parent term data.
    $term->parent_term_name = $parent_term->term_name;
    $term->parent_tid = $parent_term->tid;
    $term->action = DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT;
  }

  /**
   * Get array of parents for specified $term.
   *
   * @param TermManagerDryRunItem $term
   */
  public function getParents(TermManagerDryRunItem $term) {
    $parents = array();
    try {
      $parent = $this->getTerm($term->parent_term_name, $term->vocabulary_name);
      $parents[$term->parent_term_name] = $parent;
      $parents = array_merge($parents, $this->getParents($parent));
    }
    catch (Exception $e) {
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
   */
  public function rename($term_name, $vocabulary_name, $new_name) {
    $term = $this->getTerm($term_name, $vocabulary_name);
    // Assert that the new name doesn't already exist.
    if ($existingTerm =  $this->getOriginalTerm($new_name, $vocabulary_name)) {
      throw new Exception(t('!vocab > !name already exists', array(
        '!vocab' => $vocabulary_name,
        '!name' => $new_name,
      )));
    }
    // Copy term.
    $renamedTerm = clone $term;
    // Rename term.
    $renamedTerm->term_name = $new_name;
    $renamedTerm->description = t('Renamed from %name', array('%name' => $term_name));
    // Add renamed term to tree (keyed with new name).
    $this->termTree[$vocabulary_name][$renamedTerm->term_name] = $renamedTerm;
    // Update original term with action.
    $term->action = DENNIS_TERM_MANAGER_ACTION_RENAME;
    $term->new_name = $new_name;
  }

  /**
   * Delete operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   */
  public function delete($term_name, $vocabulary_name) {
    $term = $this->getTerm($term_name, $vocabulary_name);
    if ($term->node_count > 0) {
      throw new Exception(t('!vocab > !name cannot be deleted as it has !node_count nodes', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
        '!node_count' => $term->node_count,
      )));
    }
    $term->action = DENNIS_TERM_MANAGER_ACTION_DELETE;
  }

  /**
   * Merge operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $target_term_name
   * @param $target_vocabulary_name
   * @throws Exception
   */
  public function merge($term_name, $vocabulary_name, $target_term_name, $target_vocabulary_name) {
    // Prevent merge with self.
    if ($term_name == $target_term_name && $vocabulary_name == $target_vocabulary_name) {
      throw new Exception(t('Cannot merge with self'));
    }
    // Ensure target term can be retrieved.
    $target_term = $this->getTerm($target_term_name, $target_vocabulary_name);
    $term = $this->getTerm($term_name, $vocabulary_name);

    // Update new count on target.
    $target_term->node_count += $term->node_count;
    $term->node_count = 0;

    // Set action.
    $term->action = DENNIS_TERM_MANAGER_ACTION_MERGE;

    // Store target term data.
    $term->target_term_name = $target_term->term_name;
    $term->target_vocabulary_name = $target_term->vocabulary_name;
    $term->target_vid = $target_term->vid;
    $term->target_tid = $target_term->tid;
  }

  /**
   * Get term by vocabulary/name.
   *
   * - Will follow merges and renames.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @throws Exception
   */
  public function getTerm($term_name, $vocabulary_name) {
    // Prevent recursive merges.
    if (isset($this->mergeChain[$vocabulary_name][$term_name])) {
      // Reset merge chain.
      $this->mergeChain = array();
      throw new Exception(t('!vocab > !name is being merged', array(
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      )));
    }
    if (isset($this->termTree[$vocabulary_name][$term_name])) {
      $term = $this->termTree[$vocabulary_name][$term_name];
      switch ($term->action) {
        case DENNIS_TERM_MANAGER_ACTION_DELETE:
          // If term has been marked for delete, it cannot be returned.
          throw new Exception(t('!vocab > !name has been flagged for !operation', array(
            '!operation' => $term->action,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          )));
          break;
        case DENNIS_TERM_MANAGER_ACTION_MERGE:
          // Follow merge and return target.
          $this->mergeChain[$vocabulary_name][$term_name] = $term;
          $target_term = $this->getTerm($term->target_term_name, $term->target_vocabulary_name);
          return $target_term;
          break;
        case DENNIS_TERM_MANAGER_ACTION_RENAME:
          // If term has been marked for rename, it cannot be returned.
          throw new Exception(t('!vocab > !name has been renamed to !new_name', array(
            '!new_name' => $term->new_name,
            '!name' => $term->term_name,
            '!vocab' => $term->vocabulary_name,
          )));
          break;
      }
      return $this->termTree[$vocabulary_name][$term_name];
    }
    // Cannot find requested term in tree.
    throw new Exception(t('!vocab => !name does not exist', array(
      '!name' => $term_name,
      '!vocab' => $vocabulary_name
    )));
  }

  /**
   * Get original term.
   *
   * @param $term_name
   * @param $vocabulary_name
   */
  public function getOriginalTerm($term_name, $vocabulary_name) {
    if (isset($this->termTree[$vocabulary_name][$term_name])) {
      return $this->termTree[$vocabulary_name][$term_name];
    }
  }

  /**
   * Output dry run as CSV.
   *
   * @param $file_path
   * @param $delimiter
   */
  public function outputCSV($file_path, $delimiter) {
    // Output dry run taxonomy.
    $date = date('Y-m-d_H-i-s');
    $file_name = preg_replace("/[.](.*)/", "-" . $date . "-dry_run.$1", $file_path);
    $fp = fopen($file_name, 'w');
    $columns = array_merge(dennis_term_manager_default_columns(), array('description'));
    fputcsv($fp, $columns, $delimiter, '"');

    // Output resulting taxonomy.
    foreach ($this->termTree as $vocabulary_name => $items) {
      foreach ($items as $term_name => $item) {
        $exclude_operations = array(
          DENNIS_TERM_MANAGER_ACTION_DELETE,
          DENNIS_TERM_MANAGER_ACTION_MERGE,
          DENNIS_TERM_MANAGER_ACTION_RENAME,
        );
        if (in_array($item->action, $exclude_operations)) {
          continue 1;
        }
        $row = array();
        foreach ($columns as $key) {
          $row[] = $item->{$key};
        }
        fputcsv($fp, $row, $delimiter, '"');
      }
    }
    fclose($fp);
  
    // Create managed file to show in report list.
    $file = new stdClass();
    $file->filename = basename($file_name);
    $file->status = 1;
    $file->uri = $file_name;
    file_save($file);
  }

  /**
   * @param TermManagerOperationItem $operation
   */
  public function processOperation(TermManagerOperationItem $operation) {
    switch ($operation->action) {
      case DENNIS_TERM_MANAGER_ACTION_MERGE:
        try {
          $this->merge($operation->term_name, $operation->vocabulary_name, $operation->target_term_name, $operation->target_vocabulary_name);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT:
        try {
          $this->moveParent($operation->term_name, $operation->vocabulary_name, $operation->parent_term_name);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case DENNIS_TERM_MANAGER_ACTION_CREATE:
        try {
          $this->create($operation->term_name, $operation->vocabulary_name, $operation->parent_term_name);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case DENNIS_TERM_MANAGER_ACTION_DELETE:
        try {
          $this->delete($operation->term_name, $operation->vocabulary_name);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;

      case DENNIS_TERM_MANAGER_ACTION_RENAME:
        try {
          $this->rename($operation->term_name, $operation->vocabulary_name, $operation->new_name);
        }
        catch (Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;
    }
    // Only add actions to the operationList.
    if (!empty($operation->action)) {
      // Get original item from term tree.
      if (isset($this->termTree[$operation->vocabulary_name][$operation->term_name])) {
        $term = $this->termTree[$operation->vocabulary_name][$operation->term_name];
  
        // Add ID data to operation.
        $operation->tid = $term->tid;
        $operation->vid = $term->vid;
        $operation->target_vid = $term->target_vid;
        $operation->target_tid = $term->target_tid;
        $operation->parent_tid = $term->parent_tid;
        $operation->parent_term_name = $term->parent_term_name;
      }

      // Add operationItem to operationList.
      $this->operationList->add($operation);
    }
  }
}
