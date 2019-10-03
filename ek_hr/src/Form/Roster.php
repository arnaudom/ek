<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Roster.
 * 
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to display roster data
 */
class Roster extends FormBase {

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
        $this->roster = new \Drupal\ek_hr\RosterManager();
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
        return 'hr_roster';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }
        $access = AccessCheck::GetCompanyByUser();
        //$access = implode(',', $access);
        if (!isset($settings)) {
            $settings = '';
        }
        if (!isset($_SESSION['m'])) {
            $_SESSION['m'] = NULL;
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
            '#prefix' => "<div class='container-inline'>",
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
                '#suffix' => "</div>",
            );
        }

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);
            $monthnames = array('months', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_payroll_cycle', 'c');
            $query->fields('c', ['current']);
            $query->condition('coid', $form_state->getValue('coid'), '=');

            $month = $query->execute()->fetchField();
            $m = array_search($month, $monthnames);
            $options = [
                $month => $month,
                date('Y-m', strtotime("+1 month", strtotime($month))) => date('Y-m', strtotime("+1 month", strtotime($month))),
                date('Y-m', strtotime("+2 month", strtotime($month))) => date('Y-m', strtotime("+2 month", strtotime($month))),
            ];


            $form['month'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#default_value' => ($form_state->getValue('month')) ? $form_state->getValue('month') : $_SESSION['m'],
                '#title' => t('Month'),
                '#required' => TRUE,
            );
            
            $form['cutoff'] = array(
                '#type' => 'number',
                '#min' => 1,
                '#max' => 31,
                '#step' => 1,
                '#size' => 2,
                '#default_value' => ($form_state->getValue('cutoff')) ? $form_state->getValue('cutoff') : NULL,
                '#title' => t('Cut-off day'),
                '#required' => TRUE,
                '#prefix' => "<div class='container-inline'>",
            );


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w')
                    ->fields('w', ['location'])
                    ->distinct()
                    ->condition('company_id', $access, 'IN')
                    ->condition('company_id', $form_state->getValue('coid'))
                    ->condition('location', 0, '<>');
            $query->orderBy('location');


            $options = $query->execute()->fetchCol();
            $options['ANY'] = 'ANY';

            $form['location'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($options, $options),
                '#default_value' => ($form_state->getValue('location')) ? $form_state->getValue('location') : NULL,
                '#title' => t('Location'),
                '#required' => TRUE,
                '#suffix' => "</div></div>"
            );
            $param = NEW HrSettings($form_state->getValue('coid'));
            $check = $param->HrRoster[$form_state->getValue('coid')];
            if(empty($check)){
                //alert missing settings
                $link = Url::fromRoute('ek_hr.roster_settings', array(), array())->toString();
                $form['alert'] = array(
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>" . t('Missing settings') . " <a href='".$link."'>".t('Edit').".</div>",
                
                );
            }
            $form['cycle'] = array(
                '#type' => 'hidden',
                '#value' => $month,
            );

            $settings = ['cut' => $form_state->getValue('coid') . $month];
            $form['actions'] = array(
                '#type' => 'actions',
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Display roster'),
                '#suffix' => '',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='location']" => array('value' => ''),
                    ),
                ),
            );
        }//if step 2

        if ($form_state->get('step') == 4) {

            $input = $form_state->getValue('month');
            $c = $form_state->getValue('cutoff');
            $date = date_create($input . '-01');
            $t = date_format($date, 't');
            $Y = date_format($date, 'Y');
            $n = date_format($date, 'n');
            $settings = [];
            
            if ($c == $t) {
                //full month
                $Y0 = $Y;
                $month0 = $n;
                $month = $n;
                $s0 = 1;
                $c0 = 15;
                $s = 16;
                $c = $t;
            } else {
                //span on 2 months
                //retrieve previous
                $Y0 = $Y;
                $month0 = $n - 1;
                $month = $n;
                if ($month0 == 0) {
                    $month0 = 12;
                    $Y0 = $Y - 1;
                };
                $n0 = $Y0 . '-' . $month0 . '-1';
                $t0 = date_format(date_create($n0), 't');
                $s0 = $c;
                $c0 = $t0;
                $s = 1;
                $c = $c - 1;
                if ($c == 0) {
                    $c = 1;
                }
            }
            //Get day type
            $dayType = $this->roster->dayType($Y0 . '-' . $month0, $Y . '-' . $month, $s0, $s, $c0, $c, $form_state->getValue('coid'));

            // recap
            $form['roster']["info"] = array(
                '#type' => 'item',
                '#markup' => t('@l for month @m and cut-off date @c', array('@l' => $form_state->getValue('location'), '@m' => date('F', mktime(0, 0, 0, $month, 1, $Y)), '@c' => $c)),
                '#prefix' => '</div><h2 class="">',
                '#suffix' => '</h2>',
            );
            //location table buttons
            $param = serialize(
                    array(
                        'coid' => $form_state->getValue('coid'),
                        'cutoff' => $form_state->getValue('cutoff'),
                        'month' => $form_state->getValue('month'),
                        'location' => $form_state->getValue('location')
                    )
            );
            $link = Url::fromRoute('ek_hr.roster_extract', array('param' => $param), array())->toString();
            $excel = "<a href='" . $link . "' title='" . t('export') . "'><span id='excel' class='hand export-ico'/></a>";

            $buttons = "<div class='table'  id=''>
                      <div class='row'>
                        <div class='cell'>
                            <span id='calendar' class='hand calendar-ico' title='" . t('return to list view') . "'></span>
                        </div>
                        <div class='cell'>
                            " . $excel . "
                        </div>
                      </div>
                    </div>";

            $form['roster']["buttons"] = array(
                '#type' => 'item',
                '#markup' => $buttons,
            );

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w')
                    ->fields('w', ['location'])
                    ->distinct()
                    ->condition('company_id', $access, 'IN')
                    ->condition('company_id', $form_state->getValue('coid'));

            if ($form_state->getValue('location') == 'ANY') {
                $query->condition('location', '%', 'LIKE');
            } else {
                $query->condition('location', $form_state->getValue('location'), 'LIKE');
            }
            $query->orderBy('location');
            $locations = $query->execute()->fetchCol();


//build table header inner with days
            $headline_inner = '';

            for ($i = $s0; $i <= $c0; $i++) {

                $cal_description = '';
                $class = '';
                $w = '';
                if ($dayType[$i] == 'n') {
                    $class = 'week';
                    $w = $dayType[$i.'_'];
                } elseif ($dayType[$i] == 's') {
                    $class = 'sun';
                    $w = $dayType[$i.'_'];
                } else {
                    $class = 'ph';
                    $w = $dayType[$i.'_'];
                    $cal_description = ', ' . $dayType[$i];
                }

                $title = $w . " " . $cal_description . ' (' . t('click to edit roster') . ')';

                $headline_inner .= "
              <td id = 'd$i' class='cellborder $class time day hand'  title='" . $title . "'>" . $i . "</td>
              <td class='slide  d$i' title='" . $title . "'>" . $i . "</td>";
            }

            for ($i = $s; $i <= $c; $i++) {

                $cal_description = '';
                $class = '';
                $w = '';
                if ($dayType[$i] == 'n') {
                    $class = 'week';
                    $w = $dayType[$i.'_'];
                } elseif ($dayType[$i] == 's') {
                    $class = 'sun';
                    $w = $dayType[$i.'_'];
                } else {
                    $class = 'ph';
                    $w = $dayType[$i.'_'];
                    $cal_description = ', ' . $dayType[$i];
                }

                $title = $w . " " . $cal_description . ' (' . t('click to edit roster') . ')';
                $headline_inner .= "
              <td id = 'd$i' class='cellborder $class time day hand'  title='" . $title . "'>" . $i . "</td>
              <td class='slide  d$i' title='" . $title . "'>" . $i . "</td>";
            }

//build employee array
            $emp_array = array();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w')
                    ->fields('w', ['id', 'custom_id','name', 'origin', 'ic_no', 'location'])
                    ->condition('company_id', $form_state->getValue('coid'))
                    ->condition('active', 'working');
            $query->orderBy('name');
            $employees = $query->execute();


            $cnt = 0;
            while ($e = $employees->fetchObject()) {
                $cnt++;
                $emp_array[$e->id] = [
                    'custom_id' => $e->custom_id,
                    'name' => $e->name, 
                    'origin' => $e->origin, 
                    'ic_no' => $e->ic_no, 
                    'location' => $e->location];
            }

                        
//build roster data array in 1 single query
            $roster_array = array();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce_roster', 'r')
                    ->fields('r');
            $query->leftJoin('ek_hr_workforce', 'w', 'r.emp_id = w.id');
            $condition = $query->orConditionGroup()
                    ->condition('period', $month0 . '-' . $Y . '%', 'LIKE')
                    ->condition('period', $month0 . '-' . $Y0 . '%', 'LIKE')
                    ->condition('period', $month . '-' . $Y . '%', 'LIKE');
            $query->condition($condition);
            $query->condition('company_id', $form_state->getValue('coid'));
            $query->orderBy('id');
            $periods = $query->execute();
            $flag_rec = 0;
            
            while ($p = $periods->fetchObject()) {
                $roster_array[$p->period][$p->emp_id] = ['roster' => $p->roster, 'status' => $p->status, 'note' => $p->note];
            }

//loop the locations
            $tr = 0;
            foreach ($locations as $key => $location) {

                //roster data header
                $tr++;
                $headline = "<table><thead><tr><td colspan='2' >" . $location . "</td>";
                $headline .= $headline_inner;
                $headline .= "</tr></thead>";

                $form['roster']["headline" . $tr] = array(
                    '#type' => 'item',
                    '#markup' => $headline,
                );


                //display employees list

                $form['roster']["list" . $tr] = array(
                    '#type' => 'item',
                    '#markup' => '<tbody>',
                );

                //loop employees list per location and extract the roster values to display
                foreach ($emp_array as $key => $val) {

                    if ($val['location'] == $location) {
                        
                        $eid = ($val['custom_id'] != '') ? $val['custom_id']:$key;
                        $form['roster']["startrow" . $key] = array(
                            '#type' => 'item',
                            '#markup' => "<tr ><td class='handle'>
                                " . $eid . "</td><td width='10%'  class='tip' id='".$key."'><span>
                                " . $val['name'] . "</span></td>",
                        );



                        for ($i = $s0; $i <= $c0; $i++) {

                            $class = '';
                            if ($dayType[$i] == 'n') {
                                $class = 'week';
                            } elseif ($dayType[$i] == 's') {
                                $class = 'sun';
                            } else {
                                $class = 'ph';
                                $cal_description = ', ' . $dayType[$i];
                            }

                            $this_date = $month0 . "-" . $Y0 . "-" . $i;
                            $ref = $this_date . '_' . $key;
                            $note_class = 'info_no';
                            if (isset($roster_array[$this_date][$key])) {
                                $time = $roster_array[$this_date];
                                $roster_time = $time[$key]['roster'];
                                $status = $time[$key]['status'];
                                if(isset($roster_array[$this_date][$key]) 
                                        &&$roster_array[$this_date][$key]['note'] != ''){
                                    $note_class = 'info_on';
                                }
                            } else {
                                $roster_time = '';
                                $status = '';
                            }

                            if ($roster_time != '' && $roster_time != ',,,,,') {
                                $flag_rec = 1;
                                $time = explode(',', $roster_time);
                                $this_time = $this->roster->timed($roster_time);
                                if ($this_time == '00:00')
                                    $this_time = $status;
                            } else {
                                //$time = array($shift1,$shift1,$shift2,$shift2,$shift3,$shift3);
                                $time = array('0.00', '0.00', '8.00', '8.00', '16.00', '16.00');
                                $this_time = 0;
                            }

                            $settings += array(
                                "slide1-$ref" => array(1, 0, 8, 0.25, $time[0], $time[1]),
                                "slide2-$ref" => array(2, 8, 16, 0.25, $time[2], $time[3]),
                                "slide3-$ref" => array(3, 16, 24, 0.25, $time[4], $time[5]),
                            );

                            $opt = 'roster_note|'. $val['name'] . '_' . $ref;
                            $opt = serialize($opt);
                            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
                            $mark = "<a class='use-ajax' title = '" . t('note') . "' " . "href='" 
                                    . $link . "'><span class='$note_class'></span></a>";
                            
                        
                            $form['roster']["day" . $ref] = array(
                                '#type' => 'item',
                                '#markup' => "<td class='time $class d$i'>
                                    <span id='spread-$ref' class='status'>" . $this_time . "</span>
                                  </td>
                                  <td class='slide slidernote d$i' id='note_button-" . $ref . "'>" . $mark . "</td>
                                  <td class='slide sliderbox d$i' id='from1-" . $ref . "'>" . $time[0] . "</td>
                                  <td class='slide sliderui d$i' id='slide1-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to1-" . $ref . "'>" . $time[1] . "</td>
                                  <td class='slide sliderbox d$i' id='from2-" . $ref . "'>" . $time[2] . "</td>
                                  <td class='slide sliderui d$i' id='slide2-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to2-" . $ref . "'>" . $time[3] . "</td>
                                  <td class='slide sliderbox d$i' id='from3-" . $ref . "'>" . $time[4] . "</td>
                                  <td class='slide sliderui d$i' id='slide3-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to3-" . $ref . "'>" . $time[5] . "</td>",
                            );

                            $form['roster']["roster-" . $ref] = array(
                                '#type' => 'hidden',
                                '#default_value' => $roster_time,
                            );

                            //status absence
                            $form['roster']["status-" . $ref] = array(
                                '#type' => 'select',
                                '#id' => "status-" . $ref,
                                '#size' => 1,
                                '#options' => array('' => '', 'o' => 'off', 'm' => 'Mc', 'l' => 'La', 'a' => 'Ab'),
                                '#default_value' => $status,
                                '#prefix' => "<td class='slide status d$i'>",
                                '#suffix' => "</td>",
                            );
                            
                            
                        } //cut-off to 1st

                        for ($i = $s; $i <= $c; $i++) {

                            $class = '';
                            if ($dayType[$i] == 'n') {
                                $class = 'week';
                            } elseif ($dayType[$i] == 's') {
                                $class = 'sun';
                            } else {
                                $class = 'ph';
                                $cal_description = ', ' . $dayType[$i];
                            }

                            $this_date = $month . "-" . $Y . "-" . $i;
                            $ref = $this_date . '_' . $key;
                            $note_class = 'info_no';

                            if (isset($roster_array[$this_date][$key])) {
                                $time = $roster_array[$this_date];
                                $roster_time = $time[$key]['roster'];
                                $status = $time[$key]['status'];
                                if(isset($roster_array[$this_date][$key]) 
                                        && $roster_array[$this_date][$key]['note'] != ''){
                                    $note_class = 'info_on';
                                }
                            } else {
                                $roster_time = '';
                                $status = '';
                            }

                            if ($roster_time != '' && $roster_time != ',,,,,') {
                                $flag_rec = 1;
                                $time = explode(',', $roster_time);
                                $this_time = $this->roster->timed($roster_time);
                                if ($this_time == '00:00')
                                    $this_time = $status;
                            } else {
                                $time = array('0.00', '0.00', '8.00', '8.00', '16.00', '16.00');
                                $this_time = 0;
                            }

                            $settings += array(
                                "slide1-$ref" => array(1, 0, 8, 0.25, $time[0], $time[1]),
                                "slide2-$ref" => array(2, 8, 16, 0.25, $time[2], $time[3]),
                                "slide3-$ref" => array(3, 16, 24, 0.25, $time[4], $time[5]),
                            );
                            
                            $opt = 'roster_note|'. $val['name'] . '_' . $ref;
                            $opt = serialize($opt);
                            $link = Url::fromRoute('ek_hr_modal', ['param' => $opt])->toString();
                            $mark = "<a class='use-ajax' title = '" . t('note') . "' " . "href='" 
                                    . $link . "'><span class='$note_class'></span></a>";
                            
                            $form['roster']["day" . $ref] = array(
                                '#type' => 'item',
                                '#markup' => "<td class='time $class d$i'>
                                    <span id='spread-$ref' class='status'>" . $this_time . "</span>
                                  </td>
                                  <td class='slide slidernote d$i' id='note_button-" . $ref . "'>" . $mark . "</td>
                                  <td class='slide sliderbox d$i' id='from1-" . $ref . "'>" . $time[0] . "</td>
                                  <td class='slide sliderui d$i' id='slide1-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to1-" . $ref . "'>" . $time[1] . "</td>
                                  <td class='slide sliderbox d$i' id='from2-" . $ref . "'>" . $time[2] . "</td>
                                  <td class='slide sliderui d$i' id='slide2-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to2-" . $ref . "'>" . $time[3] . "</td>
                                  <td class='slide sliderbox d$i' id='from3-" . $ref . "'>" . $time[4] . "</td>
                                  <td class='slide sliderui d$i' id='slide3-" . $ref . "'></td>
                                  <td class='slide sliderbox d$i' id='to3-" . $ref . "'>" . $time[5] . "</td>",
                            );

                            $form['roster']["roster-" . $ref] = array(
                                '#type' => 'hidden',
                                '#defaul_value' => $roster_time,
                            );

                            //status absence
                            $form['roster']["status-" . $ref] = array(
                                '#type' => 'select',
                                '#id' => "status-" . $ref,
                                '#size' => 1,
                                '#options' => array('' => '', 'o' => 'off', 'm' => 'Mc', 'l' => 'La', 'a' => 'Ab'),
                                '#default_value' => $status,
                                '#prefix' => "<td  class='slide status d$i'>",
                                '#suffix' => "</td>",
                            );
                        } //cut-off 2nd

                        $form['roster']["endrow" . $t] = array(
                            '#type' => 'item',
                            '#markup' => "</tr>",
                        );
                    }//if in location
                }//browse employee

                $form['roster']["endtable" . $tr] = array(
                    '#type' => 'item',
                    '#markup' => "</tbody></table>",
                );
            } //browse locations


            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => ($flag_rec == 0) ? $this->t('Record roster') : $this->t('Update roster'),
                '#attributes' => ($flag_rec == 0) ? ['class' => array('button--record')] : ['class' => array('button--update')],
            );


            $form['cycle'] = array(
                '#type' => 'hidden',
                '#value' => $form_state->getValue('month'),
            );

            $form['actions']['clone'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Copy to @d', ['@d' => date('Y-m', strtotime("+1 month", strtotime($form_state->getValue('month'))))]),
                '#suffix' => ''
            );

            if ($form_state->getValue('cycle') == $input) {
                $form['daytype'] = array(
                    '#type' => 'hidden',
                    '#value' => $dayType,
                );
                               
                $form['actions']['post'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Record and push to @d payroll', ['@d' => $input]),
                    '#suffix' => ''
                );
            }
        }//if step 4




        $form['#tree'] = TRUE;
        $form['#attached']['drupalSettings']['roster'] = $settings;
        $form['#attached']['library'][] = 'ek_hr/ek_hr.roster';
        $form['#attached']['library'][] = 'ek_admin/ek_admin_css';

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
            if ($form_state->getValue('cutoff') > 31) {
                $form_state->setErrorByName('cutoff', $this->t('Wrong cut-off date'));
            } elseif (!is_numeric($form_state->getValue('cutoff'))) {
                $form_state->setErrorByName('cutoff', $this->t('Input 1 to 31'));
            }
            $form_state->set('step', 4);
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 4) {

            $data = $form_state->getValue('roster');
            $fields = [];
            $audit = \Drupal::currentUser()->getUsername() . "|" . date('U');
            $triggering_element = $form_state->getTriggeringElement();
            if ($triggering_element['#id'] == 'edit-actions-clone') {
                //replicate this month to next.
                $clone = 0;
                $nextMonth = date('Y-m', strtotime("+1 month", strtotime($form_state->getValue('cycle')))) . "-01";
                $t = date_format(date_create($nextMonth), 't');
                foreach ($data as $key => $value) {
                    if (strstr($key, 'roster-')) {
                        $string = str_replace('roster-', '', $key);
                        $id = explode('_', $string);
                        $parts = explode('-', $id[0]);
                        $period = $parts[1] . "-" . $parts[0] . "-" . $parts[2];

                        $nextDate = date('n-Y-j', strtotime("+1 month", strtotime($period)));
                        if ($parts[2] <= $t) {
                            $query = Database::getConnection('external_db', 'external_db')
                                    ->select('ek_hr_workforce_roster', 'r')
                                    ->fields('r', ['id'])
                                    ->condition('period', $nextDate)
                                    ->condition('emp_id', $id[1]);
                            if ($rec = $query->execute()->fetchField()) {
                                //can't replace
                            } else {
                                $fields = array(
                                    'period' => $nextDate,
                                    'emp_id' => $id[1],
                                    'roster' => $value,
                                    'status' => $data['status-' . $string],
                                    'audit' => $audit
                                );
                                $insert = Database::getConnection('external_db', 'external_db')
                                        ->insert('ek_hr_workforce_roster')
                                        ->fields($fields)
                                        ->execute();
                                $clone++;
                            }
                        }
                    }
                }
                
                if($clone == 0){
                    \Drupal::messenger()->addWarning(t('No record copied to @d; Data already exist for that period.', ['@d' => date('m-Y', strtotime("+1 month", strtotime($period)))]));
                 
                } else {
                    \Drupal::messenger()->addStatus(t('@r record(s) copied to roster for @d', ['@r' => $clone,'@d' => date('m-Y', strtotime("+1 month", strtotime($period)))]));
                }
            
                
            } else {
                
                if ($triggering_element['#id'] == 'edit-actions-post') {
                        //post total hours to payroll
                        //this will only update days in table for normal and public holidays
                        //will not calculate values.
                        $total = [];
                        $dayType = $form_state->getValue('daytype');
                        //Get settings
                        $param = NEW HrSettings($form_state->getValue('coid'));
                        $settings = $param->HrRoster[$form_state->getValue('coid')];
                        if(!isset($settings['hours_day'])) {
                            $hours_day = 60*60*8;
                        } else {
                            $hours_day = 60*60*$settings['hours_day'];
                        }
                }

                foreach ($data as $key => $value) {

                    if (strstr($key, 'roster-')) {

                        $string = str_replace('roster-', '', $key);
                        $id = explode('_', $string);

                        $fields = array(
                            'period' => $id[0],
                            'emp_id' => $id[1],
                            'roster' => $this->roster->filter_shift($value),
                            'status' => $data['status-' . $string],
                            'audit' => $audit
                        );
                        $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_hr_workforce_roster', 'r')
                                ->fields('r', ['id'])
                                ->condition('period', $id[0])
                                ->condition('emp_id', $id[1]);

                        if ($rec = $query->execute()->fetchField()) {
                            //update
                            $updtae = Database::getConnection('external_db', 'external_db')
                                    ->update('ek_hr_workforce_roster')
                                    ->fields($fields)
                                    ->condition('id', $rec)
                                    ->execute();
                        } else {
                            $insert = Database::getConnection('external_db', 'external_db')
                                    ->insert('ek_hr_workforce_roster')
                                    ->fields($fields)
                                    ->execute();
                        }
                   
                    
                        if ($triggering_element['#id'] == 'edit-actions-post') {
                            //collect total hours                         
                            $eid = $id[1];
                            $parts = explode('-', $id[0]);
                            if(!isset($total[$eid])){
                                $total[$eid]['normal'] = 0;
                                $total[$eid]['ph'] = 0;
                            }

                            //calculate total hours
                            if ($value != '') {
                                
                                    if($dayType[$parts[2]] == 's' || $dayType[$parts[2]] == 'n') {
                                        $total[$eid]['normal'] = $total[$eid]['normal'] + $this->roster->timed($value, TRUE) / $hours_day;
                                    } else {
                                        $total[$eid]['ph'] = $total[$eid]['ph'] + $this->roster->timed($value, TRUE) / $hours_day;
                                    }

                            }
                        }   
                    }
                }//for each
                
                if ($triggering_element['#id'] == 'edit-actions-post') {
                    //loop total to push data to payroll
                        foreach($total as $eid => $day){
                            if(!isset($day['normal'])) {
                                $day['normal'] = 0;
                            }
                            if(!isset($day['ph'])) {
                                $day['ph'] = 0;
                            }
                            $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_hr_workforce_pay', 'p')
                                ->fields('p', ['id'])
                                ->condition('id', $eid);
                            
                            if($rec = $query->execute()->fetchField()){
                                Database::getConnection('external_db', 'external_db')
                                        ->update('ek_hr_workforce_pay')
                                        ->fields(['month' => $form_state->getValue('cycle'),'n_days' => $day['normal'],'ph_day' => $day['ph']])
                                        ->condition('id', $eid)
                                        ->execute();
                            } else {
                                Database::getConnection('external_db', 'external_db')
                                        ->insert('ek_hr_workforce_pay')
                                        ->fields(['id' => $eid, 'month' => $form_state->getValue('cycle'),'n_days' => $day['normal'],'ph_day' => $day['ph']])
                                        ->execute();
                            }

                        }
                        \Drupal::messenger()->addStatus(t('Roster data pushed to payroll @c', ['@c' => $form_state->getValue('cycle')]));
                        
                }
                $_SESSION['m'] = $form_state->getValue('cycle');
                \Drupal::messenger()->addStatus(t('Roster recorded'));
            }
        }
    }

}
