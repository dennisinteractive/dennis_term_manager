<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\file\Entity\File;
use Drupal\Core\File\Exception\FileWriteException;
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
use Drupal\dennis_term_manager\Progress\TermManagerProgressBatch;


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
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

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
   * @var TermManagerProgressBatch
   */
  protected $termManagerProgressBatch;

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
   * @param TermManagerProgressBatch $termManagerProgressBatch
   */
  public function __construct(State $state,
                              Messenger $messenger,
                              TermsNodeManager $termsNodeManager,
                              TermManagerService $termManagerService,
                              TermManagerDryRun $termManagerDryRun,
                              TermManagerProgressList $termManagerProgressList,
                              TermManagerProgressItem $termManagerProgressItem,
                              TermManagerProgressBatch $termManagerProgressBatch) {

    $this->state = $state;
    $this->messenger = $messenger;
    $this->termsNodeManager = $termsNodeManager;
    $this->termManagerService = $termManagerService;
    $this->termManagerDryRun = $termManagerDryRun;
    $this->termManagerProgressList = $termManagerProgressList;
    $this->termManagerProgressItem = $termManagerProgressItem;
    $this->termManagerProgressBatch = $termManagerProgressBatch;

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
      $container->get('dennis_term_manager.progress_item'),
      $container->get('dennis_term_manager.progress_batch')
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

    $location = $this->termManagerService->getFilesDir();

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('The CSV file to be processed'),
      '#required' => FALSE,
      '#upload_location' => $location,
      '#default_value' => '',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ]
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

    $file = File::load($form_state->getValue('csv_file')[0]);
    if (!$file) {
      $this->messenger->addError('Please upload the CSV/TSV file first.');
    }
    else {
      // Save the file.
      $this->saveFile($file);
      // Process the file.
      $batch = $this->termManagerProgressBatch->batchInit($file);
      batch_set($batch);
    }
  }

  /**
   * Save the processed file.
   *
   * @param File $file
   * @return File
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveFile(File $file) {
    if (is_object($file)) {
      // Make file permanent.
      $file->status = FILE_STATUS_PERMANENT;
      $file->save();
      // Add file usage.
      $this->termManagerService->fileUsage->add($file, 'dennis_term_manager', 'dennis_term_manager_csv_file', 1);
    }
    else {
      throw new FileWriteException(t('!file_name cannot be saved', [
        '!file_name' => $file->uri,
      ]));
    }
    return $file;
  }
}
