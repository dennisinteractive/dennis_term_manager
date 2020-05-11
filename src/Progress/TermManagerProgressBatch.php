<?php

namespace Drupal\dennis_term_manager\Progress;

use Drupal\file\Entity\File;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\DryRun\TermManagerDryRun;



/**
 * Class TermManagerProgressBatch
 *
 * @package Drupal\dennis_term_manager\Progress
 */
class TermManagerProgressBatch {

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var TermManagerDryRun
   */
  protected $termManagerDryRun;

  /**
   * Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;


  // Elements per operation.
  const LIMIT = 1;

  /**
   * TermManagerProgressBatch constructor.
   *
   * @param Messenger $messenger
   * @param TermManagerDryRun $termManagerDryRun
   */
  public function __construct(Messenger $messenger,
                              TermManagerDryRun $termManagerDryRun) {
    $this->messenger = $messenger;
    $this->termManagerDryRun = $termManagerDryRun;
    $this->batchBuilder = new BatchBuilder();
  }

  /**
   * Prepare a batch definition that will process the file rows.
   *
   * @param File $file
   * @return array
   */
  public function batchInit(File $file) {
    // Dry Run to validate and get operation list.
    $term_data = $this->termManagerDryRun->execute($file);
    $batchProcessCallback = 'Drupal\dennis_term_manager\Progress\TermManagerProgressBatch::dennis_term_manager_queue_operation';
    $finishCallback = 'Drupal\dennis_term_manager\Progress\TermManagerProgressBatch::finished';
    $this->batchBuilder
      ->setTitle(t('Processing'))
      ->setInitMessage(t('Initializing.'))
      ->setProgressMessage(t('Completed @current of @total.'))
      ->setErrorMessage(t('An error has occurred.'))
      ->setFinishCallback($finishCallback);
    // Batch the update to ensure it does not timeout.
    $this->batchBuilder->addOperation($batchProcessCallback , [
        $term_data,
      ]
    );
    return $this->batchBuilder->toArray();
  }

  /**
   * Process operations and pass each to cron queue.
   */
  public static function dennis_term_manager_queue_operation($terms, &$context) {
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
      foreach ($context['sandbox']['items'] as $term) {
        if ($counter != self::LIMIT) {
          self::updateTerms($term);
          $counter++;
          $context['sandbox']['progress']++;
          $context['message'] = t('Now :op index page :progress of :count', [
            ':op' => 'updating',
            ':progress' => $context['sandbox']['progress'],
            ':count' => $context['sandbox']['max'],
          ]);
          // Increment total processed item values. Will be used in finished
          // callback.
          $context['results']['processed'] = $context['sandbox']['progress'];
        }
      }
    }
    // If not finished all tasks, we count percentage of process. 1 = 100%.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function finished($success, $results, $operations) {
    if (!empty($results)) {
      \Drupal::messenger()->addStatus(
        'Number of terms affected by batch: @count',
        [
          '@count' => $results['processed']
        ]
      );
    }
  }


  /**
   * @param array $term
   */
  protected static function updateTerms( array $term) {
    \Drupal::service('dennis_term_manager.operation_item')->init($term);
  }



}
