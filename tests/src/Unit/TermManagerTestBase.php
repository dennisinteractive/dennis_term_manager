<?php

namespace Drupal\Tests\dennis_term_manager\Unit;

use Drupal\dennis_term_manager\TermManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class TermManagerTestBase.
 *
 * @package Drupal\Tests\dennis_term_manager\Unit
 */
class TermManagerTestBase extends UnitTestCase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Term manager service.
   *
   * @var \Drupal\dennis_term_manager\TermManagerInterface
   */
  protected $termManager;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Operation list.
   *
   * @var \Drupal\dennis_term_manager\Operations\TermManagerOperationList
   */
  protected $operationsList;

  /**
   * String represents the CSV file content.
   *
   * @var string
   */
  protected static $csvString = "node,field,value 226087,field_primary_category,oil 44895,field_primary_category,share tips,226087,field_primary_category,oil price";

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setConstructorArguments();
  }

  /**
   * Sets the arguments required by the constructor.
   */
  protected function setConstructorArguments() {

    $this->entityTypeManager = $this->getMockBuilder(EntityTypeManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->termManager = $this->getMockBuilder(TermManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->messenger = $this->getMockBuilder(Messenger::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->operationsList = new TermManagerOperationList();
  }

}
