<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterTrial.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;

/**
 * Provides a form to filter trial balance display
 */
class FilterTrial extends FormBase {

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
        return 'trial_balance_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $year = date('Y');
        $month = date('m');

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
        $company = Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed();

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $options = array($year, $year - 1, $year - 2, $year - 3, $year - 4);
        $form['filters']['year'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($options, $options),
            '#default_value' => isset($_SESSION['tfilter']['year']) ? $_SESSION['tfilter']['year'] : $year,
            '#title' => $this->t('year'),
        );

        $form['filters']['month'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array('01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10', '11' => '11', '12' => '12'),
            '#default_value' => isset($_SESSION['tfilter']['month']) ? $_SESSION['tfilter']['month'] : $month,
            '#attributes' => array('placeholder' => $this->t('to')),
            '#title' => $this->t('month'),
        );


        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#title' => $this->t('company'),
            '#default_value' => isset($_SESSION['tfilter']['coid']) ? $_SESSION['tfilter']['coid'] : null,
        );

        $form['filters']['active'] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($_SESSION['tfilter']['active']) ? $_SESSION['tfilter']['active'] : 1,
            '#title' => $this->t('active only'),
            '#attributes' => array('title' => $this->t('show only active status accounts in list')),
            '#title_display' => 'before',
        );

        $form['filters']['null'] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($_SESSION['tfilter']['null']) ? $_SESSION['tfilter']['null'] : 1,
            '#title' => $this->t('no transaction'),
            '#attributes' => array('title' => $this->t('hide accounts with no transactions')),
            '#title_display' => 'before',
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

        if (!empty($_SESSION['tfilter'])) {
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
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['tfilter']['year'] = $form_state->getValue('year');
        $_SESSION['tfilter']['month'] = $form_state->getValue('month');
        $_SESSION['tfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['tfilter']['active'] = $form_state->getValue('active');
        $_SESSION['tfilter']['null'] = $form_state->getValue('null');
        $_SESSION['tfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['tfilter'] = array();
    }

}
