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
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to filter HR funds.
 */
class FilterFund extends FormBase {

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

        if ($form_state->getValue('coid')) {
            $param = NEW HrSettings($form_state->getValue('coid'));
            
            $opt = [
                'fund1' => $param->get('param', 'c', 'value'),
                'fund2' => $param->get('param', 'h', 'value'),
                'fund3' => $param->get('param', 'q', 'value'),
                'fund4' => $param->get('param', 'v', 'value'),
                'fund5' => $param->get('param', 'aa', 'value'),
            ];
           
        } else {
           $opt = array() ;
        }

        $form['filters']['fund'] = array(
            '#type' => 'select',
            '#options' => $opt,
            '#required' => TRUE,
            '#default_value' =>  NULL,
            '#prefix' => "<div id='type_fund'>",
            '#suffix' => '</div>',
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

        $query = "SELECT country FROM {ek_company} WHERE id=:id";
        $_SESSION['hrfundfilter']['country'] = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $form_state->getValue('coid')])
                ->fetchField();

        $_SESSION['hrfundfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['hrfundfilter']['fund'] = $form_state->getValue('fund');
        $_SESSION['hrfundfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['hrfundfilter'] = array();
    }

}
