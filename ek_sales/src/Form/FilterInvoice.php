<?php

namespace Drupal\ek_sales\Form;

use Drupal\ek_finance\CurrencyData;
use Drupal\ek_address_book\AddressBookData;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter invoices.
 */
class FilterInvoice extends FormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandler $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
              $container->get('module_handler')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'invoice_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $to = Database::getConnection('external_db', 'external_db')
      ->select('ek_sales_invoice', 'i')
      ->fields('i', ['date'])
      ->range(0, 1)
      ->orderBy('date', 'DESC')
      ->execute()->fetchObject();
    $from = Database::getConnection('external_db', 'external_db')
      ->select('ek_sales_invoice', 'i')
      ->fields('i', ['date'])
      ->condition('status', 1, '<>')
      ->range(0, 1)
      ->orderBy('date')
      ->execute()->fetchObject();
    $s = [0 => $this->t('Not paid'), 1 => $this->t('Paid'), 3 => $this->t('Any')];
    $filter_title = $this->t('Filter');
    if (isset($_SESSION['ifilter']['status'])) {
      $filter_title .= ' (' . $s[$_SESSION['ifilter']['status']];
      if (isset($_SESSION['ifilter']['currency']) && $_SESSION['ifilter']['currency'] != '%') {
        $filter_title .= " - " . $_SESSION['ifilter']['currency'];
      }
      $filter_title .= ')';
    }

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $filter_title,
      '#open' => isset($_SESSION['ifilter']['filter']) ? FALSE : TRUE,
              // '#attributes' => array('class' => array('container-inline')),
    ];
    $form['filters']['filter'] = [
      '#type' => 'hidden',
      '#value' => 'filter',
    ];
    $form['filters']['keyword'] = [
      '#type' => 'textfield',
      '#maxlength' => 75,
      '#size' => 60,
      '#attributes' => ['placeholder' => $this->t('Keyword search, multiple search separated by comma, i.e. abc1, def2')],
      '#default_value' => $_SESSION['ifilter']['keyword'] ?? NULL,
    ];

    $form['filters']['coid'] = [
      '#type' => 'select',
      '#size' => 1,
      '#options' => AccessCheck::CompanyListByUid(),
      '#default_value' => $_SESSION['ifilter']['coid'] ?? 0,
      '#prefix' => "<div>",
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          ':input[name="keyword"]' => ['filled' => TRUE],
        ],
      ],
    ];

    $form['filters']['from'] = [
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => $_SESSION['ifilter']['from'] ?? $from->date,
      '#title' => $this->t('from'),
      '#prefix' => "<div class='container-inline'>",
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    $form['filters']['to'] = [
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => $_SESSION['ifilter']['to'] ?? $to->date,
      '#suffix' => '</div>',
      '#title' => $this->t('to'),
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    $form['filters']['pcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project code'),
      '#size' => 50,
      '#maxlength' => 150,
      '#attributes' => ['placeholder' => $this->t('Ex. 123')],
      '#default_value' => $_SESSION['ifilter']['pcode'] ?? '',
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];
    if ($this->moduleHandler->moduleExists('ek_projects')) {
      $form['filters']['pcode']['#autocomplete_route_name'] = 'ek_look_up_projects';
      $form['filters']['pcode']['#autocomplete_route_parameters'] = ['level' => 'all', 'status' => '0'];
    }

    if ($this->moduleHandler->moduleExists('ek_address_book')) {
      $client = AddressBookData::addresslist(1);

      if (!empty($client)) {
        $client = ['%' => $this->t('Any')] + $client;
        $form['filters']['client'] = [
          '#type' => 'select',
          '#size' => 1,
          '#options' => $client,
          '#required' => TRUE,
          '#default_value' => $_SESSION['ifilter']['client'] ?? '%',
          '#title' => $this->t('client'),
          '#attributes' => ['style' => ['width:200px;white-space:nowrap']],
              // '#prefix' => "<div class='container-inline'>",
          '#states' => [
            'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
          ],
        ];
      }
      else {
        $link = Url::fromRoute('ek_address_book.new', [])->toString();
        $form['filters']['client'] = [
          '#markup' => $this->t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
          '#prefix' => "<div class='messages messages--warning'>",
          '#suffix' => '</div>',
        ];
      }
    }
    else {
      $form['filters']['client'] = [
        '#markup' => $this->t('You do not have any client list.'),
        '#default_value' => 0,
      ];
    }

    $form['filters']['status'] = [
      '#type' => 'select',
      '#options' => [0 => $this->t('Not paid'), 1 => $this->t('Paid'), 3 => $this->t('Any')],
      '#default_value' => $_SESSION['ifilter']['status'] ?? '0',
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    if ($this->moduleHandler->moduleExists('ek_finance')) {
      $op = ['%' => $this->t('Any')];
      $op += CurrencyData::listcurrency(1);
      $form['filters']['currency'] = [
        '#type' => 'select',
        '#options' => $op,
        '#description' => $this->t('currency'),
        '#default_value' => $_SESSION['ifilter']['currency'] ?? '%',
        '#states' => [
          'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
        ],
      ];
    }

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    if (!empty($_SESSION['ifilter'])) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#submit' => [[$this, 'resetForm']],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('client')) {
      $form_state->setErrorByName('client', $this->t('You must select a client.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['ifilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
    $_SESSION['ifilter']['coid'] = $form_state->getValue('coid');
    $_SESSION['ifilter']['from'] = $form_state->getValue('from');
    $_SESSION['ifilter']['to'] = $form_state->getValue('to');
    $pcode = Xss::filter(trim($form_state->getValue('pcode')));
    if (preg_match('/^code\s+(\S+)/', $pcode, $matches)) {
      $pcode = $matches[1];
    }
    $_SESSION['ifilter']['pcode'] = $pcode;
    $_SESSION['ifilter']['status'] = $form_state->getValue('status');
    $_SESSION['ifilter']['client'] = $form_state->getValue('client');
    $_SESSION['ifilter']['currency'] = (!NULL == $form_state->getValue('currency')) ? $form_state->getValue('currency') : '%';
    $_SESSION['ifilter']['filter'] = 1;
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['ifilter'] = [];
    $_SESSION['ifilter']['filter'] = 0;
  }

}
