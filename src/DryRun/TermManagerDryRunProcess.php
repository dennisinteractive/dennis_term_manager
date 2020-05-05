<?php

namespace Drupal\dennis_term_manager\DryRun;


use Drupal\dennis_term_manager\TermManagerTree;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;
use Drupal\dennis_term_manager\Operations\TermManagerOperationItem;

/**
 * Class TermManagerDryRunProcess
 *
 * @package Drupal\dennis_term_manager\DryRun
 */
class TermManagerDryRunProcess {

  /**
   * @var \Drupal\dennis_term_manager\TermManagerTree
   */
  protected $termManagerTree;

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

  public function __construct(TermManagerTree $termManagerTree) {
    $this->termManagerTree = $termManagerTree;
    $this->operationList = new TermManagerOperationList();
  }

  /**
   * @param TermManagerOperationItem $operation
   */
  public function processOperation(TermManagerOperationItem $operation) {
    switch ($operation->action) {
      case self::$DENNIS_TERM_MANAGER_ACTION_MERGE:
        try {
          $this->merge($operation->term_name, $operation->vocabulary_name, $operation->target_term_name, $operation->target_vocabulary_name, $operation->target_field, $operation->tid, $operation->target_tid);
        }
        catch (\Exception $e) {
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
      if ($term = $this->termManagerTree->getOriginalTreeItem($operation->term_name, $operation->vocabulary_name, $operation->tid)) {
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
      throw new \InvalidArgumentException(t('New name for !vocab > !term_name is empty', [
        '!vocab' => $vocabulary_name,
        '!term_name' => $term_name,
      ]));
    }

    // Get term to rename.
    $term = $this->termManagerTree->getTreeItem($term_name, $vocabulary_name, $target_tid);

    // Assert that the new name doesn't already exist.
    if ($existingTerm =  $this->termManagerTree->getOriginalTreeItem($new_name, $vocabulary_name, $target_tid)) {
      // Throw exception if another term exists with the same name or the name isn't being changed.
      if ($existingTerm->tid != $term->tid || $term_name === $new_name) {
        throw new \InvalidArgumentException(t('!vocab > !name already exists', [
          '!vocab' => $vocabulary_name,
          '!name' => $new_name,
        ]));
      }
    }

    // Copy term.
    $renamedTerm = clone $term;
    // Rename term.
    $renamedTerm->term_name = $new_name;
    $renamedTerm->description = t('Renamed from !name', ['!name' => $term_name]);
    // Add renamed term to tree (keyed with new name).
    $this->termManagerTree->addTreeItem($renamedTerm);

    // Update original term with action.
    $term->action = self::$DENNIS_TERM_MANAGER_ACTION_RENAME;
    $term->new_name = $new_name;
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
    $term = $this->termManagerTree->getTreeItem($term_name, $vocabulary_name, $tid);

    // Prevent terms being moved more than once per run.
    if ($term->action == self::$DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT) {
      throw new \InvalidArgumentException(t('!term_name has already been moved to !parent_term_name', [
        '!term_name' => $term->term_name,
        '!parent_term_name' => $term->parent_term_name,
      ]));
    }

    // Get parent and check it's not the same term.
    if (!empty($target_term_name)) {
      $parent_term = $this->termManagerTree->getTreeItem($target_term_name, $vocabulary_name, $target_tid);

      if ($parent_term->tid == $term->tid) {
        throw new \InvalidArgumentException(t('!term cannot be parent of self', [
          '!term' => $term->term_name,
        ]));
      }

      // Get parent's parent.
      $parents = $this->getParents($parent_term);
      // Throw exception if term is is the parent of the new parent.
      if (isset($parents[$term->term_name])) {
        throw new \InvalidArgumentException(t('!term is a parent of !parent', [
          '!term' => $term->term_name,
          '!parent' => $parent_term->term_name,
        ]));
      }

      // Add child to new parent.
      $parent_term->addChild($term->tid);
    }

    // Get current parent.
    if (!empty($term->parent_term_name)) {
      $current_parent_term = $this->termManagerTree->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
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
   * Create operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @param $parent_term_name
   * @throws \Exception
   */
  protected function create($term_name, $vocabulary_name, $parent_term_name) {
    // Check if term already exists.
    if ($this->termManagerTree->getOriginalTreeItem($term_name, $vocabulary_name)) {
      throw new \InvalidArgumentException(t('!vocab > !name already exists', [
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      ]));
    }
    // Assert that parents are valid.
    if (!empty($parent_term_name)) {
      $parent_term = $this->termManagerTree->getTreeItem($parent_term_name, $vocabulary_name);
    }
    // Check that vocabulary is valid.
    $vocabulary = $this->termManagerTree->getVocabulary($vocabulary_name);
    if (empty($vocabulary->vid)) {
      throw new \InvalidArgumentException(t('!vocab is not a valid vocabulary', [
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
    $this->termManagerTree->addTreeItem($term);

    // Add as child of parent item.
    if (!empty($parent_term_name)) {
      $parent_term = $this->termManagerTree->getTreeItem($parent_term_name, $vocabulary_name);
      // Using term name for new children.
      $parent_term->addChild($term_name);
    }
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
    $term = $this->termManagerTree->getTreeItem($term_name, $vocabulary_name, $tid);

    // Assert that the term is not locked.
    $this->assertNotLocked($term);

    // Prevent deleted terms with children.
    if ($term->isParent()) {
      throw new \InvalidArgumentException(t('!vocab > !name cannot be deleted as it has children', [
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      ]));
    }

    // Remove term from parent.
    if (!empty($term->parent_term_name)) {
      $parent_term = $this->termManagerTree->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
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
      throw new \InvalidArgumentException(t('Cannot merge with self, please specify a tid.'));
    }

    // Ensure target term can be retrieved.
    $target_term = $this->termManagerTree->getTreeItem($target_term_name, $target_vocabulary_name, $target_tid);

    if (empty($target_term->tid)) {
      throw new \InvalidArgumentException(t('!vocab > !name has not been created yet', [
        '!vocab' => $target_vocabulary_name,
        '!name' => $target_term_name,
      ]));
    }

    $term = $this->termManagerTree->getTreeItem($term_name, $vocabulary_name, $tid);

    // Assert that the term is not locked.
    $this->assertNotLocked($term);

    // Prevent merging terms with children.
    if ($term->isParent()) {
      throw new \InvalidArgumentException(t('!vocab > !name cannot be merged as it has children', [
        '!vocab' => $vocabulary_name,
        '!name' => $term_name,
      ]));
    }

    // Ensure target vocabulary is allowed in target field.
    if ($target_vocabulary_name != $vocabulary_name) {
      // Get target vocabulary by name.

      $target_vocabulary = $this->termManagerTree->getVocabulary($target_vocabulary_name);
      if (empty($target_field)) {

        $valid_fields = $this->termManagerTree->getVocabularyAllowedFields($target_vocabulary->vid);
        throw new \InvalidArgumentException(t('You must specify a target_field when merge across vocabularies: !valid_fields', [
          '!valid_fields' => implode(', ', $valid_fields),
        ]));
      }
      else {
        // Check that target vocabulary is allowed in the target field.
        $target_fields = array_map('trim', explode(',', $target_field));
        foreach ($target_fields as $field_name) {
          $allowed_vocabularies = $this->termManagerTree->getFieldAllowedVocabularies($field_name);
          if (!isset($allowed_vocabularies[$target_vocabulary->vid])) {
            $valid_fields = $this->termManagerTree->getVocabularyAllowedFields($target_vocabulary->vid);
            throw new \InvalidArgumentException(t('!field cannot contain !vocab terms, please use one of the following: !valid_fields', [
              '!vocab' => $target_vocabulary_name,
              '!field' => $field_name,
              '!valid_fields' => implode(', ', $valid_fields),
            ]));
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
        $parent_term = $this->termManagerTree->getTreeItem($term->parent_term_name, $vocabulary_name, $term->parent_tid);
      }
      else {
        $parent_term = $this->termManagerTree->getTreeItem($term->parent_term_name, $vocabulary_name);
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
   * Get array of parents for specified $term.
   *
   * @param TermManagerDryRunItem $term
   * @return array
   */
  protected function getParents(TermManagerDryRunItem $term) {
    $parents = array();
    try {
      $parent = $this->termManagerTree->getTreeItem($term->parent_term_name, $term->vocabulary_name);
      $parents[$term->parent_term_name] = $parent;
      $parents = array_merge($parents, $this->getParents($parent));
    }
    catch (\Exception $e) {
      // No parent found;
    }
    return $parents;
  }

  /**
   * Asserts that a term is not locked.
   *
   * @param TermManagerDryRunItem $term
   * @throws \Exception
   */
  protected function assertNotLocked(TermManagerDryRunItem $term) {
    if ($term->locked) {
      throw new \InvalidArgumentException(t('!vocab > !name is locked. This may be due to a chained action.', [
        '!name' => $term->term_name,
        '!vocab' => $term->vocabulary_name,
      ]));
    }
  }
}
