<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\FilterFund.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to filter HR funds.
 */
class FilterFund extends FormBase {

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
        return 'hr_funds_filter';
    }

  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $company = AccessCheck::CompanyListByUid();
        $code = '';

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => TRUE,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => isset($_SESSION['hrfundfilter']['coid']) ? $_SESSION['hrfundfilter']['coid'] : NULL,
            '#title' => t('company'),
            '#required' => TRUE,
            '#ajax' => array(
                'callback' => array($this, 'type_fund'),
                'wrapper' => 'type_fund',
            ),
        );

        if ((!NULL == $form_state->getValue('coid')) || isset($_SESSION['hrfundfilter']['coid'])) {
            $coid = (!NULL == $form_state->getValue('coid')) ? $form_state->getValue('coid') : $_SESSION['hrfundfilter']['coid'];
    
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_country', 'a');
            $query->fields('a',['code']);
            $query->leftJoin('ek_company', 'b', 'a.name = b.country');
            $query->condition('b.id', $coid);
            $code = $query->execute()->fetchField();
          
            $opt = $this->moduleHandler->invokeAll('list_fund',[strtolower($code)]);
           
        } else {
           $opt = array() ; 
        }

        $form['filters']['fund'] = array(
            '#type' => 'select',
            '#options' => $opt,
            '#required' => TRUE,
            //'#default_value' => '',
            '#prefix' => "<div id='type_fund'>",
            '#suffix' => '</div>',
        );
        
        $form['filters']['code'] = array(
            '#type' => 'hidden',
            '#value' => strtolower($code),
        );



        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
             
        );

        if (!empty($_SESSION['hrlfilter'])) {
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
   * Callback
   */
    public function type_fund(array &$form, FormStateInterface $form_state) {
        $form_state->setRebuild();
        return $form['filters']['fund'];
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

        $_SESSION['hrfundfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['hrfundfilter']['fund'] = $form_state->getValue('fund');
        $_SESSION['hrfundfilter']['code'] = $form_state->getValue('code');
        $_SESSION['hrfundfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['hrfundfilter'] = array();
    }

}
