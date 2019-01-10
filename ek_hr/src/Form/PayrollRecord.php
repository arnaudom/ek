<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\PayrollRecord.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to ecord or edit payroll
 */
class PayrollRecord extends FormBase {

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
        return 'payroll_record';
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
            '#prefix' => '<div class="container-inline">',
        );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                '#limit_validation_errors' => [['coid']],
                '#suffix' => '</div>',
            );
        } else {
            $form['next'] = array(
                '#type' => 'item',
                '#suffix' => '</div>',
            );
        }

        if ($form_state->get('step') > 1) {

            $query = "SELECT id,name from {ek_hr_workforce} WHERE company_id=:coid";
            $a = array(':coid' => $form_state->getValue('coid'));

            $employees = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchAllKeyed();

            $form['eid'] = array(
                '#type' => 'select',
                '#size' => 5,
                '#options' => $employees,
                '#title' => t('employees'),
                '#limit_validation_errors' => [['coid'],['eid']],
                '#prefix' => '<div class="table"><div class="row "><div class="cell">',
                '#suffix' => '</div></div></div>',
                '#ajax' => array(
                    'callback' => array($this, 'payroll'),
                    'wrapper' => 'hr_payroll',
                    'method' => 'replace',
                )
            );
        }



        $form['hr'] = array(
            '#title' => t("Payroll"),
            '#prefix' => '<div id="hr_payroll">',
            '#suffix' => '</div>',
            '#type' => 'fieldset',
        );

        if ($form_state->getValue('eid')) {


            $form_state->set('step', 3);

            //
            // collect needed data from employee profile first
            //

            $query = "SELECT * from {ek_hr_workforce} WHERE id=:id";
            $a = array(':id' => $form_state->getValue('eid'));
            $e = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchObject();

            // get allowance aparameters for the coid
            $param = NEW HrSettings($form_state->getValue('coid'));
            $ad = $param->HrAd[$form_state->getValue('coid')];



            $category = explode("_", $e->origin);
            if(isset($category[1])){
                $c = "-" . $category[1]; //old format
            } else {
                $c = '-' . $e->origin; //new format
            }
            $settings = array(
                'salary' => $e->salary,
                'salary2' => $e->th_salary,
                'currency' => $e->currency,
                'coid' => $form_state->getValue('coid'),
                'LAF1' => $param->get('ad', 'LAF1' . $c, 'value'),
                'LAF1f' => $param->get('ad', 'LAF1' . $c, 'formula'),
                'LAF2' => $param->get('ad', 'LAF2' . $c, 'value'),
                'LAF2f' => $param->get('ad', 'LAF2' . $c, 'formula'),
                'LAF3' => $param->get('ad', 'LAF3' . $c, 'value'),
                'LAF3f' => $param->get('ad', 'LAF3' . $c, 'formula'),
                'LAF4' => $param->get('ad', 'LAF4' . $c, 'value'),
                'LAF4f' => $param->get('ad', 'LAF4' . $c, 'formula'),
                'LAF5' => $param->get('ad', 'LAF5' . $c, 'value'),
                'LAF5f' => $param->get('ad', 'LAF5' . $c, 'formula'),
                'LAF6' => $param->get('ad', 'LAF6' . $c, 'value'),
                'LAF6f' => $param->get('ad', 'LAF6' . $c, 'formula'),
                'custom_a_val' => array(
                    1 => $param->get('ad', 'LAC1' . $c, 'value'),
                    2 => $param->get('ad', 'LAC2' . $c, 'value'),
                    3 => $param->get('ad', 'LAC3' . $c, 'value'),
                    4 => $param->get('ad', 'LAC4' . $c, 'value'),
                    5 => $param->get('ad', 'LAC5' . $c, 'value'),
                    6 => $param->get('ad', 'LAC6' . $c, 'value'),
                    7 => $param->get('ad', 'LAC7' . $c, 'value'),
                    8 => $param->get('ad', 'LAC8' . $c, 'value'),
                    9 => $param->get('ad', 'LAC9' . $c, 'value'),
                    10 => $param->get('ad', 'LAC10' . $c, 'value'),
                    11 => $param->get('ad', 'LAC11' . $c, 'value'),
                    12 => $param->get('ad', 'LAC12' . $c, 'value'),
                    13 => $param->get('ad', 'LAC13' . $c, 'value'),
                ),
                'custom_a_for' => array(
                    1 => $param->get('ad', 'LAC1' . $c, 'formula'),
                    2 => $param->get('ad', 'LAC2' . $c, 'formula'),
                    3 => $param->get('ad', 'LAC3' . $c, 'formula'),
                    4 => $param->get('ad', 'LAC4' . $c, 'formula'),
                    5 => $param->get('ad', 'LAC5' . $c, 'formula'),
                    6 => $param->get('ad', 'LAC6' . $c, 'formula'),
                    7 => $param->get('ad', 'LAC7' . $c, 'formula'),
                    8 => $param->get('ad', 'LAC8' . $c, 'formula'),
                    9 => $param->get('ad', 'LAC9' . $c, 'formula'),
                    10 => $param->get('ad', 'LAC10' . $c, 'formula'),
                    11 => $param->get('ad', 'LAC11' . $c, 'formula'),
                    12 => $param->get('ad', 'LAC12' . $c, 'formula'),
                    13 => $param->get('ad', 'LAC13' . $c, 'formula'),
                ),
                'custom_d_val' => array(
                    1 => $param->get('ad', 'LDC1' . $c, 'value'),
                    2 => $param->get('ad', 'LDC2' . $c, 'value'),
                    3 => $param->get('ad', 'LDC3' . $c, 'value'),
                    4 => $param->get('ad', 'LDC4' . $c, 'value'),
                    5 => $param->get('ad', 'LDC5' . $c, 'value'),
                    6 => $param->get('ad', 'LDC6' . $c, 'value'),
                    7 => $param->get('ad', 'LDC7' . $c, 'value'),
                ),
                'LDF1' => $param->get('ad', 'LDF1' . $c, 'value'),
                'LDF2' => $param->get('ad', 'LDF2' . $c, 'value'),
                'LDF1_f' => $param->get('ad', 'LDF1' . $c, 'formula'),
                'LDF2_f' => $param->get('ad', 'LDF2' . $c, 'formula'),
                'custom_d_for' => array(
                    1 => $param->get('ad', 'LDC1' . $c, 'formula'),
                    2 => $param->get('ad', 'LDC2' . $c, 'formula'),
                    3 => $param->get('ad', 'LDC3' . $c, 'formula'),
                    4 => $param->get('ad', 'LDC4' . $c, 'formula'),
                    5 => $param->get('ad', 'LDC5' . $c, 'formula'),
                    6 => $param->get('ad', 'LDC6' . $c, 'formula'),
                    7 => $param->get('ad', 'LDC7' . $c, 'formula'),
                ),
                'fund1_calc' => $param->get('param', 'fund_1', ['calcul','value']),
                'fund1_pc_yer' => $param->get('param', 'fund_1', ['employer','value']),
                'fund1_pc_yee' => $param->get('param', 'fund_1', ['employee','value']),
                'fund1_base' => $param->get('param','fund_1', ['base','value']),
                'fund2_calc' => $param->get('param', 'fund_2', ['calcul','value']),
                'fund2_pc_yer' => $param->get('param', 'fund_2', ['employer','value']),
                'fund2_pc_yee' => $param->get('param', 'fund_2', ['employee','value']),
                'fund2_base' => $param->get('param','fund_2', ['base','value']),
                'fund3_calc' => $param->get('param', 'fund_3', ['calcul','value']),
                'fund3_pc_yer' => $param->get('param', 'fund_3', ['employer','value']),
                'fund3_pc_yee' => $param->get('param', 'fund_3', ['employee','value']),
                'fund3_base' => $param->get('param', 'fund_3', ['base','value']),
                'fund4_calc' => $param->get('param', 'fund_4', ['calcul','value']),
                'fund4_pc_yer' => $param->get('param', 'fund_4', ['employer','value']),
                'fund4_pc_yee' => $param->get('param', 'fund_4', ['employee','value']),
                'fund4_base' => $param->get('param', 'fund_4', ['base','value']),
                'fund5_calc' => $param->get('param', 'fund_5', ['calcul','value']),
                'fund5_pc_yer' => $param->get('param', 'fund_5', ['employer','value']),
                'fund5_pc_yee' => $param->get('param', 'fund_5', ['employee','value']),
                'fund5_base' => $param->get('param', 'fund_5', ['base','value']),
                'tax_calc' => $param->get('param', 'tax', ['calcul','value']),
                'tax_base' => $param->get('param', 'tax', ['base','value']),
                'tax_pc' => $param->get('param', 'tax', ['employee','value']),
                'tax_pcr' => $param->get('param', 'tax', ['employer','value']),
                'tax_category' => $e->itax_c,
            );

            $form['hr']['#attached']['drupalSettings']['ek_hr'] = $settings;

            //
            // collect data from previous salary post if any
            //

//1
            $query = "SELECT count(id) from {ek_hr_workforce_pay} WHERE id=:id";
            $a = array(':id' => $form_state->getValue('eid'));
            $count = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();
//2 
            if ($count > 0) {
                $query = "SELECT * from {ek_hr_workforce_pay} WHERE id=:id";
                $post = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)
                        ->fetchObject();
            } else {
                $post = NULL;
            }

            $query = "SELECT current FROM {ek_hr_payroll_cycle} WHERE coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $current = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();


            if ($current == '') {
                $current = date('Y-m');
                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_payroll_cycle')
                        ->fields(array('coid' => $form_state->getValue('coid'), 'current' => $current))
                        ->execute();
            }

