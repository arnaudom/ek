<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\FilterProjects.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\ek_admin\Access\AccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to filter Projects.
 *
 */
class FilterProjects extends FormBase {

  /**
   * The private temp store for filter state.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $extdb;

  /**
   * Constructs a FilterProjects form.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Database\Connection $extdb
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, $extdb) {
    $this->tempStore = $temp_store_factory->get('ek_projects_filter');
    $this->extdb = $extdb;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      Database::getConnection('external_db', 'external_db')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_projects_filter';
  }

  /**
   * Returns current filter state from TempStore with a default fallback.
   *
   * @param string $key
   * @param mixed $default
   * @return mixed
   */
  protected function getFilter(string $key, $default = NULL) {
    $value = $this->tempStore->get($key);
    return $value !== NULL ? $value : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // ------------------------------------------------------------------ //
    // Country list — scoped to user access.
    // ------------------------------------------------------------------ //
    $access = AccessCheck::GetCountryByUser();
    $cid_list = implode(',', array_map('intval', $access)); // sanitize to ints

    $country_list = [0 => $this->t('Any')];
    if ($cid_list) {
      $country_list += $this->extdb
        ->query(
          "SELECT id, name FROM {ek_country}
           WHERE status = :t AND FIND_IN_SET(id, :c)
           ORDER BY name",
          [':t' => 1, ':c' => $cid_list]
        )
        ->fetchAllKeyed();
    }

    // ------------------------------------------------------------------ //
    // Client list — only clients that have at least one project.
    // ------------------------------------------------------------------ //
    $client_list = ['%' => $this->t('Any')];
    $client_list += $this->extdb
      ->select('ek_address_book', 'b')
      ->fields('b', ['id', 'name'])
      ->distinct()
      ->innerJoin('ek_project', 'p', 'b.id = p.client_id') // returns join alias; ignore
      ? // chained below:
      [] : [];
    // Rewrite as a proper query object:
    $client_query = $this->extdb->select('ek_address_book', 'b');
    $client_query->innerJoin('ek_project', 'p', 'b.id = p.client_id');
    $client_list = ['%' => $this->t('Any')];
    $client_list += $client_query
      ->fields('b', ['id', 'name'])
      ->distinct()
      ->orderBy('b.name')
      ->execute()
      ->fetchAllKeyed();

    // ------------------------------------------------------------------ //
    // Supplier list — fetch distinct supplier IDs in one query.
    // Original code fetched all rows and exploded a CSV string per row,
    // which is O(n) queries. We still need to handle the CSV-stored IDs,
    // but we do it in PHP without extra DB round-trips.
    // ------------------------------------------------------------------ //
    //$supplier_list = ['%' => $this->t('Any')];
    $query = $this->extdb
    ->select('ek_project_description', 'p')
    ->fields('p', ['supplier_offer'])
    ->condition('supplier_offer', 0, '<>');
    $raw_suppliers = $query->execute()->fetchCol();
      /*->query(
        "SELECT DISTINCT supplier_offer FROM {ek_project_description}
         WHERE supplier_offer <> :s",
        [':s' => '']
      )
      ->fetchCol();*/

    $supplier_ids = [];
    foreach ($raw_suppliers as $csv) {
      foreach (explode(',', $csv) as $id) {
        $id = trim($id);
        if ($id !== '') {
          $supplier_ids[$id] = $id;
        }
      }
    }
    foreach ($supplier_ids as $id) {
      $n = \Drupal\ek_address_book\AddressBookData::getname($id);
      if($n != null) {
        $supplier_list[$id] = trim($n);
      }
    }
    asort($supplier_list);
    $supplier_list = ['%' => $this->t('Any')] + $supplier_list;

    // ------------------------------------------------------------------ //
    // Project type / category list.
    // ------------------------------------------------------------------ //
    $type_list = ['%' => $this->t('Any')];
    $type_list += $this->extdb
      ->query("SELECT id, type FROM {ek_project_type} ORDER BY type")
      ->fetchAllKeyed();

    // ================================================================== //
    // Form elements.
    // ================================================================== //
    $form['#attributes']['class'][] = 'ek-projects-filter-form';
    $form['#attached']['library'][] = 'ek_projects/ek_projects_filter';

    // Hidden marker so the controller knows a filter has been applied.
    $form['filter'] = [
      '#type' => 'hidden',
      '#value' => 'filter',
    ];

    // ------------------------------------------------------------------ //
    // Row 0: Keyword — full-width search bar at the top.
    // ------------------------------------------------------------------ //
    $form['search_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pjf-search-row']],
    ];

    $form['search_row']['keyword'] = [
      '#type' => 'textfield',
      '#maxlength' => 150,
      '#placeholder' => $this->t('Search by keyword or reference No. — or use filters below'),
      '#default_value' => $this->getFilter('keyword', ''),
      '#title' => $this->t('Quick search'),
      '#title_display' => 'invisible',
      '#attributes' => ['class' => ['pjf-keyword']],
    ];

    // ------------------------------------------------------------------ //
    // Advanced filters — hidden while keyword is filled.
    // All rows use the CSS class 'pjf-row' which maps to a flex row.
    // ------------------------------------------------------------------ //
    $hide_on_keyword = [
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pjf-advanced']],
    ] + $hide_on_keyword;

    // ------------------------------------------------------------------ //
    // Row 1: Country · Category · Status  (single-select dropdowns)
    // ------------------------------------------------------------------ //
    $form['advanced']['row1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pjf-row']],
    ];

    $form['advanced']['row1']['cid'] = [
      '#type' => 'select',
      '#options' => $country_list,
      '#default_value' => $this->getFilter('cid', 0),
      '#title' => $this->t('Country'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
    ];

    $form['advanced']['row1']['type'] = [
      '#type' => 'select',
      '#options' => $type_list,
      '#default_value' => $this->getFilter('type', '%'),
      '#title' => $this->t('Category'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
    ];

    $form['advanced']['row1']['status'] = [
      '#type' => 'select',
      '#options' => [
        '%'         => $this->t('Any'),
        'open'      => $this->t('Open'),
        'awarded'   => $this->t('Awarded'),
        'completed' => $this->t('Completed'),
        'closed'    => $this->t('Closed'),
      ],
      '#default_value' => $this->getFilter('status', '%'),
      '#title' => $this->t('Status'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
    ];

    // ------------------------------------------------------------------ //
    // Row 2: Client · Supplier  (multi-select listboxes)
    // ------------------------------------------------------------------ //
    $form['advanced']['row2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pjf-row']],
    ];

    $form['advanced']['row2']['client'] = [
      '#type' => 'select',
      '#size' => 5,
      '#multiple' => TRUE,
      '#options' => $client_list,
      '#default_value' => $this->getFilter('client', ['%']),
      '#title' => $this->t('Client'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
    ];

    $form['advanced']['row2']['supplier'] = [
      '#type' => 'select',
      '#size' => 5,
      '#multiple' => TRUE,
      '#options' => $supplier_list,
      '#default_value' => $this->getFilter('supplier', ['%']),
      '#title' => $this->t('Supplier'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
    ];

    // ------------------------------------------------------------------ //
    // Row 3: Date toggle · From · To  (inline, only visible when checked)
    // ------------------------------------------------------------------ //
    $form['advanced']['date_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pjf-row', 'pjf-row--date']],
    ];

    $form['advanced']['date_row']['date'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->getFilter('date', 1),
      '#title' => $this->t('Filter by date range'),
      '#wrapper_attributes' => ['class' => ['pjf-col', 'pjf-col--checkbox']],
    ];

    $form['advanced']['date_row']['start'] = [
      '#type' => 'date',
      '#default_value' => $this->getFilter('start', date('Y') . '-01-01'),
      '#title' => $this->t('From'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
      '#states' => [
        'visible'   => [":input[name='date']" => ['checked' => TRUE]],
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    $form['advanced']['date_row']['end'] = [
      '#type' => 'date',
      '#default_value' => $this->getFilter('end', date('Y-m-d')),
      '#title' => $this->t('To'),
      '#wrapper_attributes' => ['class' => ['pjf-col']],
      '#states' => [
        'visible'   => [":input[name='date']" => ['checked' => TRUE]],
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    // -- Actions ------------------------------------------------------- //
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['pjf-actions']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply filters'),
      '#button_type' => 'primary',
    ];

    if ($this->tempStore->get('filter')) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#submit' => [[$this, 'resetForm']],
        '#button_type' => 'danger',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $keyword = trim($form_state->getValue('keyword'));

    // Disallow bare wildcard.
    if ($keyword === '%') {
      $form_state->setErrorByName('keyword', $this->t('The % character is not allowed as a search term.'));
    }

    // Date range validation only when date filtering is active and no keyword.
    if ($keyword === '' && $form_state->getValue('date') == 1) {
      $start = $form_state->getValue('start');
      $end   = $form_state->getValue('end');
      if ($start && $end && strtotime($end) < strtotime($start)) {
        $form_state->setErrorByName('start', $this->t('The start date must be before the end date.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keys = ['cid', 'status', 'type', 'client', 'supplier', 'keyword', 'date', 'start', 'end'];
    foreach ($keys as $key) {
      $this->tempStore->set($key, $form_state->getValue($key));
    }
    $this->tempStore->set('filter', 1);
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    foreach (['cid', 'status', 'type', 'client', 'supplier', 'keyword', 'date', 'start', 'end', 'filter'] as $key) {
      $this->tempStore->delete($key);
    }
  }

}