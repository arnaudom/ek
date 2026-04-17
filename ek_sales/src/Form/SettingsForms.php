<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsForms.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_sales\SalesSettings;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to manage sales templates.
 */
class SettingsForms extends FormBase {

  /**
   * Sales settings wrapper.
   *
   * @var \Drupal\ek_sales\SalesSettings
   */
  protected $settings;

  /**
   * Template settings array.
   *
   * @var array
   */
  protected $tpls = [];

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;

    $this->settings = new SalesSettings();
    $this->tpls = (array) $this->settings->get('templates');

    // Ensure expected keys exist.
    $this->tpls += [
      'purchase' => [],
      'quotation' => [],
      'invoice' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_sales_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    // Ensure directories exist before upload widgets are used.
    $this->prepareTemplateDirectory('purchase');
    $this->prepareTemplateDirectory('quotation');
    $this->prepareTemplateDirectory('invoice');

    // Purchase section.
    $form['p'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase forms'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['p']['new_purchase'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload purchase template'),
      '#description' => $this->t('Only files with a ".inc" extension are allowed.'),
      '#upload_location' => 'private://sales/templates/purchase/',
      '#upload_validators' => [
        'file_validate_extensions' => ['inc'],
      ],
      '#multiple' => FALSE,
    ];

    if (!empty($this->tpls['purchase'])) {
      foreach ($this->tpls['purchase'] as $i => $name) {
        $form['p']['template_purchase_' . $i] = [
          '#type' => 'checkbox',
          '#default_value' => 0,
          '#return_value' => $name,
          '#attributes' => ['title' => $this->t('delete')],
          '#title' => $this->t('Delete purchase template <b>"@n"</b>', ['@n' => $name]),
        ];
      }
    }

    // Quotation section.
    $form['q'] = [
      '#type' => 'details',
      '#title' => $this->t('Quotations forms'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['q']['new_quotation'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload quotation template'),
      '#description' => $this->t('Only files with a ".inc" extension are allowed.'),
      '#upload_location' => 'private://sales/templates/quotation/',
      '#upload_validators' => [
        'file_validate_extensions' => ['inc'],
      ],
      '#multiple' => FALSE,
    ];

    if (!empty($this->tpls['quotation'])) {
      foreach ($this->tpls['quotation'] as $i => $name) {
        $form['q']['template_quotation_' . $i] = [
          '#type' => 'checkbox',
          '#default_value' => 0,
          '#return_value' => $name,
          '#attributes' => ['title' => $this->t('delete')],
          '#title' => $this->t('Delete quotation template <b>"@n"</b>', ['@n' => $name]),
        ];
      }
    }

    // Invoice section.
    $form['i'] = [
      '#type' => 'details',
      '#title' => $this->t('Invoice forms'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['i']['new_invoice'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload invoice template'),
      '#description' => $this->t('Only files with a ".inc" extension are allowed.'),
      '#upload_location' => 'private://sales/templates/invoice/',
      '#upload_validators' => [
        'file_validate_extensions' => ['inc'],
      ],
      '#multiple' => FALSE,
    ];

    if (!empty($this->tpls['invoice'])) {
      foreach ($this->tpls['invoice'] as $i => $name) {
        $form['i']['template_invoice_' . $i] = [
          '#type' => 'checkbox',
          '#default_value' => 0,
          '#return_value' => $name,
          '#attributes' => ['title' => $this->t('delete')],
          '#title' => $this->t('Delete invoice template <b>"@n"</b>', ['@n' => $name]),
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['#attached']['library'][] = 'ek_sales/ek_sales_css';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process deletes.
    $this->processTemplateDeletes($form_state, 'p', 'purchase');
    $this->processTemplateDeletes($form_state, 'q', 'quotation');
    $this->processTemplateDeletes($form_state, 'i', 'invoice');

    // Process uploads.
    $this->processTemplateUpload($form_state, 'p', 'purchase', 'new_purchase');
    $this->processTemplateUpload($form_state, 'q', 'quotation', 'new_quotation');
    $this->processTemplateUpload($form_state, 'i', 'invoice', 'new_invoice');

    // Reindex arrays for cleaner storage.
    $this->tpls['purchase'] = array_values(array_unique($this->tpls['purchase']));
    $this->tpls['quotation'] = array_values(array_unique($this->tpls['quotation']));
    $this->tpls['invoice'] = array_values(array_unique($this->tpls['invoice']));

    $this->settings->set('templates', $this->tpls);
    $this->settings->save();
  }

  /**
   * Ensures template directory exists.
   */
  protected function prepareTemplateDirectory(string $type): void {
    $dir = 'private://sales/templates/' . $type . '/';
    $this->fileSystem->prepareDirectory(
      $dir,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );
  }

  /**
   * Handles template deletion checkboxes for a section.
   */
  protected function processTemplateDeletes(FormStateInterface $form_state, string $section, string $type): void {
    $values = (array) $form_state->getValue($section);
    if (empty($values)) {
      return;
    }

    foreach ($values as $key => $value) {
      // Only process delete checkboxes, not managed_file values.
      if (!str_starts_with((string) $key, 'template_' . $type . '_')) {
        continue;
      }
      if (empty($value)) {
        continue;
      }

      $filename = basename((string) $value);
      $uri = 'private://sales/templates/' . $type . '/' . $filename;

      // Delete file entities with this URI if present.
      $storage = $this->entityTypeManager->getStorage('file');
      $files = $storage->loadByProperties(['uri' => $uri]);
      if (!empty($files)) {
        $storage->delete($files);
      }
      else {
        // If not managed by file entity, delete raw file.
        try {
          $this->fileSystem->delete($uri);
        }
        catch (\Exception $e) {
          // Ignore if not found or already deleted.
        }
      }

      // Remove from settings list.
      if (!empty($this->tpls[$type])) {
        $idx = array_search($filename, $this->tpls[$type], TRUE);
        if ($idx !== FALSE) {
          unset($this->tpls[$type][$idx]);
        }
      }

      $this->messenger()->addStatus($this->t('Template @t deleted', ['@t' => $filename]));
    }
  }

  /**
   * Handles one managed_file upload field.
   */
  protected function processTemplateUpload(FormStateInterface $form_state, string $section, string $type, string $field_name): void {
    $values = (array) $form_state->getValue($section);
    $fids = $values[$field_name] ?? [];

    if (!is_array($fids) || empty($fids[0])) {
      return;
    }

    $fid = (int) $fids[0];
    if ($fid <= 0) {
      return;
    }

    $file = File::load($fid);
    if (!$file) {
      return;
    }

    // Keep uploaded file permanently.
    $file->setPermanent();
    $file->save();

    $filename = $file->getFilename();

    if (!in_array($filename, $this->tpls[$type], TRUE)) {
      $this->tpls[$type][] = $filename;
    }

    $this->messenger()->addStatus($this->t('New @f uploaded', ['@f' => $filename]));
  }

}