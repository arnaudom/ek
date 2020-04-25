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
class PayrollRecord extends FormBase
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
        return 'payroll_record';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $coid = null, $eid = null)
    {
        if ($coid != null && $eid != null) {
            if (array_key_exists($coid, AccessCheck::CompanyListByUid())) {
                $form_state->set('step', 2);
                $form_state->setValue('coid', $coid);
                

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_hr_workforce', 'h');
                $query->fields('h', ['id', 'name', 'custom_id','administrator'])
                        ->condition('company_id', $form_state->getValue('coid'))
                        ->condition('active', 'resigned', '<>')
                        ->orderBy('name');
                $employees = [];
                $e_list = $query->execute();
                while ($e = $e_list->fetchObject()) {
                    if ($e->administrator != 0) {
                        $admins = explode(',', $e->administrator);
                        if (in_array(\Drupal::currentUser()->id(), $admins)) {
                            $id = isset($e->custom_id) ? $e->custom_id : $e->id;
                            $employees[$e->id] = $id . " - " . $e->name;
                        }
                    } else {
                        $id = isset($e->custom_id) ? $e->custom_id : $e->id;
                        $employees[$e->id] = $id . " - " . $e->name;
                    }
                    
                    if ($e->id == $eid) {
                        //validate current id is editable
                        //i.e. filter resigned
                        /* TODO disable form if not editable */
                        $form_state->setValue('eid', $eid);
                    }
                }
            }
        }

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }



        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => ($coid) ? $coid : $form_state->getValue('coid'),
            '#title' => t('company'),
            '#required' => true,
            '#prefix' => '<div class="container-inline">',
            '#ajax' => array(
                'callback' => array($this, 'get_employees'),
                'wrapper' => 'employees',
            ),
        );


        if (!isset($employees)) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'h');
            $query->fields('h', ['id', 'name', 'custom_id','administrator'])
                    ->condition('company_id', $form_state->getValue('coid'))
                    ->condition('active', 'resigned', '<>')
                    ->orderBy('name');
            $employees = [];
            $e_list = $query->execute();
            while ($e = $e_list->fetchObject()) {
                if ($e->administrator != 0) {
                    $admins = explode(',', $e->administrator);
                    if (in_array(\Drupal::currentUser()->id(), $admins)) {
                        $id = isset($e->custom_id) ? $e->custom_id : $e->id;
                        $employees[$e->id] = $id . " - " . $e->name;
                    }
                } else {
                    $id = isset($e->custom_id) ? $e->custom_id : $e->id;
                    $employees[$e->id] = $id . " - " . $e->name;
                }
            }
        }

        $form['eid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $employees,
            '#title' => t('employees'),
            '#required' => true,
            '#default_value' => (!null == $form_state->getValue('eid')) ? $form_state->getValue('eid') : null,
            '#prefix' => "<div id='employees'",
            '#suffix' => '</div></div>',
            '#ajax' => array(
                'callback' => array($this, 'get_data'),
                'wrapper' => 'hrdata',
            ),
        );


        $post = new \stdClass();
        $e = new \stdClass();
        $c = 0;
        $settings = [];
        $current = "";
        $markup = '';

        if ($form_state->getValue('eid')) {
            $form_state->set('step', 2);

            //build payroll data
            $query = "SELECT * from {ek_hr_workforce} WHERE id=:id";
            $a = array(':id' => $form_state->getValue('eid'));
            $e = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchObject();

            $category = explode("_", $e->origin);
            if (isset($category[1])) {
                $c = $category[1]; //old format
            } else {
                $c = $e->origin; //new format
            }
            // get allowance parameters for the coid
            $param = new HrSettings($form_state->getValue('coid'));
            $ad = $param->HrAd[$form_state->getValue('coid')];
            //re-structure paramaters to pass to js
            $settings = $this->build_settings($param, $ad, $c);
            $settings['salary'] = $e->salary;
            $settings['salary2'] = $e->th_salary;
            $settings['currency'] = $e->currency;
            $settings['coid'] = $form_state->getValue('coid');
            $settings['eid'] = $form_state->getValue('eid');
            $settings['tax_category'] = $e->itax_c;
            $settings['ad_category'] = $e->origin;
            $settings['error'] = t('Wrong input');

            $query = "SELECT current FROM {ek_hr_payroll_cycle} WHERE coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $current = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();

            if ($current == '' && $form_state->getValue('coid')) {
                $current = date('Y-m');
                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_payroll_cycle')
                        ->fields(array('coid' => $form_state->getValue('coid'), 'current' => $current))
                        ->execute();
            }
            $settings['current_month'] = $current;
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
                $form['post'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                );
                $settings['nett'] = $post->nett;
            } else {
                $post = null;
                $settings['nett'] = 0;
                $form['post'] = array(
                    '#type' => 'hidden',
                    '#value' => 0,
                );
            }
        } //data step 2


        $form['total_display'] = array(
            '#type' => 'item',
            '#markup' => "<p id='total_display'>0</p>",
        );

        $form['hr'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'hrdata'],
        ];

        if ($form_state->getValue('eid')) {
            $markup = "<ul>";
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('ID') . ': <b>' . $e->id . "</b></li>" : '';
            $markup .= (!null == $form_state->getValue('eid') && $e->custom_id != '' && $e->custom_id != $e->id) ? "<li>" . t('Given ID') . ': <b>' . $e->custom_id . "</b></li>" : '';
            $markup .= "<li>" . t('Payroll month') . ': <b>' . $current . '</b></li>';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('basic salary') . ': <b>' . number_format($e->salary, 2) . " " . $e->currency . "</b></li>" : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('category') . ': <b>' . $e->origin . '</b></li>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('rank') . ': <b>' . $e->rank . '</b></li>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('location') . ': <b>' . $e->location . '</b></li>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('start date') . ': <b>' . $e->start . '</b></li>' : '';
            $markup .= ($e->resign <> '') ? "<li>" . t('resigned date') . ': <b>' . $e->resign . '</b></li>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('status') . ': <b>' . $e->active . ', ' . $e->e_status . '</b></li>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<li>" . t('tax category') . ': <b>' . $e->itax_c . '</b></li></ul>' : '';
            $markup .= (!null == $form_state->getValue('eid')) ? "<p>" . $e->note . "</p>" : '';
        }

        $form['hr']['data'] = array(
            '#title' => (!null == $form_state->getValue('eid')) ? t("Employee data") . ": " . $e->name : t("Employee data"),
            '#type' => 'details',
            '#open' => (!null == $form_state->getValue('eid')) ? true : false,
        );


        $form['hr']['data']['payroll_month'] = array(
            '#type' => 'hidden',
            '#value' => $current,
        );

        $form['hr']['data']['info'] = array(
            '#type' => 'item',
            '#markup' => $markup,
        );



        //Table 1 work data
        $form['hr']['table1'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#theme' => 'table',
            '#header' => '',
            '#rows' => array(),
            '#attributes' => array('id' => 'table1'),
            '#empty' => '',
        );

        $form["work_base"] = array(
            '#type' => 'number',
            '#id' => 'work_base',
            '#title' => t("units work base"),
            '#title_display' => 'after',
            '#required' => true,
            //'#default_value' => isset($post->d_pay) ? $post->d_pay : 0 ,
            '#min' => 0,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->d_pay) ? $post->d_pay : 0),
        );
        $form["unit_work"] = array(
            '#type' => 'number',
            '#id' => 'unit_work',
            '#title' => t("units worked"),
            '#title_display' => 'after',
            '#required' => true,
            '#min' => 0,
            //'#default_value' => isset($post->n_days) ? $post->n_days : 0 ,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->n_days) ? $post->n_days : 0),
        );
        $form["no_pay_day"] = array(
            '#type' => 'number',
            '#id' => 'no_pay_day',
            '#title' => t("no pay days"),
            '#title_display' => 'after',
            '#required' => false,
            '#max' => 31,
            '#min' => 0,
            //'#default_value' => isset($post->no_payday) ? $post->no_payday : 0 ,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'title' => t('number of days not paid'), 'value' => isset($post->no_payday) ? $post->no_payday : 0),
        );
        $form["leave"] = array(
            '#type' => 'number',
            '#id' => 'leave',
            '#title' => t("leaves"),
            '#title_display' => 'after',
            '#required' => false,
            '#max' => 31,
            '#min' => 0,
            //'#default_value' => isset($post->tleave) ? $post->tleave : 0 ,
            '#attributes' => array('title' => t('number of days leave'), 'class' => ['calculate', 'form-number-small'], 'value' => isset($post->tleave) ? $post->tleave : 0),
        );

        $ck = (isset($e->currency)) ? $e->currency : null;
        $val_s = (isset($e->salary)) ? number_format($e->salary, 2) : null;
        $form["basic_value"] = array(
            '#type' => 'textfield',
            '#id' => 'basic_value',
            '#title' => t("basic salary") . " " . $ck,
            '#title_display' => 'after',
            '#required' => true,
            '#size' => 15,
            //'#default_value' => isset($post->basic) ? $post->basic : 0,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => $val_s,)
        );

        //built edit rows for table
        $form['hr']['table1'][] = array(
            'work_base' => &$form['work_base'],
            'unit_work' => &$form['unit_work'],
            'no_pay_day' => &$form['no_pay_day'],
            'leave' => &$form['leave'],
            'basic_value' => &$form['basic_value'],
        );

        $form['hr']['table1']['#rows'] = array(
            'data' => array(
                array('data' => &$form['work_base']),
                array('data' => &$form['unit_work']),
                array('data' => &$form['no_pay_day']),
                array('data' => &$form['leave']),
                array('data' => &$form['basic_value']),
            ),
                //'id' => array('table1row1'),
                //'class' => $rowClass,
        );
        unset($form['work_base']);
        unset($form['unit_work']);
        unset($form['no_pay_day']);
        unset($form['leave']);
        unset($form['basic_value']);

        //Table 2 fixed allowances
        $form['hr']['fa'] = [
            '#title' => t('Fixed allowances'),
            '#type' => 'fieldset',
        ];
        $form['hr']['fa']['table2'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#input' => true,
            '#theme' => 'table',
            '#title' => t('Fixed allowances'),
            '#header' => array(
                'unit' => array(
                    'data' => $this->t('Unit'),
                    'id' => ['tour-item1'],
                ),
                'value' => array(
                    'data' => $this->t('Value'),
                    'id' => ['tour-item2'],
                ),
                'tax' => array(
                    'data' => 'tax',
                    'id' => ['tour-item4'],
                ),
                'formula' => array(
                    'data' => '',
                    'id' => ['tour-item3'],
                ),
            ),
            '#rows' => array(),
            '#attributes' => array('id' => 'table2'),
            '#empty' => '',
        );

        $form["overtime_hours"] = array(
            '#type' => 'number',
            '#id' => 'overtime_hours',
            '#title' => t("Number of hours overtime"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->n_ot_days) ? $post->n_ot_days : 0),
        );
        $form["normal_ot"] = array(
            '#type' => 'textfield',
            '#id' => 'normal_ot',
            '#title' => t("Normal OT"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->n_ot_val) ? $post->n_ot_val : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF1-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_not'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_not'] = array(
            '#type' => 'item',
            '#markup' => '',
        );


        //rows normal OT
        $form['hr']['fa']['table2']['not'] = array(
            'unit' => &$form['overtime_hours'],
            'value' => &$form['normal_ot'],
            'formula' => &$form['formula_not'],
                
        );
        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['overtime_hours']),
                array('data' => &$form['normal_ot']),
                array('data' => &$form['tax_not']),
                array('data' => &$form['formula_not']),
            ),
               
        );
        unset($form['overtime_hours']);
        unset($form['normal_ot']);
        unset($form['formula_not']);
        unset($form['tax_not']);

        $form["rest_hours"] = array(
            '#type' => 'number',
            '#id' => 'rest_hours',
            '#title' => t("Number of hours rest days"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->r_day) ? $post->r_day : 0),
        );
        $form["rest_day_ot"] = array(
            '#type' => 'textfield',
            '#id' => 'rest_day_ot',
            '#title' => t("Rest day OT"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#default_value' => isset($post->r_day_val) ? $post->r_day_val : 0,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->r_day_val) ? $post->r_day_val : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF2-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_rdot'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_rdot'] = array(
            '#type' => 'item',
            '#markup' => '',
        );


        //rows rest day OT
        $form['hr']['fa']['table2']['rdot'] = array(
            'unit' => &$form['rest_hours'],
            'value' => &$form['rest_day_ot'], 'tax' => &$form['tax_rdot'],
            'formula' => &$form['formula_rdot'],
        );

        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['rest_hours']),
                array('data' => &$form['rest_day_ot']),
                array('data' => &$form['tax_rdot']),
                array('data' => &$form['formula_rdot']),
            ),
               
        );
        unset($form['rest_hours']);
        unset($form['rest_day_ot']);
        unset($form['formula_rdot']);
        unset($form['tax_rdot']);

        $form["ph_hours"] = array(
            '#type' => 'number',
            '#id' => 'ph_hours',
            '#title' => t("Number of hours on PH"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->ph_day) ? $post->ph_day : 0),
        );
        $form["ph_ot"] = array(
            '#type' => 'textfield',
            '#id' => 'ph_ot',
            '#title' => t("PH OT"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->ph_day_val) ? $post->ph_day_val : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF3-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_phot'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_phot'] = array(
            '#type' => 'item',
            '#markup' => '',
        );


        //rows PH OT
        $form['hr']['fa']['table2']['phot'] = array(
            'unit' => &$form['ph_hours'],
            'value' => &$form['ph_ot'],
            'tax' => &$form['tax_phot'],
            'formula' => &$form['formula_phot'],
        );

        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['ph_hours']),
                array('data' => &$form['ph_ot']),
                array('data' => &$form['tax_phot']),
                array('data' => &$form['formula_phot']),
            ),
        );
        unset($form['ph_hours']);
        unset($form['ph_ot']);
        unset($form['formula_phot']);
        unset($form['tax_phot']);

        $form["mc_days"] = array(
            '#type' => 'number',
            '#id' => 'mc_days',
            '#title' => t("Number of days medical leave"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->mc_day) ? $post->mc_day : 0),
        );
        $form["mc_day_val"] = array(
            '#type' => 'textfield',
            '#id' => 'mc_day_val',
            '#title' => t("Medical leave"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->mc_day_val) ? $post->mc_day_val : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF4-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_mcot'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_mcot'] = array(
            '#type' => 'item',
            '#markup' => '',
        );


        //rows mc
        $form['hr']['fa']['table2']['mc'] = array(
            'unit' => &$form['mc_days'],
            'value' => &$form['mc_day_val'],
            'tax' => &$form['tax_mcot'],
            'formula' => &$form['formula_mcot'],
        );

        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['mc_days']),
                array('data' => &$form['mc_day_val']),
                array('data' => &$form['tax_mcot']),
                array('data' => &$form['formula_mcot']),
            ),
        );
        unset($form['mc_days']);
        unset($form['mc_day_val']);
        unset($form['formula_mcot']);
        unset($form['tax_mcot']);


        $form["x_hours"] = array(
            '#type' => 'number',
            '#id' => 'x_hours',
            '#title' => t("Number of extra hours"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('class' => ['calculate', 'form-number-small'], 'value' => isset($post->xr_hours) ? $post->xr_hours : 0),
        );
        $form["x_hours_val"] = array(
            '#type' => 'textfield',
            '#id' => 'x_hours_val',
            '#title' => t("Extra hours"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->xr_hours_val) ? $post->xr_hours_val : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF5-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_xhot'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_xhot'] = array(
            '#type' => 'item',
            '#markup' => '',
        );


        //rows extra hours
        $form['hr']['fa']['table2']['xh'] = array(
            'unit' => &$form['x_hours'],
            'value' => &$form['x_hours_val'],
            'tax' => &$form['tax_xhot'],
            'formula' => &$form['formula_xhot'],
        );

        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['x_hours']),
                array('data' => &$form['x_hours_val']),
                array('data' => &$form['tax_xhot']),
                array('data' => &$form['formula_xhot']),
            ),
        );
        unset($form['x_hours']);
        unset($form['x_hours_val']);
        unset($form['formula_xhot']);
        unset($form['tax_xhot']);

        $form["turnover"] = array(
            '#type' => 'textfield',
            '#id' => 'turnover',
            '#title' => t("Turnover"),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('title' => t('value to calculate commission'), 'class' => ['calculate'], 'value' => isset($post->turnover) ? $post->turnover : 0),
        );
        $form["commission"] = array(
            '#type' => 'textfield',
            '#id' => 'commission',
            '#title' => (!null == $form_state->getValue('eid')) ? $ad["LAF6-" . $c]['description'] : t('commision'),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->commission) ? $post->commission : 0),
        );

        $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAF6-" . $c;
        $opt = serialize($opt);
        $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
        $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
        $form['formula_to'] = array(
            '#type' => 'item',
            '#markup' => $mark,
        );
        $form['tax_commision'] = array(
            '#type' => 'checkbox',
            '#id' => 'commision-tax',
            '#attributes' => array('title' => t('include tax'), 'class' => ['calculate'], 'value' => (!null == $form_state->getValue('eid')) ? $ad["LAF6-" . $c]['tax'] : 0),
        );


        //rows commission
        $form['hr']['fa']['table2']['to'] = array(
            'unit' => &$form['turnover'],
            'value' => &$form['commission'],
            'tax' => &$form['tax_commision'],
            'formula' => &$form['formula_to'],
        );

        $form['hr']['fa']['table2']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['turnover']),
                array('data' => &$form['commission']),
                array('data' => &$form['tax_commision']),
                array('data' => &$form['formula_to']),
            ),
        );
        unset($form['turnover']);
        unset($form['commission']);
        unset($form['formula_to']);
        unset($form['tax_commision']);


        //Table 3 custom allowances
        $form['hr']['ca'] = [
            '#title' => t('Custom allowances'),
            '#type' => 'fieldset',
        ];
        $form['hr']['ca']['table3'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#input' => true,
            '#theme' => 'table',
            '#title' => t('Custom allowances'),
            '#header' => array(
                'allowance' => array(
                    'data' => $this->t('Allowance'),
                    'id' => ['tour-item5'],
                ),
                'tax' => array(
                    'data' => '',
                    'id' => ['tour-item5'],
                ),
                'empty' => array(
                ),
                'formula' => array(
                    'data' => '',
                    'id' => ['tour-item5'],
                ),
            ),
            '#rows' => array(),
            '#attributes' => array('id' => 'table3'),
            '#empty' => '',
        );
        for ($i = 1; $i <= 13; $i++) {
            $custom_aw = 'custom_aw' . $i;
            $tax = 'tax' . $i;
            $formula = 'formula' . $i;
            if (!null == $form_state->getValue('eid')) {
                $attr = ['title' => t('include tax'), 'class' => ['calculate']];
                if ($ad["LAC$i-" . $c]['tax'] == 1) {
                    $attr = ['title' => t('include tax'), 'class' => ['calculate'], 'checked' => 'checked'];
                }
            }
            
            $form[$custom_aw] = array(
                '#type' => 'textfield',
                '#id' => $custom_aw,
                '#title' => (!null == $form_state->getValue('eid')) ? $ad["LAC$i-" . $c]['description'] : t('Allowance'),
                '#title_display' => 'after',
                '#required' => false,
                '#size' => 20,
                '#attributes' => array('class' => array('calculate'), 'value' => isset($post->$custom_aw) ? $post->$custom_aw : 0),
                '#suffix' => "<div id='erroraw$i' class='back_red'></div>",
            );

            $form[$tax] = array(
                '#type' => 'checkbox',
                '#id' => $tax,
                '#attributes' => $attr,
            );

            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LAC$i-" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
            $form[$formula] = array(
                '#type' => 'item',
                '#markup' => $mark,
            );

            $form['hr']['ca']['table3'][$i] = array(
                'allowance' => &$form[$custom_aw],
                'tax' => &$form[$tax],
                'formula' => &$form[$formula],
            );

            $form['hr']['ca']['table3']['#rows'][$i] = array(
                'data' => array(
                    array('data' => &$form[$custom_aw]),
                    array('data' => &$form[$tax]),
                    array('data' => []),
                    array('data' => &$form[$formula]),
                ),
                    //'id' => array('table1row1'),
                    //'class' => $rowClass,
            );
            unset($form[$custom_aw]);
            unset($form[$formula]);
            unset($form[$tax]);
        }
        $i++;
        $form["sub_total_gross"] = array(
            '#type' => 'item',
            '#markup' => t('Total gross salary'),
        );
        $form["total_gross"] = array(
            '#type' => 'textfield',
            '#id' => 'total_gross',
            '#title' => '',
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->gross) ? $post->gross : 0),
        );

        $form['hr']['ca']['table3'][$i] = array(
            'item' => &$form['sub_total_gross'],
            'total_gross' => &$form["total_gross"],
        );

        $form['hr']['ca']['table3']['#rows'][$i] = array(
            'data' => array(
                array('data' => &$form['sub_total_gross']),
                array('data' => &$form["total_gross"]),
                array('data' => []), array('data' => []),
            ),
        );
        unset($form['sub_total_gross']);
        unset($form["total_gross"]);

        $i++;
        $form["less_hours"] = array(
            '#type' => 'number',
            '#id' => 'less_hours',
            '#title' => t("Number of less hours"),
            '#title_display' => 'after',
            '#required' => false,
            '#min' => 0,
            '#step' => 0.5,
            '#attributes' => array('title' => t('less hours number'), 'class' => ['calculate', 'form-number-small'], 'value' => isset($post->less_hours) ? $post->less_hours : 0),
        );
        $form["less_hours_val"] = array(
            '#type' => 'textfield',
            '#id' => 'less_hours_val',
            '#title' => '',
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->less_hours_val) ? $post->less_hours_val : 0),
        );

        $form['hr']['ca']['table3'][$i] = array(
            'less_hours' => &$form['less_hours'],
            'less_hours_val' => &$form["less_hours_val"],
        );

        $form['hr']['ca']['table3']['#rows'][$i] = array(
            'data' => array(
                array('data' => &$form['less_hours']),
                array('data' => &$form["less_hours_val"]),
                array('data' => []), array('data' => []),
            ),
        );
        unset($form['less_hours']);
        unset($form["less_hours_val"]);

        $i++;
        $form["sub_advance"] = array(
            '#type' => 'item',
            '#markup' => t('Advance'),
        );
        $form["advance"] = array(
            '#type' => 'textfield',
            '#id' => 'advance',
            '#title' => '',
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->advance) ? $post->advance : 0),
        );

        $form['hr']['ca']['table3'][$i] = array(
            'item' => &$form['sub_advance'],
            'advance' => &$form["advance"],
        );

        $form['hr']['ca']['table3']['#rows'][$i] = array(
            'data' => array(
                array('data' => &$form['sub_advance']),
                array('data' => &$form["advance"]),
                array('data' => []), array('data' => []),
            ),
        );
        unset($form['sub_advance']);
        unset($form["advance"]);

        //Table 4 custom deductions
        $form['hr']['cd'] = [
            '#title' => t('Custom deductions'),
            '#type' => 'fieldset',
        ];
        $form['hr']['cd']['table4'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#input' => true,
            '#theme' => 'table',
            '#title' => t('Custom deductions'),
            '#header' => array(
                'allowance' => array(
                    'data' => $this->t('Deduction'),
                    'id' => ['tour-item6'],
                ),
                'tax' => array(
                    'data' => '',
                    'id' => ['tour-item8'],
                ),
                'empty' => array(),
                'formula' => array(
                    'data' => '',
                    'id' => ['tour-item7'],
                ),
            ),
            '#rows' => array(),
            '#attributes' => array('id' => 'table4'),
            '#empty' => '',
        );
        for ($i = 1; $i <= 7; $i++) {
            $n = $i + 13;
            $custom_d = 'custom_d' . $i;
            $tax = 'tax' . $n;
            $formula = 'formula' . $i;
            if (!null == $form_state->getValue('eid')) {
                $attr = ['title' => t('include tax'), 'class' => ['calculate']];
                if ($ad["LDC$i-" . $c]['tax'] == 1) {
                    $attr = ['title' => t('include tax'), 'class' => ['calculate'], 'checked' => 'checked'];
                }
            }

            $form[$custom_d] = array(
                '#type' => 'textfield',
                '#id' => $custom_d,
                '#title' => (!null == $form_state->getValue('eid')) ? $ad["LDC$i-" . $c]['description'] : t('Deduction'),
                '#title_display' => 'after',
                '#required' => false,
                '#size' => 20,
                '#default_value' => isset($post->$custom_d) ? $post->$custom_d : 0,
                '#attributes' => array('class' => ['calculate'], 'value' => isset($post->$custom_d) ? $post->$custom_d : 0),
                '#suffix' => "<div id='errord$i' class='back_red'></div>",
            );

            $form[$tax] = array(
                '#type' => 'checkbox',
                '#id' => $tax,
                '#attributes' => $attr,
            );

            $opt = 'formula|' . $form_state->getValue('coid') . '|' . "LDC$i-" . $c;
            $opt = serialize($opt);
            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
            $mark = "<a class='use-ajax' title = '" . t('formula') . "' " . "href='" . $link . "'><span class='formula'></span></a>";
            $form[$formula] = array(
                '#type' => 'item',
                '#markup' => $mark,
            );

            $form['hr']['cd']['table4'][$i] = array(
                'allowance' => &$form[$custom_d],
                'tax' => &$form[$tax],
                'formula' => &$form[$formula],
            );

            $form['hr']['cd']['table4']['#rows'][$i] = array(
                'data' => array(
                    array('data' => &$form[$custom_d]),
                    array('data' => &$form[$tax]),
                    array('data' => []),
                    array('data' => &$form[$formula]),
                ),
            );
            unset($form[$custom_d]);
            unset($form[$formula]);
            unset($form[$tax]);
        }

        $i++;
        $form["sub_total_deductions"] = array(
            '#type' => 'item',
            '#markup' => t('Total deductions'),
        );
        $form["total_deductions"] = array(
            '#type' => 'textfield',
            '#id' => 'total_deductions',
            '#title' => '',
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => ['amount'], 'value' => isset($post->deduction) ? $post->deduction : 0),
        );

        $form['hr']['cd']['table4'][$i] = array(
            'item' => &$form['sub_total_deductions'],
            'total_deductions' => &$form["total_deductions"],
        );

        $form['hr']['cd']['table4']['#rows'][$i] = array(
            'data' => array(
                array('data' => &$form['sub_total_deductions']),
                array('data' => &$form["total_deductions"]),
                array('data' => []), array('data' => []),
            ),
        );
        unset($form['sub_total_deductions']);
        unset($form["total_deductions"]);

        //table 5 contributions
        $form['hr']['co'] = [
            '#title' => t('Contributions'),
            '#type' => 'fieldset',
        ];
        $form['hr']['co']['table5'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#input' => true,
            '#theme' => 'table',
            '#header' => array(
                'employer' => array(
                    'data' => $this->t('Employer'),
                    'id' => ['tour-item1'],
                ),
                'employee' => array(
                    'data' => $this->t('Employee'),
                    'id' => ['tour-item2'],
                ),
                'tax' => array(
                    'data' => '',
                    'id' => ['tour-item4'],
                ),
                'empty' => array(),
            ),
            '#rows' => array(),
            '#attributes' => array('id' => 'table5'),
            '#empty' => '',
        );

        $form["fund1_employer"] = array(
            '#type' => 'textfield',
            '#id' => 'fund1_employer',
            '#title' => (!null == $form_state->getValue('eid')) ? $param->get('param', 'fund_1', ['name', 'value']) : t('Contribution'),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->epf_er) ? $post->epf_er : 0),
            '#suffix' => "<div id='errorfund1er' class='back_red'></div><div id='fund1_employer_alert' class='back_blue'></div>",
        );
        $form["fund1_employee"] = array(
            '#type' => 'textfield',
            '#id' => 'fund1_employee',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->epf_yee) ? $post->epf_yee : 0),
            '#suffix' => "<div id='errorfund1ee' class='back_red'></div><div id='fund1_employee_alert' class='back_blue'></div>",
        );
        $form["thisepf"] = array(
            '#type' => 'checkbox',
            '#id' => 'thisepf',
            '#value' => 0,
            '#attributes' => array('title' => t('include this fund'), 'class' => ['amount', 'calculate']),
        );

        //built edit rows for table
        $form['hr']['co']['table5']['fund1'] = array(
            'employer' => &$form['fund1_employer'],
            'employee' => &$form['fund1_employee'],
            'select' => &$form['thisepf'],
        );

        $form['hr']['co']['table5']['#rows'] = array(
            'data' => array(
                array('data' => &$form['fund1_employer']),
                array('data' => &$form['fund1_employee']),
                array('data' => &$form['thisepf']),
                array('data' => []),
            ),
        );
        unset($form['fund1_employer']);
        unset($form['fund1_employee']);
        unset($form['thisepf']);

        $form["fund2_employer"] = array(
            '#type' => 'textfield',
            '#id' => 'fund2_employer',
            '#title' => (!null == $form_state->getValue('eid')) ? $param->get('param', 'fund_2', ['name', 'value']) : t('Contribution'),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->socso_er) ? $post->socso_er : 0),
            '#suffix' => "<div id='errorfund2er' class='back_red'></div><div id='fund2_employer_alert' class='back_blue'></div>",
        );
        $form["fund2_employee"] = array(
            '#type' => 'textfield',
            '#id' => 'fund2_employee',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->socso_yee) ? $post->socso_yee : 0),
            '#suffix' => '<div id="fund2_employer_alert" class="cell cellsmall back_blue"/></div>',
            '#suffix' => "<div id='errorfund2ee' class='back_red'></div><div id='fund2_employee_alert' class='back_blue'></div>",
        );
        $form["thissoc"] = array(
            '#type' => 'checkbox',
            '#id' => 'thissoc',
            '#value' => 0,
            '#attributes' => array('title' => t('include this fund'), 'class' => ['amount', 'calculate']),
        );

        //built edit rows for table
        $form['hr']['co']['table5']['fund2'] = array(
            'employer' => &$form['fund2_employer'],
            'employee' => &$form['fund2_employee'],
            'select' => &$form['thissoc'],
        );

        $form['hr']['co']['table5']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['fund2_employer']),
                array('data' => &$form['fund2_employee']),
                array('data' => &$form['thissoc']),
                array('data' => []),
            ),
        );
        unset($form['fund2_employer']);
        unset($form['fund2_employee']);
        unset($form['thissoc']);

        $form["fund3_employer"] = array(
            '#type' => 'textfield',
            '#id' => 'fund3_employer',
            '#title' => (!null == $form_state->getValue('eid')) ? $param->get('param', 'fund_3', ['name', 'value']) : t('Contribution'),
            '#title_display' => 'after',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->with_yer) ? $post->with_yer : 0),
            '#suffix' => "<div id='errorfund3er' class='back_red'></div><div id='fund3_employer_alert' class='back_blue'></div>",
        );
        $form["fund3_employee"] = array(
            '#type' => 'textfield',
            '#id' => 'fund3_employee',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->with_yee) ? $post->with_yee : 0),
            '#suffix' => '<div id="fund3_employer_alert" class="cell cellsmall back_blue"/></div>',
            '#suffix' => "<div id='errorfund3ee' class='back_red'></div><div id='fund3_employee_alert' class='back_blue'></div>",
        );
        $form["thiswith"] = array(
            '#type' => 'checkbox',
            '#id' => 'thiswith',
            '#value' => 0,
            '#attributes' => array('title' => t('include this fund'), 'class' => ['amount', 'calculate']),
        );

        //built edit rows for table
        $form['hr']['co']['table5']['fund3'] = array(
            'employer' => &$form['fund3_employer'],
            'employee' => &$form['fund3_employee'],
            'select' => &$form['thissoc'],
        );

        $form['hr']['co']['table5']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['fund3_employer']),
                array('data' => &$form['fund3_employee']),
                array('data' => &$form['thiswith']),
                array('data' => []),
            ),
        );
        unset($form['fund3_employer']);
        unset($form['fund3_employee']);
        unset($form['thiswith']);

        $form["tax_employee"] = array(
            '#type' => 'item',
            '#markup' => (!null == $form_state->getValue('eid')) ? $param->get('param', 'tax', ['name', 'value']) : t('Personal income tax'),
        );
        $form["income_tax"] = array(
            '#type' => 'textfield',
            '#id' => 'income_tax',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('class' => ['calculate'], 'value' => isset($post->incometax) ? $post->incometax : 0),
            '#suffix' => "<div id='errortaxee' class='back_red'></div><div id='incometax_alert' class='back_blue'></div>",
        );
        $form["thisincometax"] = array(
            '#type' => 'checkbox',
            '#id' => 'thisincometax',
            '#value' => 0,
            '#attributes' => array('title' => t('include personal tax'), 'class' => ['amount', 'calculate']),
        );

        //built edit rows for table
        $form['hr']['co']['table5']['tax'] = array(
            'item' => &$form['tax_employee'],
            'income_tax' => &$form['income_tax'],
            'select' => &$form['thisincometax'],
        );

        $form['hr']['co']['table5']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['tax_employee']),
                array('data' => &$form['income_tax']),
                array('data' => &$form['thisincometax']),
                array('data' => []),
            ),
        );
        unset($form['tax_employee']);
        unset($form['income_tax']);
        unset($form['thisincometax']);

        //Table 6 total
        $form['hr']['table6'] = array(
            '#type' => 'item',
            '#tree' => true,
            '#input' => true,
            '#theme' => 'table',
            '#header' => '',
            '#rows' => array(),
            '#attributes' => array('id' => 'table6'),
            '#empty' => '',
        );

        $form["_total_net"] = array(
            '#type' => 'item',
            '#markup' => t('Total net salary'),
        );
        $form["total_net"] = array(
            '#type' => 'textfield',
            '#id' => 'total_net',
            '#required' => false,
            '#size' => 20,
            '#attributes' => array('readonly' => 'readonly', 'class' => array('amount'), 'value' => isset($post->nett) ? $post->nett : 0),
        );

        //built edit rows for table
        $form['hr']['table6']['total'] = array(
            'item' => &$form['_total_net'],
            'total_net' => &$form['total_net'],
        );

        $form['hr']['table6']['#rows'] = array(
            'data' => array(
                array('data' => &$form['_total_net']),
                array('data' => []),
                array('data' => &$form['total_net']),
                array('data' => []),
            ),
        );
        unset($form['_total_net']);
        unset($form['total_net']);

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );


        $form['actions']['clear'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Clear'),
            '#limit_validation_errors' => array(['eid'], ['coid']),
            '#attributes' => array('class' => array('clear')),
            '#submit' => array('::clearForm'),
        );

        $form['actions']['save'] = array(
            '#id' => 'savebutton',
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#attributes' => array('class' => array('save')),
        );

        $form['top'] = array(
            '#id' => 'top',
            '#type' => 'item',
            '#markup' => "<div class='top-ico'></div>",
        );

        $form['hr']['#attached']['drupalSettings']['ek_hr'] = $settings;
        $form['hr']['#attached']['library'][] = 'ek_hr/ek_hr.payroll';
        $form['hr']['#attached']['library'][] = 'ek_admin/ek_admin_css';
        $form['#id'] = 'payroll_data';


        return $form;
    }

    /**
     * callback functions
     */
    public function get_employees(array &$form, FormStateInterface $form_state)
    {
        return $form['eid'];
    }

    public function get_data(array &$form, FormStateInterface $form_state)
    {
        return $form['hr'];
    }

    public function clearForm(array &$form, FormStateInterface $form_state)
    {
        $db = Database::getConnection('external_db', 'external_db')
                ->delete('ek_hr_workforce_pay')
                ->condition('id', $form_state->getValue('eid'))
                ->execute();
        \Drupal::messenger()->addStatus('Data cleared');
        $form_state->setRebuild();
        $form_state->setRedirect('ek_hr.payroll', ['coid' => $form_state->getValue('coid'), 'eid' => $form_state->getValue('eid')]);
    }

    public function build_settings($param, $ad, $c)
    {
        return array(
            'LAF1' => $ad['LAF1-' . $c]['value'], //$param->get('ad', 'LAF1' . $c, 'value'),
            'LAF1f' => $ad['LAF1-' . $c]['formula'], //$param->get('ad', 'LAF1' . $c, 'formula'),
            'LAF2' => $ad['LAF2-' . $c]['value'], //$param->get('ad', 'LAF2' . $c, 'value'),
            'LAF2f' => $ad['LAF2-' . $c]['formula'], //$param->get('ad', 'LAF2' . $c, 'formula'),
            'LAF3' => $ad['LAF3-' . $c]['value'], //$param->get('ad', 'LAF3' . $c, 'value'),
            'LAF3f' => $ad['LAF3-' . $c]['formula'], //$param->get('ad', 'LAF3' . $c, 'formula'),
            'LAF4' => $ad['LAF4-' . $c]['value'], //$param->get('ad', 'LAF4' . $c, 'value'),
            'LAF4f' => $ad['LAF4-' . $c]['formula'], //$param->get('ad', 'LAF4' . $c, 'formula'),
            'LAF5' => $ad['LAF5-' . $c]['value'], //$param->get('ad', 'LAF5' . $c, 'value'),
            'LAF5f' => $ad['LAF5-' . $c]['formula'], //$param->get('ad', 'LAF5' . $c, 'formula'),
            'LAF6' => $ad['LAF6-' . $c]['value'], //$param->get('ad', 'LAF6' . $c, 'value'),
            'LAF6f' => $ad['LAF6-' . $c]['formula'], //$param->get('ad', 'LAF6' . $c, 'formula'),
            'custom_a_val' => array(
                1 => $ad['LAC1-' . $c]['value'], //$param->get('ad', 'LAC1' . $c, 'value'),
                2 => $ad['LAC2-' . $c]['value'], //$param->get('ad', 'LAC2' . $c, 'value'),
                3 => $ad['LAC3-' . $c]['value'], //$param->get('ad', 'LAC3' . $c, 'value'),
                4 => $ad['LAC4-' . $c]['value'], //$param->get('ad', 'LAC4' . $c, 'value'),
                5 => $ad['LAC5-' . $c]['value'], //$param->get('ad', 'LAC5' . $c, 'value'),
                6 => $ad['LAC6-' . $c]['value'], //$param->get('ad', 'LAC6' . $c, 'value'),
                7 => $ad['LAC7-' . $c]['value'], //$param->get('ad', 'LAC7' . $c, 'value'),
                8 => $ad['LAC8-' . $c]['value'], //$param->get('ad', 'LAC8' . $c, 'value'),
                9 => $ad['LAC9-' . $c]['value'], //$param->get('ad', 'LAC9' . $c, 'value'),
                10 => $ad['LAC10-' . $c]['value'], //$param->get('ad', 'LAC10' . $c, 'value'),
                11 => $ad['LAC11-' . $c]['value'], //$param->get('ad', 'LAC11' . $c, 'value'),
                12 => $ad['LAC12-' . $c]['value'], //$param->get('ad', 'LAC12' . $c, 'value'),
                13 => $ad['LAC13-' . $c]['value'], //$param->get('ad', 'LAC13' . $c, 'value'),
            ),
            'custom_a_for' => array(
                1 => $ad['LAC1-' . $c]['formula'], //$param->get('ad', 'LAC1' . $c, 'formula'),
                2 => $ad['LAC2-' . $c]['formula'], //$param->get('ad', 'LAC2' . $c, 'formula'),
                3 => $ad['LAC3-' . $c]['formula'], //$param->get('ad', 'LAC3' . $c, 'formula'),
                4 => $ad['LAC4-' . $c]['formula'], //$param->get('ad', 'LAC4' . $c, 'formula'),
                5 => $ad['LAC5-' . $c]['formula'], //$param->get('ad', 'LAC5' . $c, 'formula'),
                6 => $ad['LAC6-' . $c]['formula'], //$param->get('ad', 'LAC6' . $c, 'formula'),
                7 => $ad['LAC7-' . $c]['formula'], //$param->get('ad', 'LAC7' . $c, 'formula'),
                8 => $ad['LAC8-' . $c]['formula'], //$param->get('ad', 'LAC8' . $c, 'formula'),
                9 => $ad['LAC9-' . $c]['formula'], //$param->get('ad', 'LAC9' . $c, 'formula'),
                10 => $ad['LAC10-' . $c]['formula'], //$param->get('ad', 'LAC10' . $c, 'formula'),
                11 => $ad['LAC11-' . $c]['formula'], //$param->get('ad', 'LAC11' . $c, 'formula'),
                12 => $ad['LAC12-' . $c]['formula'], //$param->get('ad', 'LAC12' . $c, 'formula'),
                13 => $ad['LAC13-' . $c]['formula'], //$param->get('ad', 'LAC13' . $c, 'value'),
            ),
            'custom_d_val' => array(
                1 => $ad['LDC1-' . $c]['value'], //$param->get('ad', 'LDC1' . $c, 'value'),
                2 => $ad['LDC2-' . $c]['value'], //$param->get('ad', 'LDC2' . $c, 'value'),
                3 => $ad['LDC3-' . $c]['value'], //$param->get('ad', 'LDC3' . $c, 'value'),
                4 => $ad['LDC4-' . $c]['value'], //$param->get('ad', 'LDC4' . $c, 'value'),
                5 => $ad['LDC5-' . $c]['value'], //$param->get('ad', 'LDC5' . $c, 'value'),
                6 => $ad['LDC6-' . $c]['value'], //$param->get('ad', 'LDC6' . $c, 'value'),
                7 => $ad['LDC7-' . $c]['value'], //$param->get('ad', 'LDC7' . $c, 'value'),
            ),
            'custom_d_for' => array(
                1 => $ad['LDC1-' . $c]['formula'], //$param->get('ad', 'LDC1' . $c, 'formula'),
                2 => $ad['LDC2-' . $c]['formula'], //$param->get('ad', 'LDC2' . $c, 'formula'),
                3 => $ad['LDC3-' . $c]['formula'], //$param->get('ad', 'LDC3' . $c, 'formula'),
                4 => $ad['LDC4-' . $c]['formula'], //$param->get('ad', 'LDC4' . $c, 'formula'),
                5 => $ad['LDC5-' . $c]['formula'], //$param->get('ad', 'LDC5' . $c, 'formula'),
                6 => $ad['LDC6-' . $c]['formula'], //$param->get('ad', 'LDC6' . $c, 'formula'),
                7 => $ad['LDC7-' . $c]['formula'], //$param->get('ad', 'LDC7' . $c, 'formula'),
            ),
            'LDF1' => $ad['LDF1-' . $c]['value'], //$param->get('ad', 'LDF1' . $c, 'value'),
            'LDF2' => $ad['LDF2-' . $c]['value'], //$param->get('ad', 'LDF2' . $c, 'value'),
            'LDF1_f' => $ad['LDF1-' . $c]['formula'], //$param->get('ad', 'LDF1' . $c, 'formula'),
            'LDF2_f' => $ad['LDF2-' . $c]['formula'], //$param->get('ad', 'LDF2' . $c, 'formula'),
            'fund1_calc' => $param->get('param', 'fund_1', ['calcul', 'value']),
            'fund1_pc_yer' => $param->get('param', 'fund_1', ['employer', 'value']),
            'fund1_pc_yee' => $param->get('param', 'fund_1', ['employee', 'value']),
            'fund1_base' => $param->get('param', 'fund_1', ['base', 'value']),
            'fund2_calc' => $param->get('param', 'fund_2', ['calcul', 'value']),
            'fund2_pc_yer' => $param->get('param', 'fund_2', ['employer', 'value']),
            'fund2_pc_yee' => $param->get('param', 'fund_2', ['employee', 'value']),
            'fund2_base' => $param->get('param', 'fund_2', ['base', 'value']),
            'fund3_calc' => $param->get('param', 'fund_3', ['calcul', 'value']),
            'fund3_pc_yer' => $param->get('param', 'fund_3', ['employer', 'value']),
            'fund3_pc_yee' => $param->get('param', 'fund_3', ['employee', 'value']),
            'fund3_base' => $param->get('param', 'fund_3', ['base', 'value']),
            'fund4_calc' => $param->get('param', 'fund_4', ['calcul', 'value']),
            'fund4_pc_yer' => $param->get('param', 'fund_4', ['employer', 'value']),
            'fund4_pc_yee' => $param->get('param', 'fund_4', ['employee', 'value']),
            'fund4_base' => $param->get('param', 'fund_4', ['base', 'value']),
            'fund5_calc' => $param->get('param', 'fund_5', ['calcul', 'value']),
            'fund5_pc_yer' => $param->get('param', 'fund_5', ['employer', 'value']),
            'fund5_pc_yee' => $param->get('param', 'fund_5', ['employee', 'value']),
            'fund5_base' => $param->get('param', 'fund_5', ['base', 'value']),
            'tax_calc' => $param->get('param', 'tax', ['calcul', 'value']),
            'tax_base' => $param->get('param', 'tax', ['base', 'value']),
            'tax_pc' => $param->get('param', 'tax', ['employee', 'value']),
            'tax_pcr' => $param->get('param', 'tax', ['employer', 'value']),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $tb = $form_state->getValue('table1');
        
        $tb = $form_state->getValue('table2');
        if (!is_numeric($form_state->getValue('table2')['to']['unit'])) {
            $form_state->setErrorByName($form_state->setErrorByName("table2][to][unit", $this->t('Wrong input')));
        }
        if (!is_numeric($form_state->getValue('table2')['to']['value'])) {
            $form_state->setErrorByName($form_state->setErrorByName("table2][5][value", $this->t('Wrong input')));
        }

        $tb = $form_state->getValue('table3');
        foreach ($tb as $key => $arr) {
            if ($key < 15) {
                if (!is_numeric($arr['allowance'])) {
                    $form_state->setErrorByName($form_state->setErrorByName("table3][$key][allowance", $this->t('Wrong input')));
                }
            }
        }

        $tb = $form_state->getValue('table4');
        foreach ($tb as $key => $arr) {
            if ($key < 8) {
                if (!is_numeric($arr['allowance'])) {
                    $form_state->setErrorByName($form_state->setErrorByName("table4][$key][allowance", $this->t('Wrong input')));
                }
            }
        }

        if (!is_numeric($form_state->getValue('table6')['total']['total_net'])) {
            $form_state->setErrorByName($form_state->setErrorByName("table6][total][total_net", $this->t('Wrong total')));
        }
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $triggering_element = $form_state->getTriggeringElement();
        if ($triggering_element['#id'] == 'savebutton') {
            $tb1 = $form_state->getValue('table1');
            $tb2 = $form_state->getValue('table2');
            $tb3 = $form_state->getValue('table3');
            $tb4 = $form_state->getValue('table4');
            $tb5 = $form_state->getValue('table5');
            $tb6 = $form_state->getValue('table6');
            $fields = array(
                'month' => $form_state->getValue('payroll_month'),
                'd_pay' => $tb1[0]['work_base'],
                'n_days' => $tb1[0]['unit_work'],
                'basic' => floatval(preg_replace('/[^\d.]/', '', $tb1[0]['basic_value'])),
                'no_payday' => $tb1[0]['no_pay_day'],
                'n_ot_days' => $tb2['not']['unit'],
                'n_ot_val' => $tb2['not']['value'],
                'r_day' => $tb2['rdot']['unit'],
                'r_day_val' => $tb2['rdot']['value'],
                'ph_day' => $tb2['phot']['unit'],
                'ph_day_val' => $tb2['phot']['value'],
                'mc_day' => $tb2['mc']['unit'],
                'mc_day_val' => $tb2['mc']['value'],
                'xr_hours' => $tb2['xh']['unit'],
                'xr_hours_val' => $tb2['xh']['value'],
                'tleave' => $tb1[0]['leave'],
                'custom_aw1' => $tb3[1]['allowance'],
                'custom_aw2' => $tb3[2]['allowance'],
                'custom_aw3' => $tb3[3]['allowance'],
                'custom_aw4' => $tb3[4]['allowance'],
                'custom_aw5' => $tb3[5]['allowance'],
                'custom_aw6' => $tb3[6]['allowance'],
                'custom_aw7' => $tb3[7]['allowance'],
                'custom_aw8' => $tb3[8]['allowance'],
                'custom_aw9' => $tb3[9]['allowance'],
                'custom_aw10' => $tb3[10]['allowance'],
                'custom_aw11' => $tb3[11]['allowance'],
                'custom_aw12' => $tb3[12]['allowance'],
                'custom_aw13' => $tb3[13]['allowance'],
                'turnover' => $tb2['to']['unit'],
                'commission' => $tb2['to']['value'],
                'gross' => $tb3[15]['total_gross'],
                'less_hours' => $tb3[16]['less_hours'],
                'less_hours_val' => $tb3[16]['less_hours_val'],
                'advance' => $tb3[17]['advance'],
                'custom_d1' => $tb4[1]['allowance'],
                'custom_d2' => $tb4[2]['allowance'],
                'custom_d3' => $tb4[3]['allowance'],
                'custom_d4' => $tb4[4]['allowance'],
                'custom_d5' => $tb4[5]['allowance'],
                'custom_d6' => $tb4[6]['allowance'],
                'custom_d7' => $tb4[7]['allowance'],
                'deduction' => $tb4[9]['total_deductions'],
                'epf_yee' => $tb5['fund1']['employee'],
                'socso_yee' => $tb5['fund2']['employee'],
                'epf_er' => $tb5['fund1']['employer'],
                'socso_er' => $tb5['fund2']['employer'],
                'with_yer' => $tb5['fund3']['employer'],
                'with_yee' => $tb5['fund3']['employee'],
                'incometax' => $tb5['tax']['income_tax'],
                'nett' => $tb6['total']['total_net'],
            );

            if ($form_state->getValue('post') == 1) {
                //update
                $db = Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce_pay')
                        ->fields($fields)
                        ->condition('id', $form_state->getValue('eid'))
                        ->execute();
            } else {
                //insert
                $fields['id'] = $form_state->getValue('eid');
                $db = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_workforce_pay')
                        ->fields($fields)
                        ->execute();
            }

            \Drupal::messenger()->addStatus('Data updated');
            $form_state->setRebuild();
            $form_state->setRedirect('ek_hr.payroll', ['coid' => $form_state->getValue('coid'), 'eid' => $form_state->getValue('eid')]);
        }
    }
}
