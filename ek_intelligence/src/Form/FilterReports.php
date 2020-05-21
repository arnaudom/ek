<?php

/**
 * @file
 * Contains \Drupal\ek_intelligence\Form\FilterReports.
 */

namespace Drupal\ek_intelligence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter business reports.
 */
class FilterReports extends FormBase {

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
        return 'ireport_list_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $company = AccessCheck::CompanyListByUid();

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => isset($_SESSION['ireports']['coid']) ? $_SESSION['ireports']['coid'] : null,
            '#title' => t('Company'),
            '#required' => true,
            '#prefix' => "",
            '#suffix' => '',
        );


        $form['filters']["type"] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => true,
            '#options' => array('%' => t('any'), 1 => t('briefing'), 2 => t('report'), 3 => t('training')),
            '#title' => t('Category'),
            '#default_value' => $_SESSION['ireports']['type'],
            '#attributes' => array(),
            '#prefix' => "<div id='category'  class='row'>",
            '#suffix' => '</div>',
        );

        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 11,
            '#default_value' => isset($_SESSION['ireports']['from']) ? $_SESSION['ireports']['from'] : date('Y-m') . "-01",
            //'#attributes' => array('placeholder'=>t('YY-mm-dd'), 'class' => array('date')),
            '#prefix' => "",
            '#suffix' => '',
            '#title' => t('from date'),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['ireports'])) {
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
        $_SESSION['ireports']['coid'] = $form_state->getValue('coid');
        $_SESSION['ireports']['type'] = $form_state->getValue('type');
        $_SESSION['ireports']['from'] = $form_state->getValue('from');
        $_SESSION['ireports']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['ireports'] = array();
    }

}
