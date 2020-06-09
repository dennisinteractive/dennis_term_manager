<?php

namespace Drupal\Tests\dennis_term_manager\Functional;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Language\LanguageInterface;

/**
 * Trait TermManagerTestTrait
 *
 * @package Drupal\Tests\dennis_term_manager\Functional
 */
trait TermManagerTestTrait {

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  protected $node;

  /**
   * @var
   */
  protected $cat1;

  /**
   * @var
   */
  protected $cat2;

  /**
   * @var string
   */
  protected static $vocab_name = 'category';


  /**
   * Create an article for testing.
   */
  protected function createArticleContent() {
    $vocabulary = Vocabulary::load(self::$vocab_name);
    $this->cat1 = $this->createTerm($vocabulary, ['name' => $this->createTestCSVData()[0]['value']]);
    $this->cat2 = $this->createTerm($vocabulary, ['name' => $this->createTestCSVData()[1]['value']]);
    // Create the article.
    $this->node = $this->createNode(
      [
        'type' => 'article',
        'title' => 'Test Article',
        'field_primary_category' => $this->cat1,
      ]
    );
  }

  /**
   * Returns a new vocabulary.
   * @param $vid
   *   Vocabulary vid.
   * @return \Drupal\taxonomy\VocabularyInterface
   *   A vocabulary used for testing.
   * @return \Drupal\Core\Entity\EntityInterface|Vocabulary
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createCustomVocabulary($vid) {
    $vocabulary = Vocabulary::create([
      'name' => $vid,
      'description' => $this->randomMachineName(),
      'vid' => mb_strtolower($vid),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'weight' => mt_rand(0, 10),
    ]);
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * @return array
   */
  public function createTestCSVData() {
    return [
      ['node' => 1, 'field' => 'field_categories', 'value' => 'just eat'],
      ['node' => 1, 'field' => 'field_categories', 'value' => 'scottish mortgage investment trust'],
      ['node' => 2, 'field' => 'field_categories', 'value' => 'this node does not exist'],
    ];
  }
}
