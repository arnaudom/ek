<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Advance.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

;

/**
 * Provides a form to record salary advance
 */
class Advance extends FormBase {

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
        return 'hr_advance_payroll';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
            '#title' => t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? TRUE : FALSE,
            '#required' => TRUE,
        );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);


            $query = "SELECT id,name,currency from {ek_hr_workforce} WHERE company_id=:coid";
            $a = array(':coid' => $form_state->getValue('coid'));

            $employees = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a);
            $i = 0;
            $form["table"] = array(
                    '#prefix' => '<div class="table">',
                );
            While ($e = $employees->fetchObject()) {
                $i++;
                $query = "SELECT advance FROM {ek_hr_workforce_pay} WHERE id=:id";
                $adv = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':id' => $e->id])
                        ->fetchField();
                $name = str_replace(' ', '_', $e->name);
                
                $form["div" . $i] = array(
                    '#prefix' => '<div class="row">',
                );
                $form["name" . $i] = array(
                    '#type' => 'item',
                    '#markup' => $e->name,
                    '#prefix' => '<div class="cell">',
                    '#suffix' => '</div>',
                );                
                $form["id" . $i][$e->id] = array(
                    '#type' => 'textfield',
                    '#size' => 25,
                    '#default_value' => $adv,
                    '#title' => '',
                    '#description' => $e->currency,
                    '#prefix' => '<div class="cell container-inline">',
                    '#suffix' => '</div>',
                );
                $form["-div" . $i] = array(
                    '#prefix' => '</div>',
                );
                
            }

                $form["-table"] = array(
                    '#prefix' => '</div>',
                );
            
            $query = "SELECT current FROM {ek_hr_payroll_cycle} WHERE coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $current = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();
            $form['current'] = array(
                '#type' => 'hidden',
                '#value' => $current,
            );
            $form['count'] = array(
                '#type' => 'hidden',
                '#value' => $i,
            );
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Confirm advance for') . ' ' . $current,
                '#suffix' => ''
            );
            $form['#tree'] = TRUE;
        }//if step 2


        $form['#attached']['library'][] = 'ek_hr/ek_hr_css';





        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
         if ($form_state->get('step') == 3) {

            for ($i = 1; $i <= $form_state->getValue('count');$i++) {
                $arr_key = array_keys($form_state->getValue('id' . $i));
                $arr_val = array_values($form_state->getValue('id' . $i));                
                if(!is_numeric($arr_val[0])){ 
                    $form_state->setErrorByName('id' . $i . '][' . $arr_key[0] , $this->t('Non numeric value inserted: @v', ['@v' => $arr_val[0]]));
                }
            }
         }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {

            for ($i = 1; $i <= $form_state->getValue('count');$i++) {
                $arr_key = array_keys($form_state->getValue('id' . $i));
                $arr_val = array_values($form_state->getValue('id' . $i));                

                
                $query = "SELECT id FROM {ek_hr_workforce_pay} WHERE id=:id";
                $eid = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':id' => $arr_key[0]])
                        ->fetchField();
                if ($eid) {
                    Database::getConnection('external_db', 'external_db')
                            ->update('ek_hr_workforce_pay')
                            ->fields(['advance' => $arr_val[0], 'month' => $form_state->getValue('current')])
                            ->condition('id', $arr_key[0])
                            ->execute();
                    
                } else {
                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_hr_workforce_pay')
                            ->fields(['id' => $arr_key[0], 'advance' => $arr_val[0], 'month' => $form_state->getValue('current')])
                            ->execute();
                }
            }
        }//step 3
    }

}