//show employee data for ref
            
            $form['hr']['data'] = array(
                '#title' => t("Employee data"),
                '#prefix' => '<div id="hr_data">',
                '#suffix' => '</div>',
                '#type' => 'fieldset',
            );

            $form['hr']['data']['id'] = array(
                '#markup' => t('ID') . ': <b>' . $e->id . "</b> ",
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['hr']['data']['thiseid'] = array(
                '#type' => 'hidden',
                '#value' => $e->id,
            );
           
            $form['hr']['data']['month'] = array(
                '#markup' => t('Payroll month') . ': <b>' . $current . '</b>',
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div></div>',
                '#value' => $current,
            );
           
            $form['hr']['data']['payroll_month'] = array(
                '#type' => 'hidden',
                '#value' => $current,
            );

            $form['hr']['data']['salary'] = array(
                '#markup' => t('basic salary') . ': <b>' . number_format($e->salary, 2) . "</b> " . $e->currency,
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );
            $form['hr']['data']['origin'] = array(
                '#markup' => t('category') . ': <b>' . $e->origin . '</b>',
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );

            $form['hr']['data']['rank'] = array(
                '#markup' => t('rank') . ': <b>' . $e->rank . '</b>',
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );
            $form['hr']['data']['location'] = array(
                '#markup' => t('location') . ': <b>' . $e->location . '</b>',
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );
            $form['hr']['data']['start'] = array(
                '#markup' => t('start date') . ': <b>' . $e->start . '</b>',
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['hr']['data']['resign'] = array(
                '#markup' => t('resigned date') . ': <b>' . $e->resign . '</b>',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );
            $form['hr']['data']['active'] = array(
                '#markup' => t('status') . ': <b>' . $e->active . ', ' . $e->e_status . '</b>',
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );

            $form['hr']['data']['comment'] = array(
                '#type' => 'item',
                '#markup' => "<p>" . $e->note . "</p>",
                
            );
//row 1
            $form['hr']["work_base"] = array(
                '#type' => 'textfield',
                '#id' => 'work_base',
                '#title' => t("units work base"),
                '#title_display' => 'after',
                '#required' => TRUE,
                '#default_value' => isset($post->d_pay) ? $post->d_pay : 0 ,
                //'#value' => isset($post->d_pay) ? $post->d_pay : 0,
                '#size' => 5,
                '#prefix' => '<div class="table"><div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
                '#attributes' => array('class' => array('calculate')),
            );

            $form['hr']["unit_work"] = array(
                '#type' => 'textfield',
                '#id' => 'unit_work',
                '#title' => t("units worked"),
                '#title_display' => 'after',
                '#required' => TRUE,
                '#size' => 8,
                '#default_value' => isset($post->n_days) ? $post->n_days : 0 ,
                //'#value' => isset($post->n_days) ? $post->n_days : 0,
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
                '#attributes' => array('class' => array('calculate')),
            );

            $form['hr']["no_pay_day"] = array(
                '#type' => 'textfield',
                '#id' => 'no_pay_day',
                '#title' => t("no pay days"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 3,
                '#default_value' => isset($post->no_payday) ? $post->no_payday : 0 ,
                //'#value' => isset($post->no_payday) ? $post->no_payday : 0,
                '#attributes' => array('title' => t('number of days not paid')),
                '#prefix' => '<div class="cell cellmedium">',
            );

            $form['hr']["leave"] = array(
                '#type' => 'textfield',
                '#id' => 'leave',
                '#title' => t("leaves"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 3,
                '#default_value' => isset($post->tleave) ? $post->tleave : 0 ,
                //'#value' => isset($post->leave) ? $post->leave : 0,
                '#attributes' => array('title' => t('number of days leave'), 'class' => array('calculate')),
                '#suffix' => '</div></div>',
            );

//row 2
            $form['hr']["null0"] = array(
                '#type' => 'item',
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );



            $form['hr']["basic_value"] = array(
                '#type' => 'textfield',
                '#id' => 'basic_value',
                '#title' => t("basic salary"),
                '#title_display' => 'after',
                '#required' => TRUE,
                '#size' => 8,
                //'#default_value' => ($form_state->getValue('basic_value')) ? $form_state->getValue('basic_value') : $post->basic ,
                '#default_value' => isset($post->basic) ? $post->basic : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div></div>',
            );


//row 3
            $form['hr']["overtime_hours"] = array(
                '#type' => 'textfield',
                '#id' => 'overtime_hours',
                '#title' => t("Number of hours overtime"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->n_ot_days) ? $post->n_ot_days : 0 ,
                //'#value' => isset($post->n_ot_days) ? $post->n_ot_days : 0,
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
                '#attributes' => array('class' => array('calculate')),
            );

            $form['hr']["normal_ot"] = array(
                '#type' => 'textfield',
                '#id' => 'normal_ot',
                '#title' => t("Normal OT"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->n_ot_val) ? $post->n_ot_val : 0 ,
                //'#value' => isset($post->n_ot_val) ? $post->n_ot_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF1" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_not'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );

//row 4
            $form['hr']["rest_hours"] = array(
                '#type' => 'textfield',
                '#id' => 'rest_hours',
                '#title' => t("Number of hours rest days"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->r_day) ? $post->r_day : 0 ,
                //'#value' => isset($post->r_day) ? $post->r_day : 0,
                '#attributes' => array('title' => t('hours worked on rest day'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["rest_day_ot"] = array(
                '#type' => 'textfield',
                '#id' => 'rest_day_ot',
                '#title' => t("Rest day OT"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->r_day_val) ? $post->r_day_val : 0 ,
                //'#value' => isset($post->r_day_val) ? $post->r_day_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF2" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_rdot'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );            
//row 5
            $form['hr']["ph_hours"] = array(
                '#type' => 'textfield',
                '#id' => 'ph_hours',
                '#title' => t("Number of hours on PH"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->ph_day) ? $post->ph_day : 0 ,
                //'#value' => isset($post->ph_day) ? $post->ph_day : 0,
                '#attributes' => array('title' => t('hours worked on Public Holidays'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["ph_ot"] = array(
                '#type' => 'textfield',
                '#id' => 'ph_ot',
                '#title' => t("PH OT"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->ph_day_val) ? $post->ph_day_val : 0 ,
                //'#value' => isset($post->ph_day_val) ? $post->ph_day_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF3" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_phot'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );  
//row 6
            $form['hr']["mc_days"] = array(
                '#type' => 'textfield',
                '#id' => 'mc_days',
                '#title' => t("Number of days medical leave"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->mc_day) ? $post->mc_day : 0 ,
                //'#value' => isset($post->mc_day) ? $post->mc_day : 0,
                '#attributes' => array('title' => t('paid medical leave days'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["mc_day_val"] = array(
                '#type' => 'textfield',
                '#id' => 'mc_day_val',
                '#title' => t("Medical leave"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->mc_day_val) ? $post->mc_day_val : 0 ,
                //'#value' => isset($post->mc_day_val) ? $post->mc_day_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF4" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_mc'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );              

//row 7
            $form['hr']["x_hours"] = array(
                '#type' => 'textfield',
                '#id' => 'x_hours',
                '#title' => t("Number of extra hours"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->xr_hours) ? $post->xr_hours : 0 ,
                //'#value' => isset($post->xr_hours) ? $post->xr_hours : 0,
                '#attributes' => array('title' => t('extra hours worked'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["x_hours_val"] = array(
                '#type' => 'textfield',
                '#id' => 'x_hours_val',
                '#title' => t("Extra hours"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->xr_hours_val) ? $post->xr_hours_val : 0 ,
                //'#value' => isset($post->xr_hours_val) ? $post->xr_hours_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF5" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_xh'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );  
//row 8
            $form['hr']["turnover"] = array(
                '#type' => 'textfield',
                '#id' => 'turnover',
                '#title' => t("Turnover"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->turnover) ? $post->turnover : 0 ,
                //'#value' => isset($post->turnover) ? $post->turnover : 0,
                '#attributes' => array('title' => t('value to calculate commission'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["commission"] = array(
                '#type' => 'textfield',
                '#id' => "commission",
                '#title' => $param->get('ad', "LAF6" . $c, 'description'),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->commission) ? $post->commission : 0 ,
                //'#value' => isset($post->commission) ? $post->commission : 0,
                '#attributes' => array('class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );
            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF6" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['formula_com'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div>',
            );  
            $form['hr']['tax0'] = array(
                '#type' => 'checkbox',
                //'#default_value' => $param->get('ad', "LAF6".$c, 'tax'),
                '#value' => $param->get('ad', "LAF6" . $c, 'tax'),
                '#attributes' => array('title' => t('include tax'), 'class' => array('calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );


//row 9 // custom

            $form['hr']['customA'] = array(
                '#type' => 'details',
                '#title' => t('custom allowances'),
                '#collapsible' => TRUE,
                '#open' => TRUE,
            );

            $form['hr']['customA']["null1"] = array(
                '#type' => 'item',
                '#markup' => '',
                '#prefix' => '<div class="row cellbordertop"><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']['customA']["custom_aw1"] = array(
                '#type' => 'textfield',
                '#id' => 'custom_aw1',
                '#title' => $param->get('ad', 'LAC1' . $c, 'description'),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->custom_aw1) ? $post->custom_aw1 : 0 ,
                //'#value' => isset($post->custom_aw1) ? $post->custom_aw1 : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']['customA']['tax1'] = array(
                '#type' => 'checkbox',
                '#id' => 'tax1',
                //'#default_value' => $param->get('ad', 'LAC1'.$c, 'tax'),
                '#value' => $param->get('ad', 'LAC1' . $c, 'tax'),
                '#attributes' => array('title' => t('include tax'), 'class' => array('calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div>',
            );

            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAC1" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
            $form['hr']['customA']['formula1'] = array(
                '#type' => 'item',
                '#markup' => $mark,
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div></div>',
            );

//row 10 to 21
            //loop allowances

            for ($i = 2; $i <= 13; $i++) {

                $form['hr']['customA']["null$i"] = array(
                    '#type' => 'item',
                    '#prefix' => '<div class="row cellbordertop"><div class="cell cellmedium">',
                    '#suffix' => '</div>',
                );

                $custom_aw = 'custom_aw' . $i;

                $form['hr']['customA']["custom_aw$i"] = array(
                    '#type' => 'textfield',
                    '#id' => "custom_aw$i",
                    '#title' => $param->get('ad', "LAC$i" . $c, 'description'),
                    '#title_display' => 'after',
                    '#required' => FALSE,
                    '#size' => 8,
                    '#default_value' => isset($post->$custom_aw) ? $post->$custom_aw : 0 ,
                    //'#value' => isset($post->$custom_aw) ? $post->$custom_aw : 0,
                    '#attributes' => array('class' => array('amount', 'calculate')),
                    '#prefix' => '<div class="cell cellmedium">',
                    '#suffix' => '</div>',
                );

                $form['hr']['customA']['tax' . $i] = array(
                    '#type' => 'checkbox',
                    '#id' => 'tax' . $i,
                    //'#default_value' => $param->get('ad', "LAC$i".$c, 'tax'),
                    '#value' => $param->get('ad', "LAC$i" . $c, 'tax'),
                    '#attributes' => array('title' => t('include tax'), 'class' => array('calculate')),
                    '#prefix' => '<div class="cell cellnarrow">',
                    '#suffix' => '</div>',
                );

                $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAC$i" . $c;
                $opt = serialize($opt);
                $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
                $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
                $form['hr']['customA']['formula' . $i] = array(
                    '#type' => 'item',
                    '#markup' => $mark,
                    '#prefix' => '<div class="cell cellnarrow">',
                    '#suffix' => '</div></div>',
                );
            }//loop

//total gross
            $form['hr']["null14"] = array(
                '#type' => 'item',
                '#markup' => t('Total gross'),
                '#prefix' => '<div class="row"><div class="cell cellmedium back_green">',
                '#suffix' => '</div>',
            );

            $form['hr']["total_gross"] = array(
                '#type' => 'textfield',
                '#id' => 'total_gross',
                '#title' => '',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 10,
                '#default_value' => isset($post->gross) ? $post->gross : 0 ,
                //'#value' => isset($post->gross) ? $post->gross : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium back_green">',
                '#suffix' => '</div></div>',
            );



// deductions
//row 23
            $form['hr']["less_hours"] = array(
                '#type' => 'textfield',
                '#id' => 'less_hours',
                '#title' => t("Number of less hours"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 5,
                '#default_value' => isset($post->less_hours) ? $post->less_hours : 0 ,
                //'#value' => isset($post->less_hours) ? $post->less_hours : 0,
                '#attributes' => array('title' => t('less hours number'), 'class' => array('calculate')),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["less_hours_val"] = array(
                '#type' => 'textfield',
                '#id' => 'less_hours_val',
                '#title' => t("Less hours"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->less_hours_val) ? $post->less_hours_val : 0 ,
                //'#value' => isset($post->less_hours_val) ? $post->less_hours_val : 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div></div>',
            );

//row 24
            $form['hr']["null24"] = array(
                '#type' => 'item',
                //'#attributes' => array(),
                '#prefix' => '<div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["advance"] = array(
                '#type' => 'textfield',
                '#id' => 'advance',
                '#title' => t("Advance"),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->advance) ? $post->advance : 0 , //todo add from advance records
                //'#value' => isset($post->advance) ? $post->advance : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div></div>',
            );


            $form['hr']['customD'] = array(
                '#type' => 'details',
                '#title' => t('custom deductions'),
                '#collapsible' => TRUE,
                '#open' => TRUE,
            );


//row 25 to 31
            //loop deductions
            for ($i = 1; $i <= 6; $i++) {
                $r = 24 + $i;
                $form['hr']['customD']["null$r"] = array(
                    '#type' => 'item',
                    '#prefix' => '<div class="row cellbordertop"><div class="cell cellmedium">',
                    '#suffix' => '</div>',
                );

                $custom_d = 'custom_d' . $i;

                $form['hr']['customD']["custom_d$i"] = array(
                    '#type' => 'textfield',
                    '#id' => "custom_d$i",
                    '#title' => $param->get('ad', "LDC$i" . $c, 'description'),
                    '#title_display' => 'after',
                    '#required' => FALSE,
                    '#size' => 8,
                    '#default_value' => isset($post->$custom_d) ? $post->$custom_d : 0 ,
                    // isset($post->$custom_d) ? $post->$custom_d : 0,
                    '#attributes' => array('class' => array('amount', 'calculate')),
                    '#prefix' => '<div class="cell cellmedium">',
                    '#suffix' => '</div>',
                );

                $form['hr']['customD']['tax' . $r] = array(
                    '#type' => 'checkbox',
                    '#id' => 'tax' . $r,
                    '#value' => $param->get('ad', "LDC$i" . $c, 'tax'),
                    '#attributes' => array('title' => t('include tax'), 'class' => array('calculate')),
                    '#prefix' => '<div class="cell cellnarrow">',
                    '#suffix' => '</div>',
                );
                $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LDC$i" . $c;
                $opt = serialize($opt);
                $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
                $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'>[f]</a>";
                $form['hr']['customD']['formula' . $r] = array(
                    '#type' => 'item',
                    '#markup' => $mark,
                    '#prefix' => '<div class="cell cellnarrow">',
                    '#suffix' => '</div></div>',
                );
            }

//total deductions
            $form['hr']["null31"] = array(
                '#type' => 'item',
                '#markup' => t('Total deductions'),
                '#prefix' => '<div class="row"><div class="cell cellmedium back_red">',
                '#suffix' => '</div>',
            );

            $form['hr']["total_deductions"] = array(
                '#type' => 'textfield',
                '#id' => 'total_deductions',
                '#title' => '',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 10,
                '#value' => 0,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium back_red">',
                '#suffix' => '</div></div>',
            );


//row 33
            $form['hr']["fund1_employer"] = array(
                '#type' => 'textfield',
                '#id' => 'fund1_employer',
                '#title' => $param->get('param', 'fund_1', ['name','value']),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->epf_er) ? $post->epf_er : 0 ,
                //'#value' => isset($post->epf_er) ? $post->epf_er : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="table"><div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["fund1_employee"] = array(
                '#type' => 'textfield',
                '#id' => 'fund1_employee',
                //'#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->epf_yee) ? $post->epf_yee : 0 ,
                //'#value' => isset($post->epf_yee) ? $post->epf_yee : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["thisepf"] = array(
                '#type' => 'checkbox',
                '#id' => 'thisepf',
                '#value' => 0,
                '#attributes' => array('title' => t('include this fund'), 'class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div><div id="fund1_employer_alert" class="cell cellsmall back_yellow"/></div>',
            );

//row 34
            $form['hr']["fund2_employer"] = array(
                '#type' => 'textfield',
                '#id' => 'fund2_employer',
                '#title' => $param->get('param', 'fund_2', ['name','value']),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->socso_er) ? $post->socso_er : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="table"><div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["fund2_employee"] = array(
                '#type' => 'textfield',
                '#id' => 'fund2_employee',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->socso_yee) ? $post->socso_yee : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["thissoc"] = array(
                '#type' => 'checkbox',
                '#id' => 'thissoc',
                '#value' => 0,
                '#attributes' => array('title' => t('include this fund'), 'class' => array('calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div><div id="fund2_employer_alert" class="cell cellsmall back_yellow"/></div>',
            );


//row 35
            $form['hr']["fund3_employer"] = array(
                '#type' => 'textfield',
                '#id' => 'fund3_employer',
                '#title' => $param->get('param', 'fund_3', ['name','value']),
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->with_yer) ? $post->with_yer : 0 ,
                //'#value' => isset($post->with_yer) ? $post->with_yer : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="table"><div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["fund3_employee"] = array(
                '#type' => 'textfield',
                '#id' => 'fund3_employee',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->with_yee) ? $post->with_yee : 0 ,
                //'#value' => isset($post->with_yee) ? $post->with_yee : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["thiswith"] = array(
                '#type' => 'checkbox',
                '#id' => 'thiswith',
                '#value' => 0,
                '#attributes' => array('title' => t('include this fund'), 'class' => array('calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div><div id="fund3_employer_alert" class="cell cellsmall back_yellow"/></div>',
            );

//row 36 income tax
            $form['hr']["tax_employee"] = array(
                '#type' => 'item',
                '#markup' => $param->get('param', 'tax', ['name','value']),
                '#prefix' => '<div class="table"><div class="row "><div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["income_tax"] = array(
                '#type' => 'textfield',
                '#id' => 'income_tax',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#size' => 8,
                '#default_value' => isset($post->incometax) ? $post->incometax : 0 ,
                //'#value' => isset($post->incometax) ? $post->incometax : 0,
                '#attributes' => array('class' => array('amount', 'calculate')),
                '#prefix' => '<div class="cell cellmedium">',
                '#suffix' => '</div>',
            );

            $form['hr']["thisincometax"] = array(
                '#type' => 'checkbox',
                '#id' => 'thisincometax',
                '#value' => 0,
                '#attributes' => array('title' => t('include personal tax'), 'class' => array('calculate')),
                '#prefix' => '<div class="cell cellnarrow">',
                '#suffix' => '</div><div id="incometax_alert" class="cell cellsmall back_yellow"/></div>',
            );



//total net
            $form['hr']["null37"] = array(
                '#type' => 'item',
                '#markup' => t('Total net'),
                '#prefix' => '<div class="row"><div class="cell cellmedium back_blue">',
                '#suffix' => '</div>',
            );

            $form['hr']["total_net"] = array(
                '#type' => 'textfield',
                '#id' => 'total_net',
                '#title' => '',
                '#title_display' => 'after',
                '#required' => FALSE,
                '#default_value' => isset($post->nett) ? $post->nett : 0 ,
                //'#value' => isset($post->nett) ? $post->nett : 0,
                '#size' => 10,
                '#attributes' => array('readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => '<div class="cell cellmedium back_blue">',
                '#suffix' => '</div></div>',
            );

            $form['hr']['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['hr']['actions']['clear'] = array(
                '#type' => 'button',
                '#value' => $this->t('Clear'),
                '#attributes' => array('class' => array('back_red')),
                '#ajax' => array(
                    'callback' => array($this, 'clearForm'),
                    'wrapper' => 'alert1',
                ),
                '#suffix' => ''
            );

            $form['hr']['actions']['save'] = array(
                '#id' => 'savebutton',
                '#type' => 'button',
                '#value' => $this->t('Save'),
                '#ajax' => array(
                    'callback' => array($this, 'recordPay'),
                    'wrapper' => 'alert2',
                ),
            );


            $form['hr']['actions']['alert'] = array(
                '#markup' => "<div id='alert1'></div><div id='alert2'></div>",
            );

            $form['hr']['actions']['top'] = array(
                '#type' => 'item',
                '#markup' => '<a href="#content">' . t('top') . '</a>',
            );
        }  //if eid


        $form['hr']['#attached']['library'][] = 'ek_hr/ek_hr.payroll';
        $form['#id'] = 'payroll_data';


        return $form;
    }

    /**
     * callback
     */
    public function payroll(array &$form, FormStateInterface $form_state) {
        return $form['hr'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
            return $form;
        }
        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);
            $form_state->setRebuild();
            return $form;
        }
        
        if ($form_state->get('step') == 3) {
            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function recordPay(array &$form, FormStateInterface $form_state) {
        
        if ($form_state->get('step') == 3) {
            
            //filter data input
            $elements =['work_base','unit_work','no_pay_day','leave','overtime_hours',
                'rest_hours','ph_hours','mc_days','x_hours','turnover','commission',
                'less_hours','advance','fund1_employer','fund1_employee','fund2_employer','fund2_employee',
                'fund3_employer','fund3_employee','income_tax'];
            $response = new \Drupal\Core\Ajax\AjaxResponse();
            $a_error =[];
            $a_valid = [];
            foreach ($elements as $key => $element){
                if(!is_numeric($form_state->getValue($element))) {
                    $a_error[] = '#' . $element;
                }
                 else {
                    $a_valid[] = '#' . $element;
                }
            }
        for ($i = 1; $i <= 13; $i++) {
                if(!is_numeric($form_state->getValue('custom_aw' . $i))) {
                    $a_error[] = '#' . 'custom_aw' . $i;
                } else {
                    $a_valid[] = '#' . 'custom_aw' . $i;
                }
            }
            for ($i = 1; $i <= 6; $i++) {
                if(!is_numeric($form_state->getValue("custom_d$i"))) {
                    $a_error[] = '#' . 'custom_d' . $i;
                } else {
                    $a_valid[] = '#' . 'custom_d' . $i;
                }
            }
            
        if(!empty($a_error)) { 
            $command1 = new \Drupal\Core\Ajax\InvokeCommand(implode(',',$a_error), 'addClass', ['error']);
            $response->addCommand($command1);
            $alert = new \Drupal\Core\Ajax\HtmlCommand('#alert2',"<div class='messages messages--error'>" . t('Error') . "</div>");
            $response->addCommand($alert);
        }
        $command2 = new \Drupal\Core\Ajax\InvokeCommand(implode(',',$a_valid), 'removeClass', ['error']);
        $response->addCommand($command2);
        
        if(isset($alert)) {
            return $response;
        }

            $fields = array(
                'month' => $form_state->getValue('payroll_month'),
                'd_pay' => $form_state->getValue('work_base'),
                'n_days' => $form_state->getValue('unit_work'),
                'basic' => $form_state->getValue('basic_value'),
                'n_ot_days' => $form_state->getValue('overtime_hours'),
                'n_ot_val' => $form_state->getValue('normal_ot'),
                'r_day' => $form_state->getValue('rest_hours'),
                'r_day_val' => $form_state->getValue('rest_day_ot'),
                'ph_day' => $form_state->getValue('ph_hours'),
                'ph_day_val' => $form_state->getValue('ph_ot'),
                'mc_day' => $form_state->getValue('mc_days'),
                'mc_day_val' => $form_state->getValue('mc_day_val'),
                'xr_hours' => $form_state->getValue('x_hours'),
                'xr_hours_val' => $form_state->getValue('x_hours_val'),
                'tleave' => $form_state->getValue('leave'),
                'custom_aw1' => $form_state->getValue('custom_aw1'),
                'custom_aw2' => $form_state->getValue('custom_aw2'),
                'custom_aw3' => $form_state->getValue('custom_aw3'),
                'custom_aw4' => $form_state->getValue('custom_aw4'),
                'custom_aw5' => $form_state->getValue('custom_aw5'),
                'custom_aw6' => $form_state->getValue('custom_aw6'),
                'custom_aw7' => $form_state->getValue('custom_aw7'),
                'custom_aw8' => $form_state->getValue('custom_aw8'),
                'custom_aw9' => $form_state->getValue('custom_aw9'),
                'custom_aw10' => $form_state->getValue('custom_aw10'),
                'custom_aw11' => $form_state->getValue('custom_aw11'),
                'custom_aw12' => $form_state->getValue('custom_aw12'),
                'custom_aw13' => $form_state->getValue('custom_aw13'),
                'turnover' => $form_state->getValue('turnover'),
                'commission' => $form_state->getValue('commission'),
                'gross' => $form_state->getValue('total_gross'),
                'no_payday' => $form_state->getValue('no_pay_day'),
                'less_hours' => $form_state->getValue('less_hours'),
                'less_hours_val' => $form_state->getValue('less_hours_val'),
                'advance' => $form_state->getValue('advance'),
                'custom_d1' => $form_state->getValue('custom_d1'),
                'custom_d2' => $form_state->getValue('custom_d2'),
                'custom_d3' => $form_state->getValue('custom_d3'),
                'custom_d4' => $form_state->getValue('custom_d4'),
                'custom_d5' => $form_state->getValue('custom_d5'),
                'custom_d6' => $form_state->getValue('custom_d6'),
                'epf_yee' => $form_state->getValue('fund1_employee'),
                'socso_yee' => $form_state->getValue('fund2_employee'),
                'epf_er' => $form_state->getValue('fund1_employer'),
                'socso_er' => $form_state->getValue('fund2_employer'),
                'with_yer' => $form_state->getValue('fund3_employer'),
                'with_yee' => $form_state->getValue('fund3_employee'),
                'incometax' => $form_state->getValue('income_tax'),
                'deduction' => $form_state->getValue('total_deductions'),
                'nett' => $form_state->getValue('total_net'),
            );

            // check if account already exist
            $query = "SELECT count(id) from {ek_hr_workforce_pay} WHERE id=:id";
            $a = array(':id' => $form_state->getValue('thiseid'));
            $post = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();

            if ($post == 1) {
                //update
                $db = Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce_pay')
                        ->fields($fields)
                        ->condition('id', $form_state->getValue('thiseid'))
                        ->execute();
            } else {
                //insert
                $fields['id'] = $form_state->getValue('thiseid');
                $db = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_workforce_pay')
                        ->fields($fields)
                        ->execute();
            }

            $alert = new \Drupal\Core\Ajax\HtmlCommand('#alert2',"<div class='messages messages--status'>" . t('Data updated') . "</div>");
            $response->addCommand($alert);
            return $response;
            
        }
    }

    /**
     * Callback
     */
    public function clearForm(array &$form, FormStateInterface $form_state) {

        $db = Database::getConnection('external_db', 'external_db')
                ->delete('ek_hr_workforce_pay')
                ->condition('id', $form_state->getValue('thiseid'))
                ->execute();


        $form['hr']['actions']['alert']['#markup'] = "<div id='alert' class='messages messages--warning'>" . t('Data cleared') . "</div>";
        return $form['hr']['actions']['alert'];

    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

}
