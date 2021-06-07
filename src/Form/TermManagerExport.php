<?php

namespace Drupal\dennis_term_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dennis_term_manager\Operations\TermManagerExportInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TermManagerExport.
 *
 * @package Drupal\dennis_term_manager\Form
 */
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
   *   The Term Manager Export.
   */
  public function __construct(TermManagerExportInterface $termManagerExport) {
    $this->termManagerExport = $termManagerExport;
  }

  /**
   * Create the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('dennis_term_manager.export')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'term_manager_export';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['download']['download'] = [
      '#type' => 'submit',
      '#value' => t('Export'),
      '#suffix' => '<span> ' . t('Click the "Export" button to create the csv.') . '</span>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->termManagerExport->export();
  }

}
