<?php
/**
 * @file TermManagerDryRunItem
 */
class TermManagerDryRunItem extends TermManagerItem {
  /**
   * Keep track of this item's child tids.
   */
  protected $childTids = array();

  /**
   * Add tid as child.
   */
  public function addChild($tid) {
    $this->childTids[$tid] = $tid;
  }

  /**
   * Remove child tid.
   */
  public function removeChild($tid) {
    unset($this->childTids[$tid]);
  }

  /**
   * Check if this item is a parent.
   */
  public function isParent() {
    return !empty($this->childTids);
  }
}
