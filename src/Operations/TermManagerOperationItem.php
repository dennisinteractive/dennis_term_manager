<?php

namespace Drupal\dennis_term_manager\Operations;


use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;


/**
 * TermManagerOperationItem
 */
class TermManagerOperationItem  {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;



  /**
   * @var EntityFieldManager
   */
  protected $entityFieldManager;


  /**
   * @var EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * TermManagerOperationItem constructor.
   *
   * @param EntityTypeManager $entityTypeManager
   * @param EntityFieldManager $entityFieldManager
   * @param EntityTypeBundleInfo $entityTypeBundleInfo
   * @param LoggerChannelFactoryInterface $loggerFactory
   */
  public function __construct(EntityTypeManager $entityTypeManager,
                              EntityFieldManager $entityFieldManager,
                              EntityTypeBundleInfo $entityTypeBundleInfo,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->logger = $loggerFactory->get('dennis_term_manager');
  }

  /**
   * @param array $term
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function init(array $term) {
    if ($term['node'] == '226087') {
      if ($term['field'] == 'field_primary_category') {
        $this->getFieldSettings($term);
      }
    }
  }

  /**
   * @param $term
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFieldSettings($term) {
    $field_info = FieldStorageConfig::loadByName('node', $term['field']);
    ksm($field_info);
    if ($field_info->getSetting('target_type') == 'taxonomy_term') {
      $bundles = $this->entityTypeBundleInfo->getAllBundleInfo()['node'];
      $this->checkBundles($field_info, $term, $bundles);
    }
  }


  /**
   * @param $term_data
   * @param $bundles
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  /**
   * @param FieldStorageConfig $field_info
   * @param $term_data
   * @param $bundles
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkBundles(FieldStorageConfig $field_info, $term_data, $bundles) {
    if (!empty($bundles)) {
      foreach ($bundles as $id => $value) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $node_field */
        foreach ($this->entityFieldManager->getFieldDefinitions('node', $id) as $node_field) {
          /** @var \Drupal\node\Entity\Node $node */
          if ($node = $this->checkNodeStatus($term_data)) {
           // dsm('we have a node');
            if ($term = $this->getTermFromNodeField($node_field, $term_data)) {
              $node->set($term_data['field'], [$term_data['value']]);
              $node->save();
            }
          } else {
          //  dsm('we do not have a node');
            $field_info->getCardinality();
           // ksm($node_field);
          }
        }
      }
    }
  }

  /**
   * @param  $node_field
   * @param array $term_data
   * @return \Drupal\taxonomy\Entity\Term
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTermFromNodeField($node_field, array $term_data) {
    if (isset($term_data)
      && isset($term_data['field'])
      && isset($node_field)
      && $term_data['field'] == $node_field->getName()
      && $node_field instanceof \Drupal\field\Entity\FieldConfig
      && $node_field->getType() == 'entity_reference') {
      $target_bundles = $node_field->getSettings()['handler_settings']['target_bundles'];
      $vocab = $target_bundles[key($target_bundles)];
      return $this->getTerm($term_data, $vocab);
    }
  }

  /**
   * @param $term_data
   * @param $vocab
   * @return \Drupal\taxonomy\Entity\Term
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTerm($term_data, $vocab) {
    if (isset($term_data)
      && isset($term_data['value'])
      && !empty($vocab)) {
      if ($originalTerm = $this->getExistingTerm($term_data['value'], $vocab)) {
        $term = $originalTerm;
      } else{
        $term = $this->createTerm($term_data['value'], $vocab);
      }
      return $term;
    }
  }


  /**
   * Get original term.
   *
   * @param $term_name
   * @param $vocabulary_name
   * @return \Drupal\Core\Entity\EntityInterface|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getExistingTerm($term_name, $vocabulary_name) {
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
   * @param $termName
   * @param $vocab
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createTerm($termName, $vocab) {
    try {
      $entity = $this->entityTypeManager->getStorage('taxonomy_term');
      try {
        if (empty($entity->loadByProperties(['name' => $termName]))) {
          $term =  $entity->create([
            'name' => $termName,
            'vid' => $vocab,
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


  /**
   * @param array $term_data
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkNodeStatus(array $term_data) {
    if (isset($term_data['node'])) {
      return $this->entityTypeManager->getStorage('node')->load($term_data['node']);
    }
  }

}
