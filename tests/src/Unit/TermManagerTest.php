<?php

namespace Drupal\Tests\dennis_term_manager\Unit;

use Drupal\dennis_term_manager\TermNodeManager;
use Drupal\dennis_term_manager\Operations\TermManagerBuild;

/**
 * Class TermManagerTest.
 *
 * @package Drupal\Tests\dennis_term_manager\Unit
 *
 * @group polaris
 * @group dennis_term_manager
 */
class TermManagerTest extends TermManagerTestBase {

  /**
   * Term node manager.
   *
   * @var \Drupal\dennis_term_manager\TermNodeManager
   */
  protected $termNodeManager;

  /**
   * Term manager build.
   *
   * @var \Drupal\dennis_term_manager\Operations\TermManagerBuild
   */
  protected $termManagerBuild;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setConstructorArguments();
    $this->termNodeManager = new TermNodeManager($this->entityTypeManager, $this->termManager);
    $this->termManagerBuild = new TermManagerBuild($this->messenger);
  }

  /**
   * Tests getting fields names.
   *
   * @covers \Drupal\dennis_term_manager\TermNodeManager::checkNodeFieldName
   */
  public function testCheckNodeFieldName() {
    $this->assertEquals(TRUE, $this->termNodeManager->checkNodeFieldName('field_primary_category', 'field'));
    $this->assertEquals(FALSE, $this->termNodeManager->checkNodeFieldName('primary_category', 'field'));
  }

  /**
   * Tests default columns.
   *
   * @covers \Drupal\dennis_term_manager\Operations\TermManagerBuild::defaultColumns
   */
  public function testDefaultColumns() {
    $this->assertEquals([
      'node',
      'field',
      'value',
    ], $this->termManagerBuild->defaultColumns());
  }

  /**
   * Tests Detect Delimiter.
   *
   * @covers \Drupal\dennis_term_manager\Operations\TermManagerOperationList::detectDelimiter
   */
  public function testDetectDelimiter() {
    $this->assertEquals(',', $this->operationsList->detectDelimiter(self::$csvString));
  }

}
