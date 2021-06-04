<?php

namespace Drupal\Tests\dennis_term_manager\Unit;

use Drupal\dennis_term_manager\Operations\TermManagerExport;
use Drupal\Tests\UnitTestCase;

/**
 * Class TermManagerExportTest
 *
 * @package Drupal\Tests\dennis_term_manager\Unit
 * @coversDefaultClass Drupal\dennis_term_manager\Operations\TermManagerExport
 *
 * @group polaris
 */
class TermManagerExportTest extends UnitTestCase {

  /**
   * @var \Drupal\dennis_term_manager\Operations\TermManagerExport
   */
  protected $termManagerExport;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->connection = $this->getMockBuilder('Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();

    $query = $this->getMockBuilder('Drupal\Core\Database\Driver\mysql\Select')
      ->disableOriginalConstructor()
      ->getMock();

    $this->connection->expects($this->any())
      ->method('select')
      ->willReturn($query);

    $this->termManagerExport = new TermManagerExport($this->connection);
  }

  /**
   * Test default columns.
   *
   * @covers \Drupal\dennis_term_manager\Operations\TermManagerExport::getColumns()
   */
  public function testGetColumns() {
   $columns = [
     'vocabulary_name',
     'term_name',
     'tid',
     'path',
     'parent_term_name',
     'index_page',
     'primary_article_nid',
     'node_count',
     'node_count_with_children'
   ];
   $this->assertEquals($columns, $this->termManagerExport->getColumns());
  }

  /**
   * Test index page function.
   *
   * @covers \Drupal\dennis_term_manager\Operations\TermManagerExport::getIndexPage()
   */
  public function testGetIndexPage() {
    $row = $this->getRow();

    // Check when it is an index page.
    $this->assertEquals('Y', $this->termManagerExport->getIndexPage($row));

    // Check when it isn't an index page.
    $row->index_page = '';
    $this->assertEquals('N', $this->termManagerExport->getIndexPage($row));
  }

  /**
   * Test query function.
   *
   * @covers \Drupal\dennis_term_manager\Operations\TermManagerExport::query()
   */
  public function testQuery() {
    $this->assertInstanceOf('Drupal\Core\Database\Driver\mysql\Select', $this->termManagerExport->query());
  }

  /**
   * Get mock row.
   *
   * @return \stdClass
   */
  public function getRow() {
    $row = new \stdClass();
    $row->vocabulary_name = 'article_type';
    $row->term_name = 'Articles';
    $row->tid = 1;
    $row->path = '/articles';
    $row->parent_term_name = 'Articles';
    $row->index_page = 1;
    $row->primary_article_nid = 1;
    $row->node_count = 641;

    return $row;
  }

}
