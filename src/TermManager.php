<?php

namespace Drupal\dennis_term_manager;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Class TermManager
 *
 * @package Drupal\dennis_term_manager
 */
class TermManager implements TermManagerInterface {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * TermManager constructor.
   *
   * @param EntityTypeManager $entityTypeManager
   * @param LoggerChannelFactoryInterface $loggerFactory
   */
  public function __construct(EntityTypeManager $entityTypeManager,
                              LoggerChannelFactoryInterface $loggerFactory) {

    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerFactory->get('dennis_term_manager');
  }

  /**
   * {@inheritdoc}
   *
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTermFromNodeField(FieldConfig $node_config, $field, $value) {
    if (isset($field)
      && isset($node_config)
      && $field == $node_config->getName()
      && $node_config->getType() == 'entity_reference') {
      $target_bundles = $node_config->getSettings()['handler_settings']['target_bundles'];
      $vocab = $target_bundles[key($target_bundles)];
      return $this->getTerm($value, $vocab);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTerm($term_name, $vocab_name) {
    if (!empty($term_name) && !empty($vocab_name)) {
      if ($originalTerm = $this->getExistingTerm($term_name, $vocab_name)) {
        $term = $originalTerm;
      } else{
        $term = $this->createTerm($term_name, $vocab_name);
      }
      return $term;
    }
  }

  /**
   * Get an existing term.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @return \Drupal\Core\Entity\EntityInterface|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getExistingTerm($term_name, $vocabulary_name) {
    if (!empty($term_name) && !empty($vocabulary_name)) {
      if ($terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
        [
          'name' => $term_name,
          'vid' => $vocabulary_name,
        ]
      )) {
        return reset($terms);
      }
    }
  }

  /**
   * Create a new term.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createTerm($term_name, $vocabulary_name) {
    try {
      $entity = $this->entityTypeManager->getStorage('taxonomy_term');
      try {
        if (empty($entity->loadByProperties(['name' => $term_name]))) {
          $term =  $entity->create([
            'name' => $term_name,
            'vid' => $vocabulary_name,
          ]);
          $term->save();
          if (!empty($term)) {
            return $term;
          }
        }
      }
      catch (EntityStorageException $e) {
        $this->logger->error($e->getMessage());
      }
    }
    catch (InvalidPluginDefinitionException $e) {
      $this->logger->error($e->getMessage());
    }
  }
}
