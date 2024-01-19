<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Cycle.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to set the payroll cycle
 */
class Cycle extends FormBase {

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
        return 'hr_cycle';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
        ];

        if ($form_state->getValue('coid') == '') {
            $form['next'] = [
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#states' => [
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => ['value' => ''],
                    ),
                ],
            ];
        }

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);
            $year = date('Y');
            $options = [$year, $year - 1];

            $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_hr_payroll_cycle', 'c')
                            ->fields('c', ['current'])
                            ->condition('coid', $form_state->getValue('coid'), '=');
            $current = $query->execute()->fetchField();
            $date = explode('-', $current); 
            $form['current'] = array(
                '#type' => 'hidden',
                '#value' => $current,
            );

            $form['month'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Current payroll month') . ': ' . $current,
            );

            $form['_year'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($options, $options),
                '#default_value' => isset($date[0]) ? $date[0] : $year,
                '#title' => $this->t('Change'),
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['_month'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => ['01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10', '11' => '11', '12' => '12'],
                '#default_value' => isset($date[1]) ? $date[1] : '01',
                '#title' => $this->t('Month'),
                '#suffix' => '</div>',
            ];

            
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Confirm change'),
                '#suffix' => ''
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
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {

                $current  =  $form_state->getValue('current');
                $edit = $form_state->getValue('_year') . "-" . $form_state->getValue('_month');

                if ($current != $edit) {
                    $rec = 0;
                    Database::getConnection('external_db', 'external_db')
                                    ->update('ek_hr_payroll_cycle')
                                    ->fields(['current' => $edit])
                                    ->condition('coid', $form_state->getValue('coid'))
                                    ->execute();
                    \Drupal::messenger()->addStatus($this->t('Payroll month set to @r', ['@r' => $edit]));
                    // update post data
                    $squery = Database::getConnection('external_db', 'external_db')
                        ->select('ek_hr_workforce','w')
                        ->fields('w',['id'])
                        ->condition('company_id',$form_state->getValue('coid'))
                        ->execute();

                        while ($id = $squery->fetchField()) {
                            $del = Database::getConnection('external_db', 'external_db')
                                ->delete('ek_hr_post_data')
                                ->condition('emp_id', $id)
                                ->condition('month', $current)
                                ->execute();
                            if($del) {$rec++;}
                        }
                        \Drupal::messenger()->addStatus($this->t('@r post data deleted', ['@r' => $rec]));
                }
             }
        }
    }
