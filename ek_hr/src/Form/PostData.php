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
use Drupal\ek_hr\HrSettings;


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
        
        if ($form_state->get('step') == 3) {
            //validate settings for accounts if requested record
            if ($form_state->getValue('finance') == '1') {
                $settings = NEW HrSettings($form_state->getValue('coid'));
                $data = $settings->HrAccounts[$form_state->getValue('coid')];
                if (empty($data) || $data['pay_account'] == '') {
                    $url = \Drupal\Core\Url::fromRoute('ek_hr.parameters-accounts', array(), array())->toString();
                    $form_state->setErrorByName("coid", $this->t("You do not have payroll accounts recorded. Go to <a href='@s'>settings</a> first.", ['@s' => $url]));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {

//@TODO : backup data first

       $fields = ['id', 'month','d_pay','n_days','basic', 'n_ot_days','n_ot_val','r_day',
           'r_day_val','ph_day','ph_day_val','mc_day','mc_day_val','xr_hours','xr_hours_val',
           'tleave','custom_aw1','custom_aw2','custom_aw3','custom_aw4','custom_aw5',
           'custom_aw6','custom_aw7','custom_aw8','custom_aw9','custom_aw10','custom_aw11',
           'custom_aw12','custom_aw13','commission','turnover','gross','no_payday',
           'less_hours','less_hours_val','advance','custom_d1','custom_d2','custom_d3',
           'custom_d4','custom_d5','custom_d6','custom_d7','epf_yee','socso_yee',
           'deduction','nett','socso_er','epf_er','incometax','with_yer','with_yee','comment'
           ]; 
       $sub_query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_hr_workforce_pay', 'p');
       $sub_query->fields('p', $fields);
       $sub_query->innerJoin('ek_hr_workforce', 'w', 'w.id = p.id');
       $sub_query->condition('month', '', '<>');
       $sub_query->condition('company_id', $form_state->getValue('coid'), '=');
       $s = $sub_query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
       $fields = ['emp_id', 'month','d_pay','n_days','basic', 'n_ot_days','n_ot_val','r_day',
           'r_day_val','ph_day','ph_day_val','mc_day','mc_day_val','xr_hours','xr_hours_val',
           'tleave','custom_aw1','custom_aw2','custom_aw3','custom_aw4','custom_aw5',
           'custom_aw6','custom_aw7','custom_aw8','custom_aw9','custom_aw10','custom_aw11',
           'custom_aw12','custom_aw13','commission','turnover','gross','no_payday',
           'less_hours','less_hours_val','advance','custom_d1','custom_d2','custom_d3',
           'custom_d4','custom_d5','custom_d6','custom_d7','epf_yee','socso_yee',
           'deduction','nett','socso_er','epf_er','incometax','with_yer','with_yee','comment'
           ]; 
       $move = 0;
        foreach($s as $key => $array) {
            $string = implode(',', $array);
            $input = explode(',', $string);
            $query = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_post_data');
            $query->values($input);
            $query->fields($fields);
            $query->execute();
            $move++;
        }
        
            if ($move > 0) {
                //Next : clear current pay
                $fields = [
                    'month' => '',
                    'n_days' => 0,
                    'basic' => 0,
                    'n_ot_days' => 0,
                    'n_ot_val' => 0,
                    'r_day' => 0,
                    'r_day_val' => 0,
                    'ph_day' => 0,
                    'ph_day_val' => 0,
                    'mc_day' => 0,
                    'mc_day_val' => 0,
                    'xr_hours' => 0,
                    'xr_hours_val' => 0,
                    'tleave' => 0,
                    'custom_aw1' => 0,
                    'custom_aw2' => 0,
                    'custom_aw3' => 0,
                    'custom_aw4' => 0, 
                    'custom_aw5' => 0, 
                    'custom_aw6' => 0, 
                    'custom_aw7' => 0, 
                    'custom_aw8' => 0,
                    'custom_aw9' => 0,
                    'custom_aw10' => 0, 
                    'custom_aw11' => 0,  
                    'custom_aw12' => 0, 
                    'custom_aw13' => 0, 
                    'commission' => 0,
                    'turnover' => 0, 
                    'gross' => 0, 
                    'no_payday' => 0, 
                    'less_hours' => 0, 
                    'less_hours_val' => 0,
                    'custom_d2' => 0, 
                    'custom_d3' => 0, 
                    'custom_d4' => 0, 
                    'custom_d5' => 0, 
                    'custom_d6' => 0, 
                    'custom_d7' => 0,
                    'epf_yee' => 0, 
                    'socso_yee' => 0, 
                    'nett' => 0, 
                    'epf_er' => 0, 
                    'socso_er' => 0, 
                    'incometax' => 0, 
                    'with_yer' => 0, 
                    'with_yee' => 0
                ];
                $sub_query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_hr_workforce', 'w');
                $sub_query->fields('w', ['id']);
                $sub_query->condition('company_id', $form_state->getValue('coid'), '=');
                $data = $sub_query->execute();
                while($emp = $data->fetchObject()){
                    $update_pay = Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce_pay')
                        ->fields($fields);
                        $update_pay->condition('id' , $emp->id);
                        $update_pay->execute();
                }

                //Next ; increment current payroll
                $query = "SELECT current from {ek_hr_payroll_cycle} WHERE coid=:c";
                $current = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => $form_state->getValue('coid')])
                        ->fetchField();
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
                    //$options = array();
                    $param['coid'] = $form_state->getValue('coid');
                    $param['month'] = $current;
                   
                    $param = serialize($param);
                    $log = t("User @u has posted HR @now data with expenses", ['@u' => \Drupal::currentUser()->getUsername(), '@now' => $current]);
                    \Drupal::logger('ek_hr')->notice( $log );
                    $form_state->setRedirect('ek_finance_payroll.record', array('param' => $param));
                } else {
                    \Drupal::messenger()->addStatus(t('Posting recorded'));
                    $log = t("User @u has posted HR @now data without expenses", ['@u' => \Drupal::currentUser()->getUsername(), '@now' => $current]);
                    \Drupal::logger('ek_hr')->notice( $log ); 
                }
            } //if move
            else {
                \Drupal::messenger()->addError(t('Error while trying to post data.'));
            }
        }//step 2
    }

}