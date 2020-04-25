<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterQuotation.
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

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => isset($_SESSION['qfilter']['filter']) ? false : true,
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
            '#default_value' => isset($_SESSION['qfilter']['keyword']) ? $_SESSION['qfilter']['keyword'] : null,
        );
        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => isset($_SESSION['qfilter']['coid']) ? $_SESSION['qfilter']['coid'] : 0,
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
            '#default_value' => isset($_SESSION['qfilter']['from']) ? $_SESSION['qfilter']['from'] : $from,
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
            '#default_value' => isset($_SESSION['qfilter']['to']) ? $_SESSION['qfilter']['to'] : $to,
            '#title' => $this->t('to'),
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),),
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
                    '#default_value' => isset($_SESSION['qfilter']['client']) ? $_SESSION['qfilter']['client'] : null,
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                    '#title' => $this->t('client'),
                    '#states' => array(
                        'invisible' => array(':input[name="keyword"]' => array('filled' => true),),
                    ),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $form['options']['client'] = array(
                    '#markup' => t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                    '#states' => array(
                        'invisible' => array(':input[name="keyword"]' => array('filled' => true),),
                    ),
                );
            }
        } else {
            $form['filters']['client'] = array(
                '#markup' => $this->t('You do not have any client list.'),
                '#default_value' => 0,
                '#states' => array(
                    'invisible' => array(':input[name="keyword"]' => array('filled' => true),),
                ),
            );
        }

        $form['filters']['status'] = array(
            '#type' => 'select',
            '#options' => array('%' => $this->t('All'), 0 => $this->t('Open'), 1 => $this->t('Printed'), 2 => $this->t('Invoiced')),
            '#default_value' => isset($_SESSION['qfilter']['status']) ? $_SESSION['qfilter']['status'] : '0',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),),
            ),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['qfilter'])) {
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
        $_SESSION['qfilter']['status'] = $form_state->getValue('status');
        $_SESSION['qfilter']['client'] = $form_state->getValue('client');
        $_SESSION['qfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['qfilter'] = array();
    }

}
