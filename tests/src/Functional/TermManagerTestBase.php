<?php

namespace Drupal\Tests\dennis_term_manager\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\dennis_term_manager\FileSystem\TermManagerFileSystem;
use Drupal\dennis_term_manager\TermManager;
use Drupal\dennis_term_manager\TermNodeManager;

/**
 * Article tests.
 */
abstract class TermManagerTestBase extends BrowserTestBase {

  use TaxonomyTestTrait;
  use NodeCreationTrait;
  use TestFileCreationTrait;
  use ContentModerationTestTrait;
  use TermManagerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'polaris';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'polaris_drupal_content_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Workflow entity instance.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Term node manager.
   *
   * @var \Drupal\dennis_term_manager\TermNodeManager
   */
  protected $termNodeManager;

  /**
   * Term manager file system.
   *
   * @var \Drupal\dennis_term_manager\FileSystem\TermManagerFileSystem
   */
  protected $termManagerFileSystem;

  /**
   * Term manager service.
   *
   * @var \Drupal\dennis_term_manager\TermManager
   */
  protected $termManager;


  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Database connection instance.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;


  /**
   * File usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected $fileUsage;


  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->state = \Drupal::state();
    $this->state->set('polaris_drupal_content_api_disable', TRUE);
    $this->setContentModerationWorkflow();
    $this->setConstructorArguments();
    $this->termManagerFileSystem = new TermManagerFileSystem($this->database, $this->fileUsage, $this->fileSystem, $this->logger);
    $this->termManager = new TermManager($this->entityTypeManager, $this->logger);
    $this->termNodeManager = new TermNodeManager($this->entityTypeManager, $this->termManager);
  }

  /**
   * Set the arguments to use.
   */
  public function setConstructorArguments() {
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->fileUsage = \Drupal::service('file.usage');
    $this->fileSystem = \Drupal::service('file_system');
    $this->logger = \Drupal::service('logger.factory');
    $this->termManager = $this->getMockBuilder('\Drupal\dennis_term_manager\TermManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->database = $this->getMockBuilder('\Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->state->set('polaris_drupal_content_api_disable', FALSE);
    parent::tearDown();
  }

  /**
   * Set content moderation workflow.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setContentModerationWorkflow() {
    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $this->workflow->save();
  }

}
