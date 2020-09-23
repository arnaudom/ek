<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\
 */

namespace Drupal\ek_projects\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;

/**
 * Controller routines for ek module routes.
 */
class CalendarController extends ControllerBase {
    /* The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */

    protected $moduleHandler;

    /**
     * The database service.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * AJAX callback handler for Ajax Calendar Dialog
     */
    public function calendar() {
        
        $content = [];
        $content['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\SelectCalendar');
        $content['warning']['#markup'] = "<div id='calendar-warning' class='messages messages--warning'>"
                    . $this->t('Error fetching data') 
                    . "</div>";
        $content['cal']['#markup'] = "<div id='calendar'></div>";     
        $l = (null == \Drupal::currentUser()->getPreferredLangcode()) ? 'en' : \Drupal::currentUser()->getPreferredLangcode();
        $content['#attached']['drupalSettings'] = array('calendarLang' => $l);
        $content['#attached']['library'] = array('ek_projects/ek_projects.calendar');
        return $content;
        //return $this->dialog(true);
    }

    /**
     * Util to render dialog in ajax callback.
     *
     * @param bool $is_modal
     *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = false) {
        $content = [];
        $content['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\SelectCalendar');
        $content['cal']['#markup'] = "<div id='calendar'></div>";

        $response = new AjaxResponse();
        $title = $this->t('Calendar');
        $l = (null == \Drupal::currentUser()->getPreferredLangcode()) ? 'en' : \Drupal::currentUser()->getPreferredLangcode();
        
        $content['#attached']['drupalSettings'] = array('calendarLang' => $l);
        $content['#attached']['library'] = array('core/drupal.dialog.ajax', 'ek_projects/ek_projects.calendar');
        $options = array('width' => '80%');

        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content, $options);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-text-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $html));
        }
        return $response;
    }

    /**
     * AJAX callback handler for task and event display in calendar
     */
    public function view($id) {
        
        $events = array();
        $keys = array('id', 'title', 'description', 'start', 'end', 'url');

        switch ($id) {
            case 1:
                //1 My tasks             
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_tasks', 't');
                $query->fields('t',['id','pcode','event','task','start','end','uid','completion_rate','color']);
                $query->leftJoin('ek_project', 'p', 'p.pcode = t.pcode');
                $query->fields('p', ['id']);
                $query->condition('uid',\Drupal::currentUser()->id());
                $r = $query->execute();
                $x = -1;
                while ($d = $r->fetchObject()) {
                    $x++;
                    if (strlen($d->task > 25)) {
                        $title = substr($d->task, 0, 25) . "...";
                    } else {
                        $title = $d->task;
                    }

                    $pcode = explode('-', $d->pcode);
                    $pcode = array_reverse($pcode);
                    $event = $this->t('Project') . ' ' . $pcode[0] . ', ';
                    $event .= $d->event;

                    if ($d->end == '') {
                        $allday = true;
                        $d1 = date('Y-m-d', $d->start);
                        $d2 = date('Y-m-d', $d->start);
                    } elseif ($d->end - $d->start < 86400) {
                        $allday = false;
                        $d1 = date('Y-m-d H:i:s', $d->start);
                        $d2 = date('Y-m-d H:i:s', $d->end);
                    } else {
                        $allday = true;
                        $d1 = date('Y-m-d', $d->start);
                        $d2 = date('Y-m-d', $d->end);
                    }

                    $values = array(
                        'id' => $d->t_id,
                        'title' => $title,
                        'description' => $event,
                        'start' => $d1,
                        'end' => $d2,
                        'url' => "../projects/project/" . $d->p_id . "?s2=true#ps2",
                        'allDay' => $allday,
                        'className' => '',
                        'color' => '',
                        'textColor' => '',
                        'backgroundColor' => $d->color,
                        'modal' => '',
                    );
                    array_push($events, $values);
                }

                break; 
                
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
                if ($id == 2) {
                    //select project submission
                    $date = 'submission';
                }

                if ($id == 3) {
                    //select project validation
                    $date = 'validation';
                }

                if ($id == 4) {
                    //select project start dates
                    $date = 'start_date';
                }
                if ($id == 5) {
                    //select project deadline
                    $date = 'deadline';
                }
                if ($id == 6) {
                    //select project completion
                    $date = 'completion';
                }
              
                $no = "0000-00-00";
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_project', 'p');
                    $query->fields('p',['id','pcode','pname','cid']);
                    $query->leftJoin('ek_project_description', 'd', 'p.pcode = d.pcode');
                    $query->fields('d', [$date]);
                    //$query->condition($date,`$no`,'<>');
                    $r = $query->execute();
                
                $class_array = ['submission' => ['#034f84','#fff'],'validation' => ['#36486b','#fff'],
                    'start_date' => ['#405d27','#fff'],'deadline' => ['#d64161','#fff'],
                    'completion' => ['#3e4444','#fff'],];
                $countries = \Drupal\ek_admin\Access\AccessCheck::CountryList();
                while ($d = $r->fetchObject()) {
                    if (ProjectData::validate_access($d->id) && $d->$date != '0000-00-00') {
                        $pcode = explode('-', $d->pcode);
                        $pcode = array_reverse($pcode);
                        if (strlen($d->pname > 15)) {
                            $title = $pcode[0] . ' - ' . substr($d->pname, 0, 15) . "...";
                        } else {
                            $title = $pcode[0] . ' - ' . $d->pname;
                        }
                        $country = $countries[$d->cid]; 
                        $d1 = $d->$date;
                        $d2 = $d->$date;
                        $values = array(
                            'id' => $d->id,
                            'title' => $title,
                            'description' => $country ."<br/>" . $d->pcode. "<br/>" . $title,
                            'start' => $d1,
                            'end' => $d2,
                            'url' => "../projects/project/" . $d->id,
                            'allDay' => true,
                            'className' => '',
                            'color' => $class_array[$date][1],
                            'textColor' => $class_array[$date][1],
                            'backgroundColor' => $class_array[$date][0],
                            'modal' => '',
                        );
                        array_push($events, $values);
                    }//if access
                }

                break;
        }

        return new JsonResponse($events);
    }

}
