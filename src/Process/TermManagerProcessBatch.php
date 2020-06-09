<?php

namespace Drupal\dennis_term_manager\Process;

use Drupal\file\Entity\File;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\Operations\TermManagerBuildInterface;

/**
 * Class TermManagerProcessBatch
 *
 * @package Drupal\dennis_term_manager\Process
 */
class TermManagerProcessBatch {

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var TermManagerBuildInterface
   */
  protected $termManagerBuild;

  /**
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  // Elements per operation.
  const LIMIT = 1;

  /**
   * TermManagerProcessBatch constructor.
   *
   * @param Messenger $messenger
   * @param TermManagerBuildInterface $termManagerBuild
   */
  public function __construct(Messenger $messenger,
                              TermManagerBuildInterface $termManagerBuild) {
    $this->messenger = $messenger;
    $this->termManagerBuild = $termManagerBuild;
    $this->batchBuilder = new BatchBuilder();
  }

  /**
   * {@inheritdoc}
   */
  public function batchInit(File $file) {
    // Dry Run to validate and get operation list.
    $term_data = $this->termManagerBuild->execute($file);

    $batchProcessCallback = 'Drupal\dennis_term_manager\Process\TermManagerProcessBatch::termManagerQueueOperation';
    $finishCallback = 'Drupal\dennis_term_manager\Process\TermManagerProcessBatch::finished';
    $this->batchBuilder
      ->setTitle(t('Processing'))
      ->setInitMessage(t('Initializing.'))
      ->setProgressMessage(t(''))
      ->setErrorMessage(t('An error has occurred.'))
      ->setFinishCallback($finishCallback);
    // Batch the update to ensure it does not timeout.
    $this->batchBuilder->addOperation($batchProcessCallback , [
        $file,
        $term_data,
      ]
    );
    return $this->batchBuilder->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public static function termManagerQueueOperation(File $file, array $terms, &$context) {
    // Drush issue https://github.com/drush-ops/drush/issues/1930
    if (is_object($context) && $context instanceof \DrushBatchContext) {
      return;
    }
    // Set default progress values.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($terms);
    }
    // Save items to array which will be changed during processing.
    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $terms;
    }
    $counter = 0;

    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, self::LIMIT);
      }
      $process_items = \Drupal::service('dennis_term_manager.process_item');
      foreach ($context['sandbox']['items'] as $term) {
        if ($counter != self::LIMIT) {
          if ($process_items->init($term)) {
            $counter++;
            $context['results']['success']++;
          }
          $context['sandbox']['progress']++;
          if ($context['sandbox']['progress'] <= $context['sandbox']['max']) {
            $context['message'] = t('Now :op item :progress of :count', [
              ':op' => 'processing',
              ':progress' => $context['sandbox']['progress'],
              ':count' => $context['sandbox']['max'],
            ]);
          }
        }
      }
    }
    // If not finished all tasks, we count percentage of process. 1 = 100%.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    $context['results']['file'] = $file->getFileUri();
  }

  /**
   * {@inheritdoc}
   */
  public static function finished($success, $results, $operations) {
    if (!empty($results)) {
      \Drupal::messenger()->addStatus(t(
        'Number of nodes updated by batch: @count',
        [
          '@count' => $results['success']
        ])
      );
    }
  }
}
