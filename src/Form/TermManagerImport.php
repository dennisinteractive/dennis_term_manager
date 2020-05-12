<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dennis_term_manager\FileSystem\TermManagerFileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dennis_term_manager\Process\TermManagerProcessBatch;

/**
 * Class TermManagerImport
 *
 * @package Drupal\dennis_term_manager\Form
 */
class TermManagerImport extends FormBase {

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * @var TermManagerFileSystemInterface
   */
  protected $termManagerFileSystem;

  /**
   * @var TermManagerProcessBatch
   */
  protected $termManagerProcessBatch;

  /**
   * TermManagerImport constructor.
   *
   * @param Messenger $messenger
   * @param TermManagerFileSystemInterface $termManagerFileSystem
   * @param TermManagerProcessBatch $termManagerProcessBatch
   */
  public function __construct(Messenger $messenger,
                              TermManagerFileSystemInterface $termManagerFileSystem,
                              TermManagerProcessBatch $termManagerProcessBatch) {
    $this->messenger = $messenger;
    $this->termManagerFileSystem = $termManagerFileSystem;
    $this->termManagerProcessBatch = $termManagerProcessBatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('messenger'),
      $container->get('dennis_term_manager.file_system'),
      $container->get('dennis_term_manager.process_batch')
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
    return 'term_manager_import';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $location = $this->termManagerFileSystem->getFilesDir();

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

    if ($last_upload = $this->termManagerFileSystem->getPreviousUpload()) {
      if (isset($last_upload[0])) {
        $form['previos_link'] = [
          '#title' => $this
            ->t('Download Latest CSV'),
          '#type' => 'link',
          '#url' => Url::fromUri(file_create_url($last_upload[0])),
          '#prefix' => '<div><br />',
          '#suffix' => '</div>',
        ];
      }
    }
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
      $this->termManagerFileSystem->saveFile($file);
      // Process the file.
      if ($batch = $this->termManagerProcessBatch->batchInit($file)) {
        batch_set($batch);
      }
    }
  }
}
