<?php

namespace Drupal\dennis_term_manager;

/**
 * @file TermManagerDryRunItem
 */
class TermManagerDryRunItem extends TermManagerItem {
  /**
   * Keep track of this item's child tids.
   */
  protected $childTids = [];

  /**
   * Add tid as child.
   */
  public function addChild($tid) {
    $this->childTids[$tid] = $tid;
    $this->data['term_child_count'] = count($this->childTids);
  }

  /**
   * Remove child tid.
   */
  public function removeChild($tid) {
    unset($this->childTids[$tid]);
    $this->data['term_child_count'] = count($this->childTids);
  }

  /**
   * Check if this item is a parent.
   */
  public function isParent() {
    return !empty($this->childTids);
  }
}
