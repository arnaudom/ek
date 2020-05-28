<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\FormSelect.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ek_admin\Access\AccessCheck;

;
/**
 * Provides a form to select payslip template
 */
class FormSelect extends FormBase
{


  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'select_forms';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => $company,
    '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
    '#title' => $this->t('company'),
    '#disabled' => ($form_state->getValue('coid')) ? true : false,
    '#required' => true,
    '#prefix' => "<div class='container-inline'>"
    );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
    '#type' => 'submit',
    '#value' => $this->t('Next'). ' >>',
    '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),
   '#suffix' => '</div>',
  );
        }
  
        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);


            $month = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
  
            $form['month'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => array_combine($month, $month),
    '#title' => $this->t('month'),
    '#default_value' => date('m'),

    );

            $year = array(date('Y'), date('Y')-1, date('Y')-2,date('Y')-3, date('Y')-4);
            $form['year'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => array_combine($year, $year),
    '#title' => $this->t('year'),
  
    );

            $list = array();
            $handle = opendir("private://hr/forms");
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    $f = explode('.', $file);
                    if ($f[1] == 'inc') {
                        $list += array($file => $f[0]);
                    }
                }
            }

            $form['template'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => $list,
    '#title' => $this->t('form'),
    '#prefix' => '',
    '#suffix' => '</div>',
    );
  
            $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
            $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Print'),
    );
        }
  
        $form['#attached']['library'][] = 'ek_hr/ek_hr_css';
  
  
        return $form;
    }
  
  
  
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
    }
  
  
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['printforms']['coid'] = $form_state->getValue('coid');
        $_SESSION['printforms']['month'] = $form_state->getValue('year') . '-' .$form_state->getValue('month');
        $_SESSION['printforms']['template'] = $form_state->getValue('template');
        $_SESSION['printforms']['filter'] = 1;
  
        $form_state->set('step', 4);
        $form_state->setRebuild();
    }
}
