<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Roster.
 * 
 * if implemented in a configuration with remote drupal database connection
 * do not forget to set sufficient Query cache size in bytes (i.e. 16) 
 * and Maximum packet size (i.e 32MB) in mysql configuration 
 * as this script generates large data return
 * also may encounter Warning: Unknown: Input variables exceeded 1000. 
 * To increase the limit change max_input_vars in php.ini.
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

            $query = "SELECT current FROM {ek_hr_payroll_cycle} WHERE coid=:coid";
            $month = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':coid' => $form_state->getValue('coid')))
                    ->fetchField();
            $m = array_search($month, $monthnames);

            $form['cutoff'] = array(
                '#type' => 'textfield',
                '#size' => 3,
                '#maxlength' => 2,
                '#default_value' => ($form_state->getValue('cutoff')) ? $form_state->getValue('cutoff') : NULL,
                '#title' => t('Cut-off day'),
                '#required' => TRUE,
                '#prefix' => "<div class='container-inline'>",
            );
            $options = array($month => $month);
            $next = strtotime("+1 month", strtotime($month));
            $options[date('Y-m', $next)] = date('Y-m', $next);
            $next = strtotime("+2 month", strtotime($month));
            $options[date('Y-m', $next)] = date('Y-m', $next);

            $form['month'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#default_value' => ($form_state->getValue('month')) ? $form_state->getValue('month') : NULL,
                '#title' => t('Month'),
                '#required' => TRUE,
            );

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w')
                    ->fields('w', ['location'])
                    ->distinct()
                    ->condition('company_id', $access, 'IN')
                    ->condition('company_id', $form_state->getValue('coid'))
                    ->condition('location', 0, '<>');
            $query->orderBy('location');
            
            /*
            $query = "SELECT distinct location FROM {ek_hr_workforce}  "
                    . "WHERE FIND_IN_SET(company_id, :c) "
                    . "AND  company_id=:coid "
                    . "AND location <> :l "
                    . "order by location";
            $a = array(':c' => $access, ':coid' => $form_state->getValue('coid'), ':l' => 0);
             
             */
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
            /*
              $form['clone'] = array(
              '#type' => 'checkbox',
              '#title' => t('Clone data'),
              );
             */


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

            /*
              $roster = NEW HrSettings( $form_state->getValue('coid') );
              $shift = $roster->HrRoster[ $form_state->getValue('coid') ];
              //$shift1 = date_create('2000-01-01 ' . $shift['shift_start']);
              $shift1 = date('G.i', strtotime($shift['shift_start']));
              $shift2 = $shift1 + 8;
              if ($shift2 > 24) $shift2 = $shift2 - 24;
              $shift3 = $shift2 + 8;
              if ($shift3 > 24) $shift3 = $shift3 - 24;
              $shift4 = $shift3 + 8;
              if ($shift4 > 24) $shift4 = $shift4 - 24;

            */
            $settings = array();
            
            $input = $form_state->getValue('month');
            $c = $form_state->getValue('cutoff');
            $date = date_create($input . '-01');
            $t =  date_format($date, 't');
            $Y =  date_format($date, 'Y');
            $n =  date_format($date, 'n');

            if($c == $t) {
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
                $month0 = $n-1;
                $month = $n;
                if($month0 == 0) { 
                    $month0 = 12; 
                    $Y0 = $Y - 1;
                };
                $n0 = $Y0 .'-'. $month0 . '-1';
                $t0 = date_format(date_create($n0), 't');
                $s0 = $c;
                $c0 = $t0;
                $s = 1;
                $c = $c-1;
                if($c == 0) {
                    $c = 1;
                }
            }
            //Public Holidays
            $query = "SELECT * FROM {ek_hr_workforce_ph} WHERE date=:d AND coid=:coid";
            //store the public holidays and sundays dates to avoid db query
            $ph_array = array();
            $sun_array = array();

            for ($i = $s0; $i <= $c0; $i++) {
                
                $a = array(':d' => $Y0 . '-' . $month0 . '-' . $i, ':coid' => $form_state->getValue('coid'));
                $ph = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
                if ($ph) {
                    $day = date('j', strtotime($ph->date));
                    $ph_array[$day] = $ph->description;
                }
            }

            for ($i = $s; $i <= $c; $i++) {

                $a = array(':d' => $Y . '-' . $month . '-' . $i, ':coid' => $form_state->getValue('coid'));
                $ph = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
                if ($ph) {
                    $day = date('j', strtotime($ph->date));
                    $ph_array[$day] = $ph->description;
                }
            }

            // recap
            $form['roster']["info"] = array(
                '#type' => 'item',
                '#markup' => t('@l for month @m and cut-off date @c', 
                        array('@l' => $form_state->getValue('location'), '@m' => date('F', mktime(0, 0, 0, $month, 1, $Y)), '@c' => $c)),
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
                $w = jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $i, $Y0),1);
                if (isset($ph_array[$i]) && $ph_array[$i] != NULL) {
                    $class = 'ph';
                    $cal_description = ', ' . $ph_array[$i];
                } elseif ($w == 'Sunday') {
                    $class = 'sun';
                } else {
                    $class = 'week';
                    $cal_description = '';
                }
                $title = $w . " " . $cal_description . ' (' . t('click to edit roster') . ')';

                $headline_inner .= "
              <td id = 'd$i' class='cellborder $class time day hand'  title='" . $title . "'>" . $i . "</td>
              <td class='slide  d$i' title='" . $title . "'>" . $i . "</td>";
            }

            for ($i = $s; $i <= $c; $i++) {
                $w = jddayofweek(cal_to_jd(CAL_GREGORIAN, $month, $i, $Y),1);
                if (isset($ph_array[$i]) && $ph_array[$i] != NULL) {
                    $class = 'ph';
                    $cal_description = ', ' . $ph_array[$i];
                } elseif ($w == 'Sunday') {
                    $class = 'sun';
                } else {
                    $class = 'week';
                    $cal_description = '';
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
                ->fields('w',['id','name','origin','ic_no','location'])
                ->condition('company_id',$form_state->getValue('coid'))
                ->condition('active','working');
            $query->orderBy('name');
            $employees = $query->execute();    
            
            
            $cnt = 0;
            while ($e = $employees->fetchObject()) {
                $cnt++;
                $emp_array[$e->id] = ['name' => $e->name, 'origin' => $e->origin, 'ic_no' => $e->ic_no, 'location' => $e->location];
            }

            if ($cnt > 20) {
                $alert = "<div id='fx' class='messages messages--warning'>"
                        . t('You should consider breaking locations into smaller groups for better performance.')
                        . "</div>";
                $form['alert'] = array(
                    '#type' => 'item',
                    '#weight' => -10,
                    '#markup' => $alert,
                );
            }
//build roster data array in 1 single query
            $roster_array = array();
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r');
            $query->leftJoin('ek_hr_workforce', 'w', 'r.emp_id = w.id');
            $condition = $query->orConditionGroup()
                ->condition('period', $month0 . '-' . $Y . '%', 'LIKE')
                ->condition('period', $month0 . '-' . $Y0 . '%','LIKE')
                ->condition('period', $month . '-' . $Y . '%','LIKE');
            $query->condition($condition);
            $query->condition('company_id', $form_state->getValue('coid'));
            $query->orderBy('id');
            $periods = $query->execute();  
            

            while ($p = $periods->fetchObject()) {
                $roster_array[$p->period][$p->emp_id] = ['roster' => $p->roster, 'status' => $p->status];
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
                //
        
                $form['roster']["list" . $tr] = array(
                    '#type' => 'item',
                    '#markup' => '<tbody>',
                );

                //loop employees list per location and extract the roster values to display
                foreach ($emp_array as $key => $val) {

                    if ($val['location'] == $location) {

                        $form['roster']["startrow" . $key] = array(
                            '#type' => 'item',
                            '#markup' => "<tr ><td class='handle'>
                                " . $key . "</td><td width='10%' class=''>
                                " . $val['name'] . "</td>",
                                      );



                        for ($i = $s0; $i <= $c0; $i++) {

                            if (isset($ph_array[$i]) && $ph_array[$i] != NULL) {
                                $class = 'ph';
                            } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $i, $Y0), 1) == 'Sunday') {
                                $class = 'sun';
                            } else {
                                $class = 'week';
                            }

                            /*
                            if ($form_state->getValue('clone') == 1) {

                                $thismonth = $month - 1;
                                $thisyear = $year;
                                if ($thismonth == 0) {
                                    $thismonth = 12;
                                    $thisyear = $year - 1;
                                }
                                if ($thismonth == -1) {
                                    $thismonth = 11;
                                    $thisyear = $year - 1;
                                }
                            } else {

                                $thismonth = $month - 1;
                                $thisyear = $year;
                            }
                            if ($thismonth == 0) {
                                $thismonth = 12;
                                $thisyear = $year - 1;
                            }
                            */
                            $this_date = $month0 . "-" . $Y0 . "-" . $i;
                            $ref = $this_date . '_' . $key;

                            if (isset($roster_array[$this_date])) {
                                $time = $roster_array[$this_date];
                                $roster_time = $time[$key]['roster'];
                                $status = $time[$key]['status'];
                            } else {
                                $roster_time = '';
                                $status = '';
                            }

                            if ($roster_time != '' && $roster_time != ',,,,,') {
                                $time = explode(',', $roster_time);
                                $this_time = self::timed($time[0], $time[1], $time[2], $time[3], $time[4], $time[5]);
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

                            $form['roster']["day" . $ref] = array(
                                '#type' => 'item',
                                '#markup' => "<td class='time $class d$i'>
                                    <span id='spread-$ref' class='status'>" . $this_time . "</span>
                                  </td>
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
                                '#prefix' => "<td  class='slide status d$i'>",
                                '#suffix' => "</td>",
                            );
                        } //cut-off to 1st

                        for ($i = $s; $i <= $c; $i++) {

                            if (isset($ph_array[$i]) && $ph_array[$i] != NULL) {
                                $class = 'ph';
                            } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month, $i, $Y), 1) == 'Sunday') {
                                $class = 'sun';
                            } else {
                                $class = 'week';
                            }
                            /*
                            if ($form_state->getValue('clone') == 1) {

                                $thismonth = $month - 1;
                                $thisyear = $year;
                                if ($thismonth == 0) {
                                    $thismonth = 12;
                                    $thisyear = $year - 1;
                                }
                                if ($thismonth == -1) {
                                    $thismonth = 11;
                                    $thisyear = $year - 1;
                                }
                            } else {
                                $thismonth = $month;
                                $thisyear = $year;
                            }
                            */
                            $this_date = $month . "-" . $Y . "-" . $i;
                            $ref = $this_date . '_' . $key;

                            if (isset($roster_array[$this_date])) {
                                $time = $roster_array[$this_date];
                                $roster_time = $time[$key]['roster'];
                                $status = $time[$key]['status'];
                            } else {
                                $roster_time = '';
                                $status = '';
                            }

                            if ($roster_time != '' && $roster_time != ',,,,,') {
                                $time = explode(',', $roster_time);
                                $this_time = self::timed($time[0], $time[1], $time[2], $time[3], $time[4], $time[5]);
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

                            $form['roster']["day" . $ref] = array(
                                '#type' => 'item',
                                '#markup' => "<td class='time $class d$i'>
                                    <span id='spread-$ref' class='status'>" . $this_time . "</span>
                                  </td>
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
                '#value' => $this->t('Record roster'),
                '#suffix' => ''
            );
        }//if step 4




        $form['#tree'] = TRUE;
        $form['#attached']['drupalSettings']['roster'] = $settings;
        $form['#attached']['library'][] = 'ek_hr/ek_hr.roster';
        $form['#attached']['library'][] = 'ek_admin/ek_admin_css';

        return $form;
    }

    function timed($t0, $t1, $t2, $t3, $t4, $t5) {

        $t0 = explode(".", $t0);
        $t1 = explode(".", $t1);
        $t2 = explode(".", $t2);
        $t3 = explode(".", $t3);
        $t4 = explode(".", $t4);
        $t5 = explode(".", $t5);

        $ta = $t0[0] * 3600 + $t0[1] * 60;
        $tb = $t1[0] * 3600 + $t1[1] * 60;
        $tc = $t2[0] * 3600 + $t2[1] * 60;
        $td = $t3[0] * 3600 + $t3[1] * 60;
        $te = $t4[0] * 3600 + $t4[1] * 60;
        $tf = $t5[0] * 3600 + $t5[1] * 60;

        $total = ($tb - $ta) + ($td - $tc) + ($tf - $te);
        if ($total == 86400) {
            return "24:00";
        } else {
            return gmdate('H:i', $total);
        }
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
            $fields = array();
            foreach ($data as $key => $value) {

                if (strstr($key, 'roster-')) {

                    $string = str_replace('roster-', '', $key);
                    $id = explode('_', $string);

                    $fields = array(
                        'period' => $id[0],
                        'emp_id' => $id[1],
                        'roster' => $value,
                        'status' => $data['status-' . $string]
                    );
                    $delete = Database::getConnection('external_db', 'external_db')
                            ->delete('ek_hr_workforce_roster')
                            ->condition('period', $id[0])
                            ->condition('emp_id', $id[1])
                            ->execute();
                    $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_hr_workforce_roster')
                            ->fields($fields)
                            ->execute();
                }
            }
            
            \Drupal::messenger()->addStatus(t('Roster recorded'));
        }
    }

}
