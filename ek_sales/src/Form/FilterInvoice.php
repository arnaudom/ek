<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterInvoice.
 */

namespace Drupal\ek_sales\Form;

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
                ->query("SELECT date from {ek_sales_invoice} order by date DESC limit 1")
                ->fetchObject();
        $from = Database::getConnection('external_db', 'external_db')
                ->query("SELECT date from {ek_sales_invoice} WHERE status <> 1 order by date limit 1")
                ->fetchObject();

        $s = array(0 => $this->t('Not paid'), 1 => $this->t('Paid'), 3 => $this->t('Any'));
        $filter_title = $this->t('Filter');
        if (isset($_SESSION['ifilter']['status'])) {
            $filter_title .= ' (' . $s[$_SESSION['ifilter']['status']] . ')';
        }

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $filter_title,
            '#open' => isset($_SESSION['ifilter']['filter']) ? false : true,
                //'#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['keyword'] = array(
            '#type' => 'textfield',
            '#maxlength' => 75,
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('Search with keyword, ref No.')),
            '#default_value' => isset($_SESSION['ifilter']['keyword']) ? $_SESSION['ifilter']['keyword'] : null,
        );

        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => isset($_SESSION['ifilter']['coid']) ? $_SESSION['ifilter']['coid'] : 0,
            '#prefix' => "<div>",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['ifilter']['from']) ? $_SESSION['ifilter']['from'] : $from->date,
            '#title' => $this->t('from'),
            '#prefix' => "<div class='container-inline'>",
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['ifilter']['to']) ? $_SESSION['ifilter']['to'] : $to->date,
            '#suffix' => '</div>',
            '#title' => $this->t('to'),
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

            if (!empty($client)) {
                $client = array('%' => $this->t('Any')) + $client;
                $form['filters']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($_SESSION['ifilter']['client']) ? $_SESSION['ifilter']['client'] : '%',
                    '#title' => $this->t('client'),
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                    //'#prefix' => "<div class='container-inline'>",
                    '#states' => array(
                        'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                        ),
                    ),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();

                $form['filters']['client'] = array(
                    '#markup' => $this->t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                );
            }
        } else {
            $form['filters']['client'] = array(
                '#markup' => $this->t('You do not have any client list.'),
                '#default_value' => 0,
            );
        }

        $form['filters']['status'] = array(
            '#type' => 'select',
            '#options' => array(0 => $this->t('Not paid'), 1 => $this->t('Paid'), 3 => $this->t('Any')),
            '#default_value' => isset($_SESSION['ifilter']['status']) ? $_SESSION['ifilter']['status'] : '0',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['ifilter'])) {
            $form['filters']['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => array(array($this, 'resetForm')),
            );
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
        $_SESSION['ifilter']['status'] = $form_state->getValue('status');
        $_SESSION['ifilter']['client'] = $form_state->getValue('client');
        $_SESSION['ifilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['ifilter'] = array();
    }

}
