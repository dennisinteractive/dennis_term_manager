<?php

namespace Drupal\Tests\dennis_term_manager\Functional;

/**
 * Class TermManagerTest
 *
 * @package Drupal\Tests\dennis_term_manager\Functional
 *
 * @group polaris
 * @group dennis_term_manager
 */
class TermManagerTest extends TermManagerTestBase {

  /**
   * @var
   */
  protected $node_fields;

  /**
   * @var
   */
  protected $term_data;


  /**
   * @inheritdoc
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createArticleContent();
    $this->term_data = $this->createTestCSVData();
    $this->node_fields = $this->entityFieldManager->getFieldDefinitions('node', $this->node->bundle());
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTermManager() {

   // Drupal\dennis_term_manager\FileSystem\TermManagerFileSystem
    $this->assertEquals("private://term_manager", $this->termManagerFileSystem->getFilesDir());

    // \Drupal\dennis_term_manager\TermNodeManager
    $this->assertInstanceOf('\Drupal\taxonomy\Entity\Term', $this->termManager->getTerm($this->term_data[0]['value'], self::$vocab_name));
    $this->assertInstanceOf('\Drupal\taxonomy\Entity\Term', $this->termManager->getTermFromNodeField($this->node_fields[$this->term_data[0]['field']], $this->term_data[0]['field'], $this->term_data[0]['value']));
    $this->assertEquals(NULL, $this->termManager->getTermFromNodeField($this->node_fields[$this->term_data[2]['field']], $this->term_data[2]['field'], $this->term_data[2]['value']));

    // \Drupal\dennis_term_manager\TermNodeManager
    $node = $this->termNodeManager->checkNodeStatus($this->term_data[0]);
    $this->assertInstanceOf('\Drupal\Core\Entity\EntityInterface', $node);
    $this->assertEquals(NULL, $this->termNodeManager->checkNodeStatus($this->term_data[2]));
    $this->assertEquals(NULL, $this->termNodeManager->checkExistingTermInField($this->node, $this->cat1->id(),'field_categories'));
    $this->assertEquals(NULL, $this->termNodeManager->checkExistingTermInField($this->node, $this->cat2->id(),'field_categories'));
    // Save the node with category
    $this->node->get($this->term_data[1]['field'])->appendItem(['target_id' => $this->cat1->id()]);
    $field_info = $this->termNodeManager->getFieldSettings($this->term_data[0]['field']);
    $node_field = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());
    $this->assertEquals(FALSE, $this->termNodeManager->updateNode($node, $field_info, $node_field, $this->term_data[0]));

    $this->assertEquals(TRUE, $this->termNodeManager->checkExistingTermInField($this->node, $this->cat1->id(),'field_categories'));
    $this->assertEquals(NULL, $this->termNodeManager->checkExistingTermInField($this->node, $this->cat2->id(),'field_categories'));
    $this->assertEquals(TRUE, $this->termNodeManager->checkPrimaryEntityFields($this->node, $this->node_fields, $this->cat1->id()));
    $this->assertEquals(NULL, $this->termNodeManager->checkPrimaryEntityFields($this->node, $this->node_fields, $this->cat2->id()));
    $field_info1 = $this->termNodeManager->getFieldSettings($this->term_data[1]['field']);
    $this->node->get($this->term_data[2]['field'])->appendItem(['target_id' => $this->cat2->id()]);
    $this->assertEquals(TRUE, $this->termNodeManager->updateNode($node, $field_info1, $node_field, $this->term_data[1]));
    $this->assertEquals(TRUE, $this->termNodeManager->checkExistingTermInField($this->node, $this->cat2->id(),'field_categories'));
  }
}
