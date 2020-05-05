<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\file\Entity\File;
use Drupal\Core\State\State;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dennis_term_manager\TermsNodeManager;
use Drupal\dennis_term_manager\DryRun\TermManagerDryRun;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\Progress\TermManagerProgressList;
use Drupal\dennis_term_manager\Progress\TermManagerProgressItem;
use Symfony\Component\DependencyInjection\ContainerInterface;




/**
 * Class TermManager
 *
 * @package Drupal\polaris_drupal_content_api\Form
 */
class TermManager extends FormBase {

  /**
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * @var
   */
  protected $queueData;

  /**
   * @var TermsNodeManager
   */
  protected $termsNodeManager;

  /**
   * @var TermManagerService
   */
  protected $termManagerService;

  /**
   * @var TermManagerDryRun
   */
  protected $termManagerDryRun;

  /**
   * @var TermManagerProgressList
   */
  protected $termManagerProgressList;

  /**
   * @var TermManagerProgressItem
   */
  protected $termManagerProgressItem;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;


  /**
   * TermManager constructor.
   *
   * @param State $state
   * @param Messenger $messenger
   * @param TermsNodeManager $termsNodeManager
   * @param TermManagerService $termManagerService
   * @param TermManagerDryRun $termManagerDryRun
   * @param TermManagerProgressList $termManagerProgressList
   * @param TermManagerProgressItem $termManagerProgressItem
   */
  public function __construct(State $state,
                              Messenger $messenger,
                              TermsNodeManager $termsNodeManager,
                              TermManagerService $termManagerService,
                              TermManagerDryRun $termManagerDryRun,
                              TermManagerProgressList $termManagerProgressList,
                              TermManagerProgressItem $termManagerProgressItem) {

    $this->state = $state;
    $this->messenger = $messenger;
    $this->termsNodeManager = $termsNodeManager;
    $this->termManagerService = $termManagerService;
    $this->termManagerDryRun = $termManagerDryRun;
    $this->termManagerProgressList = $termManagerProgressList;
    $this->termManagerProgressItem = $termManagerProgressItem;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('state'),
      $container->get('messenger'),
      $container->get('dennis_term_manager.node_manager'),
      $container->get('dennis_term_manager.service'),
      $container->get('dennis_term_manager.dry_run'),
      $container->get('dennis_term_manager.progess_list'),
      $container->get('dennis_term_manager.progress_item')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'polaris_content_api_form';
  }

  /**
   * @param $queueData
   * @return $this
   */
  function setQueueData($queueData) {
    $this->queueData = $queueData;
    return $this;
  }

  /**
   * @return array
   */
  public function getQueueData() {
    return $this->queueData;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    // Display current process.
    /** @var \Drupal\dennis_term_manager\Progress\TermManagerProgressList $progress */
    $progress = $this->termManagerProgressList;

    foreach ($progress as $progress_item) {
      $progress_item->displayStatus();
    }

    $location = $this->termManagerService->dennis_term_manager_get_files_folder();

    $form['csv_file'] = [
      '#title' => t('Import'),
      '#type' => 'managed_file',
      '#description' => t('The CSV file to be processed.'),
      '#upload_validators' => ['file_validate_extensions' => ['csv tsv']],
      '#upload_location' => $location,
    ];

    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Import'),
      '#suffix' => '<span> ' . t('Click the "Import" button to process the file.') . '</span>',
    ];
    $form['from'] = [
      '#type' => 'item',
      '#prefix' => '<div><br /></div>',
      '#title' => t('Reports'),
      '#markup' => views_embed_view('csv_report'),
    ];

    $form['#attached']['library'][] = 'polaris_drupal_taxonomy/polaris_drupal_taxonomy_edit_form';

    return $form;
  }

  /**
   * Callback function for form submission.
   *
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load the file.
    $file = File::load($form_state->getValue('csv_file'));
    if (!$file) {
      $this->messenger->addError('Please upload the CSV/TSV file first.');
    }
    else {
      // Save the file.
      $this->dennis_term_manager_file_save($file);
      // Process the file.
      $batch = $this->dennis_term_manager_batch_init($file);

      batch_set($batch);
    }
  }




  /**
   * Helper to save the csv/tsv files and add an entry to the file usage table.
   *
   * @param $file
   *    The file object to be saved.
   * @return mixed
   * @throws \Exception
   */
  protected function dennis_term_manager_file_save($file) {
    if (is_object($file)) {

      // Make file permanent.
      $file->status = FILE_STATUS_PERMANENT;
      $file->save();
      // Add file usage.
      $file_usage = \Drupal::service('file.usage');
      $file_usage->add($file, 'dennis_term_manager', 'dennis_term_manager_csv_file', 1);


    }
    else {
      throw new \Exception(t('!file_name cannot be saved', array(
        '!file_name' => $file->uri,
      )));
    }

    return $file;
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
  protected function dennis_term_manager_batch_init($file) {
    // Dry Run to validate and get operation list.
    $this->termManagerDryRun->execute($file->uri);

    $operationList = $this->termManagerDryRun->getOperationList();

    // Prevent batch if there are no operation items.
    if (count($operationList) == 0) {
      $this->messenger()->addError('There were no valid operations');
      return;
    }

    if ($operationList->getErrorList()) {
      // Halt batch.
      return;
    }

    // Create file for reporting error.
    // - Use the same file name and change extenstion.
    $date = date('Y-m-d_H-i-s', \Drupal::time()->getRequestTime());

    $report_file_name = preg_replace("/[.](.*)/", "-" . $date . "-report.txt", $file->uri);
    if (!$report_file = $this->termManagerService->dennis_term_manager_open_report($report_file_name)) {
      return;
    }

    // Add file in progress.
    /** @var \Drupal\dennis_term_manager\TermManagerProgressItem $progress_item */
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
    foreach ($operationList as $i => $operationItem) {

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
