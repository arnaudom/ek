<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterPurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter purchases.
 */
class FilterPurchase extends FormBase {

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
        return 'purchase_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $to = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 'p')
                        ->fields('p', ['date'])
                        ->range(0, 1)
                        ->orderBy('date', 'DESC')
                        ->execute()->fetchObject();
        $from = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 'p')
                        ->fields('p', ['date'])
                        ->condition('status', 1, '<>')
                        ->range(0, 1)
                        ->orderBy('date')
                        ->execute()->fetchObject();
        $s = [0 => $this->t('Not paid'), 1 => $this->t('Paid'), 3 => $this->t('Any')];
        $filter_title = $this->t('Filter');
        if (isset($_SESSION['pfilter']['status'])) {
            $filter_title .= ' (' . $s[$_SESSION['pfilter']['status']];
            if (isset($_SESSION['pfilter']['currency']) && $_SESSION['pfilter']['currency'] != '%') {
                $filter_title .=  " - " . $_SESSION['pfilter']['currency'];
            }
            $filter_title .= ')';
        }

        $form['filters'] = [
            '#type' => 'details',
            '#title' => $filter_title,
            '#open' => (isset($_SESSION['pfilter']['filter'])) ? false : true,
                //'#attributes' => array('class' => array('container-inline')),
        ];

        $form['filters']['filter'] = [
            '#type' => 'hidden',
            '#value' => 'filter',
        ];

        $form['filters']['keyword'] = [
            '#type' => 'textfield',
            '#maxlength' => 75,
            '#size' => 30,
            '#attributes' => ['placeholder' => $this->t('Search with keyword, ref No.')],
            '#default_value' => isset($_SESSION['pfilter']['keyword']) ? $_SESSION['pfilter']['keyword'] : null,
        ];

        $form['filters']['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => isset($_SESSION['pfilter']['coid']) ? $_SESSION['pfilter']['coid'] : 0,
            '#prefix' => "<div>",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => ['filled' => true],],
            ],
        ];

        $form['filters']['from'] = [
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['pfilter']['from']) ? $_SESSION['pfilter']['from'] : $from->date,
            '#prefix' => "<div class='container-inline'>",
            '#title' => $this->t('from'),
            '#states' => [
                'invisible' => [':input[name="keyword"]' => ['filled' => true],],
            ],
        ];

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['pfilter']['to']) ? $_SESSION['pfilter']['to'] : $to->date,
            '#suffix' => '</div>',
            '#title' => $this->t('to'),
            '#states' => [
                'invisible' => [':input[name="keyword"]' => ['filled' => true],],
            ],
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $supplier = \Drupal\ek_address_book\AddressBookData::addresslist(2);
            if (!empty($supplier)) {
                $supplier = ['%' => $this->t('Any')] + $supplier;
                $form['filters']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $supplier,
                    '#required' => true,
                    '#default_value' => isset($_SESSION['pfilter']['client']) ? $_SESSION['pfilter']['client'] : '%',
                    '#attributes' => ['style' => array('width:200px;white-space:nowrap')],
                    '#title' => $this->t('supplier'),
                    '#states' => [
                        'invisible' => [':input[name="keyword"]' => ['filled' => true],],
                    ],
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', [])->toString();

                $form['filters']['supplier'] = [
                    '#markup' => $this->t("You do not have any <a title='create' href='@cl'>supplier</a> in your record.", ['@cl' => $link]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                ];
            }
        } else {
            $form['filters']['supplier'] = [
                '#markup' => $this->t('You do not have any supplier list.'),
                '#default_value' => 0,
            ];
        }

        $form['filters']['status'] = [
            '#type' => 'select',
            '#options' => $s,
            '#default_value' => isset($_SESSION['pfilter']['status']) ? $_SESSION['pfilter']['status'] : '0',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => ['filled' => true],],
            ],
        ];

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $op = ['%' => $this->t('Any')];
            $op += \Drupal\ek_finance\CurrencyData::listcurrency(1);
            $form['filters']['currency'] = [
                '#type' => 'select',
                '#options' => $op,
                '#description' => $this->t('currency'),
                '#default_value' => isset($_SESSION['pfilter']['currency']) ? $_SESSION['pfilter']['currency'] : '%',
                '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
                ],
            ];
        }
        
        $form['filters']['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => array('container-inline')],
        ];

        $form['filters']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        ];

        if (!empty($_SESSION['pfilter'])) {
            $form['filters']['actions']['reset'] = [
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => [[$this, 'resetForm']],
            ];
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (!$form_state->getValue('supplier')) {
            $form_state->setErrorByName('supplier', $this->t('You must select a supplier.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['pfilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
        $_SESSION['pfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['pfilter']['from'] = $form_state->getValue('from');
        $_SESSION['pfilter']['to'] = $form_state->getValue('to');
        $_SESSION['pfilter']['status'] = $form_state->getValue('status');
        $_SESSION['pfilter']['client'] = $form_state->getValue('supplier');
        $_SESSION['pfilter']['currency'] = (!null == $form_state->getValue('currency')) ? $form_state->getValue('currency') : '%';
        $_SESSION['pfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['pfilter'] = [];
        $_SESSION['pfilter']['filter'] = 0;
    }

}
