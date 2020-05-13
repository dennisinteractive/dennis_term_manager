<?php

namespace Drupal\dennis_term_manager;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Class TermManager
 *
 * @package Drupal\dennis_term_manager
 */
class TermManager implements TermManagerInterface {

  use StringTranslationTrait;

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
      if ($term = $this->getTerm($value, $vocab)) {
        return $this->getTerm($value, $vocab);
      }
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
      if ($terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
        [
          'name' => $term_name,
          'vid' => $vocab_name,
        ]
      )) {
        return reset($terms);
      } else {
        $this->logger->warning($this->t('No term with the name @term_name was found', ['@term_name' => $term_name]));
      }
    }
  }
}
