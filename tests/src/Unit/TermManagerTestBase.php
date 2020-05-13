<?php


namespace Drupal\Tests\dennis_term_manager\Unit;

use Drupal\Tests\UnitTestCase;

use Drupal\dennis_term_manager\Operations\TermManagerOperationList;


/**
 * Class TermManagerTestBase
 *
 * @package Drupal\Tests\dennis_term_manager\Unit
 */
class TermManagerTestBase extends UnitTestCase {

  /**
   * @var
   */
  protected $entityTypeManager;

  /**
   * @var
   */
  protected $termManager;
  /**
   * @var
   */
  protected $messenger;

  /**
   * @var \Drupal\dennis_term_manager\Operations\TermManagerOperationList
   */
  protected $operationsList;


  protected static $csvString = "node,field,value 226087,field_primary_category,oil 44895,field_primary_category,share tips,226087,field_primary_category,oil price";

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setConstructorArguments();
  }

  /**
   * Sets the arguments required by the constructor.
   */
  protected function setConstructorArguments() {

    $this->entityTypeManager = $this->getMockBuilder('\Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->termManager = $this->getMockBuilder('\Drupal\dennis_term_manager\TermManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->messenger = $this->getMockBuilder('\Drupal\Core\Messenger\Messenger')
      ->disableOriginalConstructor()
      ->getMock();

    $this->operationsList = new TermManagerOperationList();
  }

}
