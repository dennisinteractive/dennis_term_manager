<?php
/**
 * TermManagerItem
 */
class TermManagerItem {
  protected $data = array();

  /**
   * Setter.
   *
   * - Validates data being set.
   *
   * @param $key
   * @param $value
   * @throws Exception
   */
  public function __set($key, $value) {
    switch ($key) {
      case 'action':
        // Ensure only allowed actions have been specified.
        $allowed_actions = array(
          '', // None
          DENNIS_TERM_MANAGER_ACTION_CREATE,
          DENNIS_TERM_MANAGER_ACTION_DELETE,
          DENNIS_TERM_MANAGER_ACTION_MERGE,
          DENNIS_TERM_MANAGER_ACTION_RENAME,
          DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT,
        );
        if (!in_array($value, $allowed_actions)) {
          // Set the invalid action for error reporting.
          $this->data[$key] = $value;
          throw new Exception(t('!value is not a valid action', array('!value' => $value)));
        }
        break;
      case 'vocabulary_name':
      case 'term_name':
      case 'node_count':
      case 'term_child_count':
      case 'parent_term_name':
      case 'target_term_name':
      case 'target_vocabulary_name':
      case 'target_field':
      case 'new_name':
      case 'description':
      case 'error':
      case 'tid':
      case 'vid':
      case 'target_tid':
      case 'target_vid':
      case 'parent_tid':
        break;
      default:
        throw new Exception(t('!key is not a valid TermManagerItem property', array('!key' => $key)));
    }
    $this->data[$key] = $value;
  }

  /**
   * Getter.
   *
   * @param $key
   */
  public function __get($key) {
    if (isset($this->data[$key])) {
      return $this->data[$key];
    }
  }

  /**
   * Check if data isset.
   *
   * @param $key
   */
  public function __isset($key) {
    return isset($this->data[$key]);
  }

  /**
   * Unset data.
   *
   * @param $key
   */
  public function __unset($key) {
    unset($this->data[$key]);
  }
}
