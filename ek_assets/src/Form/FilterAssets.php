<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\FilterAssets.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to filter assets list.
 */
class FilterAssets extends FormBase
{

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
    public function __construct(ModuleHandler $module_handler)
    {
        $this->moduleHandler = $module_handler;
        $this->settings = new FinanceSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'assets_list_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
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
            '#default_value' => isset($_SESSION['assetfilter']['coid']) ? $_SESSION['assetfilter']['coid'] : null,
            '#title' => $this->t('Company'),
            '#required' => true,
            '#prefix' => "",
            '#suffix' => '',
            '#ajax' => array(
                'callback' => array($this, 'get_category'),
                'wrapper' => 'category',
            ),
        );

        if ($form_state->getValue('coid') || isset($_SESSION['assetfilter']['coid'])) {
            $aid = array('%' => $this->t('Any'));
            $coid = isset($_SESSION['assetfilter']['coid']) ? isset($_SESSION['assetfilter']['coid']):$form_state->getValue('coid');
            $chart = $this->settings->get('chart');
            $aid += AidList::listaid($coid, array($chart['assets']), 1);
            $_SESSION['assetfilter']['options'] = $aid;
        } else {
            $_SESSION['assetfilter']['options'] = array('%' => $this->t('Any'));
        }
        
        $form['filters']["category"] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => true,
            '#options' => isset($_SESSION['assetfilter']['options']) ? $_SESSION['assetfilter']['options'] : array(),
            '#title' => $this->t('Category'),
            '#default_value' => isset($_SESSION['assetfilter']['category']) ? $_SESSION['assetfilter']['category'] : null,
            '#attributes' => array('style' => array('width:200px;')),
            '#prefix' => "<div id='category'  class='row'>",
            '#suffix' => '</div>',
        );

        $form['filters']["amort_status"] = array(
            '#type' => 'checkbox',
            '#description' => $this->t('Not amortized'),
            '#default_value' => isset($_SESSION['assetfilter']['amort_status']) ? $_SESSION['assetfilter']['amort_status'] : 0,
            '#prefix' => "<div id='category'  class='row'>",
            '#suffix' => '</div>',
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['assetfilter'])) {
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
     * callback functions
     */
    public function get_category(array &$form, FormStateInterface $form_state)
    {

        //return aid list
        return $form['filters']['category'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['assetfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['assetfilter']['category'] = $form_state->getValue('category');
        $_SESSION['assetfilter']['amort_status'] = $form_state->getValue('amort_status');
        $_SESSION['assetfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['assetfilter'] = array();
    }
}
