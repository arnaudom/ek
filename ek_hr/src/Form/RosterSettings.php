<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\RosterSettings.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to edit roster settings
 */
class RosterSettings extends FormBase {

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
        return 'roster_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {


        if ($form_state->get('step') == '') {

            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
            '#title' => t('Company'),
            '#disabled' => ($form_state->getValue('coid')) ? TRUE : FALSE,
            '#required' => TRUE,
        );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                '#states' => array(
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);

            //verify if the settings table has the company
            $query = "SELECT count(coid) from {ek_hr_workforce_settings} where coid=:c";
            $row = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => $form_state->getValue('coid')))
                    ->fetchField();

            if ($row != 1) {

                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_workforce_settings')
                        ->fields(array('coid' => $form_state->getValue('coid')))
                        ->execute();
            }


            $roster = NEW HrSettings($form_state->getValue('coid'));
            $settings = $roster->HrRoster[$form_state->getValue('coid')];

            /*
              if (empty($list)) {
              $list = array(
              $form_state->getValue('coid') => array(
              'shift_start' => '00:00',
              )
              );

              Database::getConnection('external_db', 'external_db')
              ->update('ek_hr_workforce_settings')
              ->fields(array('roster' => serialize($list)))
              ->condition('coid', $form_state->getValue('coid'))
              ->execute();

              $roster = NEW HrSettings($form_state->getValue('coid'));
              $list = $roster->HrRoster[$form_state->getValue('coid')];
              }
              $form['info'] = array(
              '#type' => 'item',
              '#markup' => t('set the starting time of 1st shift (in a 3 x 8H shift configuration.)'),
              );

              foreach ($list as $key => $value) {

              $form[$key] = array(
              '#type' => 'textfield',
              '#size' => 20,
              '#maxlength' => 5,
              '#default_value' => $value,
              '#attributes' => array(),
              '#title' => '',
              );
              }//for
             */
            $form['hours_day'] = array(
                '#type' => 'number',
                '#min' => 0,
                '#max' => 24,
                '#step' => 0.5,
                '#size' => 2,
                '#default_value' => isset($settings['hours_day']) ? $settings['hours_day'] : 8,
                '#title' => t('Worked hours per day'),
                '#required' => TRUE,
                
            );

            $form['roster_hours_format'] = array(
                '#type' => 'select',
                '#options' => ['1' => 'hh:mm', '2' => 'decimal point'],
                '#default_value' => isset($settings['roster_hours_format']) ? $settings['roster_hours_format'] : 8,
                '#title' => t('Hours format in roster export'),
                '#required' => TRUE,
                
            );
            
            $form['last_day'] = array(
                '#type' => 'select',
                '#options' => ['1' => t('Monday'), '2' => t('Tuesday'),'3' => t('Wednesday'),'4' => t('Thursday'),'5' => t('Friday'),'6' => t('Saturday'),'7' => t('Sunday'),],
                '#default_value' => isset($settings['last_day']) ? $settings['last_day'] : 7,
                '#title' => t('Last day of the week'),
                '#required' => TRUE,
                
            );

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );


            $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
        }

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
            //TODO insert numeric validation for value
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        if ($form_state->get('step') == 3) {

            $roster = NEW HrSettings($form_state->getValue('coid'));
            $settings = $roster->HrRoster[$form_state->getValue('coid')];

            $roster->set('roster', 'hours_day', $form_state->getValue('hours_day'));
            $roster->set('roster', 'roster_hours_format', $form_state->getValue('roster_hours_format'));
            $roster->set('roster', 'last_day', $form_state->getValue('last_day'));
            /*
            foreach ($list as $key => $value) {

                $input = Xss::filter($form_state->getValue($key));
                $roster->set(
                        'roster', $key, $input
                );
            }*/
            

            $roster->save();
        }//step 3
    }

}
