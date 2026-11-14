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
 * Provides a form to filter quotations.
 */
class FilterQuotation extends FormBase {

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
    return 'quotation_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $query = Database::getConnection('external_db', 'external_db')
      ->select('ek_sales_quotation', 'q');
    $query->fields('q', ['date']);
    $query->condition('status', 0);
    $query->orderBy('date', "DESC");
    $query->range(0, 1);
    $from = $query->execute()->fetchField();
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime($to . " -90 days"));

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => isset($_SESSION['qfilter']['filter']) ? FALSE : TRUE,
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
      '#default_value' => $_SESSION['qfilter']['keyword'] ?? NULL,
    ];

    $form['filters']['coid'] = [
      '#type' => 'select',
      '#size' => 1,
      '#options' => AccessCheck::CompanyListByUid(),
      '#default_value' => $_SESSION['qfilter']['coid'] ?? 0,
      '#prefix' => "<div>",
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];
    $form['filters']['from'] = [
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => $_SESSION['qfilter']['from'] ?? $from,
      '#title' => $this->t('from'),
      '#prefix' => "<div class='container-inline'>",
      '#states' => [
        'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
      ],
    ];

    $form['filters']['to'] = [
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => $_SESSION['qfilter']['to'] ?? $to,
      '#title' => $this->t('to'),
      '#suffix' => '</div>',
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
      '#default_value' => $_SESSION['qfilter']['pcode'] ?? '',
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
          '#default_value' => $_SESSION['qfilter']['client'] ?? '%',
          '#attributes' => ['style' => ['width:200px;white-space:nowrap']],
          '#title' => $this->t('client'),
          '#states' => [
            'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
          ],
        ];
      }
      else {
        $link = Url::fromRoute('ek_address_book.new', [])->toString();
        $form['options']['client'] = [
          '#markup' => $this->t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
          '#prefix' => "<div class='messages messages--warning'>",
          '#suffix' => '</div>',
          '#states' => [
            'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
          ],
        ];
      }
    }
    else {
      $form['filters']['client'] = [
        '#markup' => $this->t('You do not have any client list.'),
        '#default_value' => 0,
        '#states' => [
          'invisible' => [':input[name="keyword"]' => ['filled' => TRUE]],
        ],
      ];
    }

    $form['filters']['status'] = [
      '#type' => 'select',
      '#options' => ['%' => $this->t('All'), 0 => $this->t('Open'), 1 => $this->t('Printed'), 2 => $this->t('Invoiced')],
      '#default_value' => $_SESSION['qfilter']['status'] ?? '0',
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
        '#default_value' => $_SESSION['qfilter']['currency'] ?? '%',
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
              // '#suffix' => "</div>",
    ];

    if (!empty($_SESSION['qfilter'])) {
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
      $form_state->setErrorByName('supplier', $this->t('You must select a client.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['qfilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
    $_SESSION['qfilter']['coid'] = $form_state->getValue('coid');
    $_SESSION['qfilter']['from'] = $form_state->getValue('from');
    $_SESSION['qfilter']['to'] = $form_state->getValue('to');
    $pcode = Xss::filter(trim($form_state->getValue('pcode')));
    if (preg_match('/^code\s+(\S+)/', $pcode, $matches)) {
      $pcode = $matches[1];
    }
    $_SESSION['qfilter']['pcode'] = $pcode;
    $_SESSION['qfilter']['status'] = $form_state->getValue('status');
    $_SESSION['qfilter']['client'] = $form_state->getValue('client');
    $_SESSION['qfilter']['currency'] = (!NULL == $form_state->getValue('currency')) ? $form_state->getValue('currency') : '%';
    $_SESSION['qfilter']['filter'] = 1;
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['qfilter'] = [];
    $_SESSION['qfilter']['filter'] = 0;
  }

}
