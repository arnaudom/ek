<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\PostData.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;



/**
 * Provides a form to post data to archive and optionally record finance
 */
class PostData extends FormBase {

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
        return 'hr_post_payroll';
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

            //verify any data to post
            $query = "SELECT count(ek_hr_workforce_pay.id) from {ek_hr_workforce_pay} INNER JOIN {ek_hr_workforce} ON  ek_hr_workforce_pay.id=ek_hr_workforce.id  WHERE company_id=:c";
            $row = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid')))->fetchField();

            $query = "SELECT current from {ek_hr_payroll_cycle WHERE coid=:c";
            $current = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid')))->fetchField();

            $form['month'] = array(
                '#type' => 'item',
                '#markup' => t('Current payroll month') . ': ' . $current,
            );

            if ($row == 0) {

                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => t('There is no data to post for this month.'),
                );
            } else {

                $query = "SELECT sum(gross) from {ek_hr_workforce_pay} INNER JOIN {ek_hr_workforce} ON  ek_hr_workforce_pay.id=ek_hr_workforce.id  WHERE company_id=:c";
                $gross = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid')))->fetchField();



                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => t('There is @n account(s) to be posted with gross value of @v.', array('@n' => $row, '@v' => number_format($gross))),
                );

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    $form['finance'] = array(
                        '#type' => 'checkbox',
                        '#title' => t('Record payments in accounts'),
                    );
                }
            }

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Confirm posting'),
                '#suffix' => ''
            );
        }//if stp 2


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
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {

//@TODO : backup data first

            $query = "INSERT into {ek_hr_post_data}
( emp_id, `month`, d_pay, n_days, basic, n_ot_days, n_ot_val, r_day, r_day_val, ph_day, ph_day_val, mc_day, mc_day_val, xr_hours, xr_hours_val, tleave, custom_aw1,custom_aw2, custom_aw3, custom_aw4, custom_aw5, custom_aw6, custom_aw7, custom_aw8, custom_aw9, custom_aw10, custom_aw11, custom_aw12, custom_aw13, commission, turnover, gross, no_payday, less_hours, less_hours_val, advance, custom_d1, custom_d2, custom_d3, custom_d4, custom_d5, custom_d6, custom_d7, epf_yee, socso_yee,deduction, nett, socso_er, epf_er, incometax, with_yer, with_yee, comment 
)
SELECT  ek_hr_workforce_pay.id, `month`, d_pay, n_days, basic, n_ot_days, n_ot_val, r_day, r_day_val, ph_day, ph_day_val, mc_day,
mc_day_val, xr_hours, xr_hours_val, tleave, custom_aw1, custom_aw2, custom_aw3, custom_aw4, custom_aw5, custom_aw6, custom_aw7, custom_aw8, custom_aw9, custom_aw10, custom_aw11, custom_aw12, custom_aw13, commission, turnover, gross, no_payday, less_hours, less_hours_val, advance,custom_d1,
custom_d2, custom_d3, custom_d4, custom_d5, custom_d6, custom_d7, epf_yee, socso_yee, deduction, nett, socso_er, epf_er, incometax, with_yer, with_yee , comment
from {ek_hr_workforce_pay} 
INNER JOIN {ek_hr_workforce} on ek_hr_workforce_pay.id = ek_hr_workforce.id
where ek_hr_workforce_pay.month <> '' and company_id=:c";

            $a = array(':c' => $form_state->getValue('coid'));
            $move = Database::getConnection('external_db', 'external_db')->query($query, $a);

            if ($move) {

                //Next : clear current pay

                $set = "
      month = '', n_days = 0, basic = 0, n_ot_days = 0, n_ot_val = 0,r_day = 0, r_day_val = 0, ph_day = 0, ph_day_val = 0, mc_day = 0, mc_day_val = 0, xr_hours = 0, xr_hours_val = 0, tleave = 0, custom_aw1 = 0, custom_aw2 = 0, custom_aw3 = 0, custom_aw4 = 0, custom_aw5 = 0, custom_aw6 = 0, custom_aw7 = 0, custom_aw8 = 0,custom_aw9 = 0, custom_aw10 = 0, custom_aw11 = 0,  custom_aw12 = 0, custom_aw13 = 0, commission = 0, turnover = 0, gross = 0, no_payday = 0, less_hours = 0, less_hours_val = 0,    custom_d2 = 0, custom_d3 = 0, custom_d4 = 0, custom_d5 = 0, custom_d6 = 0, custom_d7 = 0, epf_yee = 0, socso_yee = 0, nett = 0, epf_er = 0, socso_er = 0, incometax = 0, with_yer = 0, with_yee = 0 
  ";


                $query = "UPDATE {ek_hr_workforce_pay} "
                        . "INNER JOIN {ek_hr_workforce} "
                        . "ON ek_hr_workforce_pay.id = ek_hr_workforce.id set " . $set . " "
                        . "WHERE company_id =:c";

                $a = array(':c' => $form_state->getValue('coid'));
                $update = Database::getConnection('external_db', 'external_db')->query($query, $a);

                //Next ; increment current payroll
                $query = "SELECT current from {ek_hr_payroll_cycle} WHERE coid=:c";
                $current = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
                $newdate = strtotime('+1 month', strtotime($current));
                $newdate = date('Y-m', $newdate);
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_payroll_cycle')
                        ->fields(array('current' => $newdate))
                        ->condition('coid', $form_state->getValue('coid'))
                        ->execute();


                if ($form_state->getValue('finance') == '1') {

                    \Drupal::messenger()->addStatus(t('Posting recorded; redirecting to finance record.'));
                    //redirect to expenses form for payroll
                    //Get data as parameters
                    
                    $param = array();
                    $options = array();
                    $param['coid'] = $form_state->getValue('coid');

                    $query = "SELECT emp_id,name,month,n_days,nett,advance,epf_er,epf_yee,socso_er,socso_yee,incometax,with_yer,with_yee FROM "
                            . "{ek_hr_post_data} p INNER JOIN {ek_hr_workforce} w "
                            . "ON w.id = p.emp_id WHERE company_id=:c and month=:m";
                    $a = array(':c' => $param['coid'], ':m' => $current);
                    $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
                    

                    While ($r = $data->fetchObject()) {

                        $line = array(
                            'name' => $r->name,
                            'month' => $r->month,
                            'n_days' => $r->n_days,
                            'nett' => $r->nett,
                            'advance' => $r->advance,
                            'gross' => $r->gross,
                            'deduction' => array(
                                '0' => $r->epf_er + $r->epf_yee,
                                '1' => $r->socso_er + $r->socso_yee,
                                '2' => $r->with_yer + $r->with_yee,
                                '3' => '',
                                '4' => '',
                                '5' => $r->incometax,
                                '6' => ''
                            ),
                        );

                        $options[$r->emp_id] = $line;
                    }

                    $param = serialize($param);
                    $_SESSION['pay'] = $options;
                    $form_state->setRedirect('ek_finance_payroll.record', array('param' => $param));
                } else {
                    \Drupal::messenger()->addStatus(t('Posting recorded'));
                }
            } //if move
            else {
                \Drupal::messenger()->addError(t('Error while trying to post data.'));
            }
        }//step 2
    }

}