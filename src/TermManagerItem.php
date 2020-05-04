<?php

namespace Drupal\dennis_term_manager;

/**
 * TermManagerItem
 */
class TermManagerItem {
  protected $data = [];

  static $DENNIS_TERM_MANAGER_ACTION_CREATE = 'create';
  static $DENNIS_TERM_MANAGER_ACTION_DELETE  = 'delete';
  static $DENNIS_TERM_MANAGER_ACTION_MERGE  = 'merge';
  static $DENNIS_TERM_MANAGER_ACTION_RENAME  = 'rename';
  static $DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT  = 'move parent';

  /**
   * Setter.
   *
   * - Validates data being set.
   *
   * @param $key
   * @param $value
   * @throws \Exception
   */
  public function __set($key, $value) {
    switch ($key) {
      case 'action':
        // Ensure only allowed actions have been specified.
        $allowed_actions = array(
          '', // None
          self::$DENNIS_TERM_MANAGER_ACTION_CREATE,
          self::$DENNIS_TERM_MANAGER_ACTION_DELETE,
          self::$DENNIS_TERM_MANAGER_ACTION_MERGE,
          self::$DENNIS_TERM_MANAGER_ACTION_RENAME,
          self::$DENNIS_TERM_MANAGER_ACTION_MOVE_PARENT,
        );
        if (!in_array($value, $allowed_actions)) {
          // Set the invalid action for error reporting.
          $this->data[$key] = $value;
          throw new \Exception(t('!value is not a valid action', ['!value' => $value]));
        }
        break;
      case 'redirect':
        // Array of allowed redirect values.
        $allowed_redirect_values = [
          '301',
          'y',
          'n',
        ];

        // Lowercase incoming values.
        $value = strtolower($value);

        // Convert 'y' and empty values to 301 by default.
        if ($value == 'y' || empty($value)) {
          $value = '301';
        }

        // Error if the redirect value is not valid.
        if (!in_array($value, $allowed_redirect_values)) {
           throw new \Exception(t('!value is not a valid redirect value. The following values are allowed: !allowed', [
            '!value' => $value,
            '!allowed' => '"' . implode('", "', $allowed_redirect_values) . '"',
           ]));
        }
        $this->data[$key] = $value;
        break;
      case 'vocabulary_name':
      case 'term_name':
      case 'node_count':
      case 'path':
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
      case 'locked':
        break;
      default:
        throw new \Exception(t('!key is not a valid TermManagerItem property', array('!key' => $key)));
    }
    $this->data[$key] = $value;
  }

  /**
   * Getter.
   *
   * @param $key
   * @return mixed
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
   * @return bool
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
