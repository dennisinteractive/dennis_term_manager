<?php

namespace Drupal\dennis_term_manager\DryRun;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\dennis_term_manager\TermManagerTree;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;

/**
 * Class TermManagerDryRunProcess
 *
 * @package Drupal\dennis_term_manager\DryRun
 */
class TermManagerDryRunProcess {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

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

  public function __construct(EntityTypeManager $entityTypeManager,
                              TermManagerTree $termManagerTree) {
    $this->entityTypeManager = $entityTypeManager;
    $this->termManagerTree = $termManagerTree;
    $this->operationList = new TermManagerOperationList();
  }

  /**
   * @param TermManagerOperationItem $operation
   */
  public function processOperation( $operation) {
    switch ($operation->action) {

      case self::$DENNIS_TERM_MANAGER_ACTION_CREATE:



        $this->checkTermExists($operation->term_name, $operation->vocabulary_name);




        try {
          $this->create($operation->term_name, $operation->vocabulary_name);
        }
        catch (\Exception $e) {
          $operation->error = $e->getMessage();
        }
        break;








    }

    // Only add actions to the operationList.
    // Get original item from term tree.
    try {
      if ($term = $this->termManagerTree->getTerm($operation->term_name, $operation->vocabulary_name, $operation->tid)) {
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
   * Create operation.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @throws \Exception
   */
  protected function create($term_name, $vocabulary_name) {


    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $term_name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vocabulary_name;
    }
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);



    // $parent = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadParents($termId);



    // Check if term already exists.
    if ($this->termManagerTree->getTerm($term_name, $vocabulary_name)) {
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
      throw new \InvalidArgumentException(t('@!vocab is not a valid vocabulary', [
        '@vocab' => $vocabulary_name,
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
    $parents = [];
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


  /**
   * @param $term_name
   * @param $vocabulary_name
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */

  /**
   * @param $term_name
   * @param $vocabulary_name
   * @return \Drupal\Core\Entity\EntityInterface|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function checkTermExists($term_name, $vocabulary_name) {
    if ($term = $this->termManagerTree->getTerm($term_name, $vocabulary_name)) {
      return $term;
    }
  }















}
