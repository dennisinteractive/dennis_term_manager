<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dennis_term_manager\Operations\TermManagerExportInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class TermManagerExport extends FormBase {

  /**
   * The Term Manager Export.
   *
   * @var \Drupal\dennis_term_manager\Operations\TermManagerExportInterface
   */
  protected $termManagerExport;

  /**
   * TermManagerExport constructor.
   *
   * @param \Drupal\dennis_term_manager\Operations\TermManagerExportInterface $termManagerExport
   */
  public function __construct(TermManagerExportInterface $termManagerExport) {
    $this->termManagerExport = $termManagerExport;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('dennis_term_manager.export')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'term_manager_export';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['buttons']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Export'),
      '#suffix' => '<span> ' . t('Click the "Export" button to create the csv.') . '</span>',
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->termManagerExport->export();
  }

}
