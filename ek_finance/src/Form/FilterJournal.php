<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterJournal.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter journal.
 */
class FilterJournal extends FormBase {

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
        return 'journal_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $jid = null) {
        $to = date('Y-m-d');
        $from = date('Y-m-') . '01';
        if ($jid == null && isset($_SESSION['jfilter']['jid'])) {
            $jid = $_SESSION['jfilter']['jid'];
        }

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $coids = ['' => $this->t('- Select -')];
        $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
        $coids += Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed();

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['jid'] = array(
            '#type' => 'textfield',
            '#maxlength' => 20,
            '#size' => 8,
            '#attributes' => array('placeholder' => $this->t('Search ID')),
            '#default_value' => $jid,
        );

        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['jfilter']['from']) ? $_SESSION['jfilter']['from'] : $from,
            '#title' => $this->t('from'),
            '#states' => array(
                'invisible' => array(':input[name="jid"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['jfilter']['to']) ? $_SESSION['jfilter']['to'] : $to,
            '#title' => $this->t('to'),
            '#states' => array(
                'invisible' => array(':input[name="jid"]' => array('filled' => true),
                ),
            ),
        );


        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $coids,
            '#required' => false,
            '#title' => $this->t('company'),
            '#default_value' => isset($_SESSION['jfilter']['coid']) ? $_SESSION['jfilter']['coid'] : null,
            '#states' => array(
                'invisible' => array(':input[name="jid"]' => array('filled' => true),
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
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['jfilter'])) {
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
        if ($form_state->getValue('jid') != '' && !is_numeric($form_state->getValue('jid'))) {
            $form_state->setErrorByName('jid', $this->t('@jid must be a number', array('@jid' => $form_state->getValue('jid'))));
        }
        if ($form_state->getValue('jid') === '') {
            if ($form_state->getValue('coid') === '') {
                $form_state->setErrorByName('coid', $this->t('Please select a company'));
            }
            if (strtotime($form_state->getValue('to')) < strtotime($form_state->getValue('from'))) {
                $form_state->setErrorByName('to', $this->t('Start date is higher than ending date'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['jfilter']['jid'] = $form_state->getValue('jid');
        $_SESSION['jfilter']['from'] = $form_state->getValue('from');
        $_SESSION['jfilter']['to'] = $form_state->getValue('to');
        $_SESSION['jfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['jfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['jfilter'] = array();
    }

}
