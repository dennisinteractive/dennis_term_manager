<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\Core\State\State;
use Drupal\Core\Form\FormBase;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dennis_term_manager\TermsNodeManager;
use Drupal\dennis_term_manager\TermManagerDryRun;
use Drupal\dennis_term_manager\TermManagerService;
use Drupal\dennis_term_manager\TermManagerProgressList;
use Drupal\dennis_term_manager\TermManagerProgressItem;
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
   * @var FileSystem
   */
  protected $fileSystem;


  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  public static $DENNIS_TERM_MANAGER_PRIVATE_FOLDER = 'private://term_manager';

  public static $DENNIS_TERM_MANAGER_PUBLIC_FOLDER = 'public://term_manager';

  /**
   * TermManager constructor.
   *
   * @param FileSystem $fileSystem
   * @param State $state
   * @param Messenger $messenger
   * @param TermsNodeManager $termsNodeManager
   * @param TermManagerService $termManagerService
   */
  public function __construct(FileSystem $fileSystem,
                              State $state,
                              Messenger $messenger,
                              TermsNodeManager $termsNodeManager,
                              TermManagerService $termManagerService) {

    $this->fileSystem = $fileSystem;
    $this->state = $state;
    $this->messenger = $messenger;
    $this->termsNodeManager = $termsNodeManager;
    $this->termManagerService = $termManagerService;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('file_system'),
      $container->get('state'),
      $container->get('messenger'),
      $container->get('dennis_term_manager.node_manager'),
      $container->get('dennis_term_manager.service')
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
    $progress = new TermManagerProgressList();
    foreach ($progress as $progress_item) {
      $progress_item->displayStatus();
    }

    $location = $this->dennis_term_manager_get_files_folder();

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
    $file = \Drupal\file\Entity\File::load($form_state->getValue('csv_file'));
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
   * Helper to retrieve the files folder.
   */
  protected function dennis_term_manager_get_files_folder() {
    // Store CSV/TSV files and reports in private file location if available.
    if (\Drupal::config('file_private_path')) {
      $location = self::$DENNIS_TERM_MANAGER_PRIVATE_FOLDER;
    }
    else {
      $location = self::$DENNIS_TERM_MANAGER_PUBLIC_FOLDER;
      // Warn user that files will be publicly accessible.
      $config_link = l(
        'Please configure the private file system path to store report privately',
        'admin/config/media/file-system'
      );
      $warning_message = t('Files will be stored in the public file directory. !config_link', array('!config_link' => $config_link));

      $this->messenger->addWarning($warning_message);
    }


    $file_system = \Drupal::service('file_system');
    // Test if folder exists and try to create it if necessary.
    if (!is_dir($location) && !$file_system->mkdir($location, NULL, TRUE)) {
      \Drupal::logger('file system')->warning('The directory %directory does not exist and could not be created.', ['%directory' => $location]);
    }
    if (is_dir($location) && !is_writable($location) && !$this->fileSystem->chmod($location)) {
      // If the directory is not writable and cannot be made so.
      \Drupal::logger('file system')->warning('The directory %directory exists but is not writable and could not be made writable.', ['%directory' => $location]);
    }
    elseif (is_dir($location)) {
      // Create private .htaccess file.
      \Drupal\Component\FileSecurity\FileSecurity::writeHtaccess($location);
      return $location;
    }

    throw new \Exception(t('Error trying to copy files to !name folder. Make sure the folder exists and you have writting permission.', array(
      '!name' => $location,
    )));
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
    $dryRun = new TermManagerDryRun();
    $dryRun->execute($file->uri);

    $operationList = $dryRun->getOperationList();

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
    $progress_item = new TermManagerProgressItem($file->fid);
    $progress_item->setReportFid($report_file->fid);
    $progress_item->setOffsetQueueId();
    $progress_item->save();

    // Get a list of nodes that are not published but are scheduled to be published.

    $unpublished_sheduled_nodes = $this->termsNodeManager->getScheduledNodes();
    // Get list of tids vs nodes. This is used to queue nodes that have any of the tids used by actions.
    $extra_nodes = $this->termsNodeManager->listNodeTids($unpublished_sheduled_nodes);

    // Add each operation to the batch.
    $operations = array();
    foreach ($operationList as $i => $operationItem) {

      $options = array(
        'operation_item' => $operationItem,
        'report_fid' => $report_file->fid,
        'row' => $i,
        'extra_nodes' => $extra_nodes,
      );

      $operations[] = array(
        'dennis_term_manager_queue_operation',
        array($options),
      );
    }

    // Set final queue operation.
    $operations[] = array(
      'dennis_term_manager_queue_operation_complete',
      array(
        array(
          'fid' => $file->fid,
          'report_fid' => $report_file->fid,
        )
      ),
    );

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
