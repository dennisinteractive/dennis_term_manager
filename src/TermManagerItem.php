<?php

namespace Drupal\dennis_term_manager;

/**
 * Class TermManagerItem
 *
 * @package Drupal\dennis_term_manager
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
          throw new \InvalidArgumentException(t('!value is not a valid action', ['!value' => $value]));
        }
        break;
      case 'node':
      case 'field':
      case 'value':
        break;
      default:
       // throw new \InvalidArgumentException(t('@key is not a valid TermManagerItem property', ['@key' => $key]));
        self::$DENNIS_TERM_MANAGER_ACTION_CREATE;
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
