<?php

namespace Drupal\dennis_term_manager\Progress;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Messenger\Messenger;
use Drupal\dennis_term_manager\TermsNodeManager;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\DryRun\TermManagerDryRun;
use Drupal\dennis_term_manager\Operations\TermManagerOperationList;
use Drupal\dennis_term_manager\Operations\TermManagerOperationItem;


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
   * @var Time
   */
  protected $time;

  /**
   * @var TermManagerService
   */
  protected $termManagerService;

  /**
   * @var TermManagerDryRun
   */
  protected $termManagerDryRun;

  /**
   * @var TermManagerOperationList
   */
  protected $operationList;

  /**
   * @var TermManagerOperationItem
   */
  protected $operationsItem;

  /**
   * @var TermManagerProgressItem
   */
  protected $termManagerProgressItem;

  /**
   * @var TermsNodeManager
   */
  protected $termsNodeManager;

  /**
   * TermManagerProgressBatch constructor.
   *
   * @param Messenger $messenger
   * @param Time $time
   * @param TermManagerService $termManagerService
   * @param TermManagerDryRun $termManagerDryRun
   * @param TermManagerProgressItem $termManagerProgressItem
   * @param TermsNodeManager $termsNodeManager
   */
  public function __construct(Messenger $messenger,
                              Time $time,
                              TermManagerService $termManagerService,
                              TermManagerDryRun $termManagerDryRun,
                              TermManagerProgressItem $termManagerProgressItem,
                              TermsNodeManager $termsNodeManager) {
    $this->messenger = $messenger;
    $this->time = $time;
    $this->termManagerService = $termManagerService;
    $this->termManagerDryRun = $termManagerDryRun;
    $this->termManagerProgressItem = $termManagerProgressItem;
    $this->termsNodeManager = $termsNodeManager;
    $this->operationList = new TermManagerOperationList();
  }

  /**
   * Prepare a batch definition that will process the file rows.
   *
   * @param $file
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function batchInit($file) {
    // Dry Run to validate and get operation list.
    $this->termManagerDryRun->execute($file->uri);

    $operation_list = $this->termManagerDryRun->getOperationList();

    // Prevent batch if there are no operation items.
    if (count($operation_list) == 0) {
      $this->messenger->addError('There were no valid operations');
      return;
    }

    if ($operation_list->getErrorList()) {
      // Halt batch.
      return;
    }
    // Create file for reporting error.
    // - Use the same file name and change extenstion.
    $date = date('Y-m-d_H-i-s', $this->time->getRequestTime());
    $report_file_name = preg_replace("/[.](.*)/", "-" . $date . "-report.txt", $file->uri);
    if (!$report_file = $this->termManagerService->openReport($report_file_name)) {
      return;
    }

    // Add file in progress.
    /** @var \Drupal\dennis_term_manager\Progress\TermManagerProgressItem $progress_item */
    $progress_item = $this->termManagerProgressItem;
    $progress_item->init($file->fid);
    $progress_item->setReportFid($report_file->fid);
    $progress_item->setOffsetQueueId();
    $progress_item->save();
    // Get a list of nodes that are not published but are scheduled to be published.
    $unpublished_sheduled_nodes = $this->termsNodeManager->getScheduledNodes();
    // Get list of tids vs nodes. This is used to queue nodes that have any of the tids used by actions.
    $extra_nodes = $this->termsNodeManager->listNodeTids($unpublished_sheduled_nodes);
    // Add each operation to the batch.
    $operations = [];
    foreach ($operation_list as $i => $operationItem) {

      $options = [
        'operation_item' => $operationItem,
        'report_fid' => $report_file->fid,
        'row' => $i,
        'extra_nodes' => $extra_nodes,
      ];

      $operations[] = [
        'dennis_term_manager_queue_operation',
        [$options],
      ];
    }

    // Set final queue operation.
    $operations[] = [
      'dennis_term_manager_queue_operation_complete',
      [
        [
          'fid' => $file->fid,
          'report_fid' => $report_file->fid,
        ]
      ],
    ];

    return [
      'operations' => $operations,
      //'finished' => 'batch_dennis_term_manager_finished',
      'title' => t('Processing operations'),
      'init_message' => t('Process is starting.'),
      'progress_message' => t('Processed @current out of @total steps.'),
      'error_message' => t('Batch has encountered an error.'),
    ];
  }
}
