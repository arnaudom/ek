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
use Drupal\Component\Utility\SafeMarkup;
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
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('module_handler')
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
    return $this->dialog(TRUE);
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
  protected function dialog($is_modal = FALSE) {
  
    $content = [];
    $content['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\SelectCalendar');
    $content['cal']['#markup'] = "<div id='calendar'></div>";
                        
    $response = new AjaxResponse();
    $title = t('Calendar');
    $l =  \Drupal::currentUser()->getPreferredLangcode();
    $content['#attached']['drupalSettings'] = array('calendarLang' => $l );
    $content['#attached']['library'] = array('core/drupal.dialog.ajax', 'ek_projects/ek_projects.calendar');
    $options = array('width' => '80%');
    
    if ($is_modal) {
      $dialog = new OpenModalDialogCommand($title, $content, $options);
      $response->addCommand($dialog);
    }
    else {
      $selector = '#ajax-text-dialog-wrapper-1';
      $response->addCommand(new OpenDialogCommand($selector, $title, $html));
    }
    return $response;
  }
 
  /**
   * AJAX callback handler for task and event display in calendar
   */
  public function view($id) {
      $color_array = array('#CEE3F6','#CEEFF6','#CEF6F0','#CEE3F6','#CEF6D4',
'#DAF6CE','#E9F6CE','#F6F5CE','#F6EBCE','#F6DECE','#CED5F6','#D4CEF6','#E1CEF6',
'#E2CEF6','#F6CEEF','#F6CEE5','#F6CED9','#F6CECE','#CEE3F6','#CEEFF6','#CEF6F0',
'#CEE3F6','#CEF6D4','#DAF6CE','#E9F6CE','#F6F5CE','#F6EBCE','#F6DECE','#CED5F6',
'#D4CEF6','#E1CEF6','#E2CEF6','#F6CEEF','#F6CEE5','#F6CED9','#F6CECE'
      );
      $events=array();
      $keys=array('id', 'title', 'description', 'start', 'end', 'url');
      
      switch($id) {
          
      case 1 :
     
      //1 My tasks
      //$query="SELECT * FROM {ek_project_tasks} WHERE uid=:o";
          $query = "SELECT p.id as pid ,t.id,event,task,uid,t.pcode,start,end,completion_rate FROM {ek_project_tasks} t "
            . "LEFT JOIN {ek_project} p "
            . "ON p.pcode=t.pcode WHERE t.uid=:id";
          
        $data = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => \Drupal::currentUser()->id()));
      
        $x=-1;
         while ($d = $data->fetchObject()) {
         $x++;  
            
                
            if (strlen($d->task > 25)) {
              $title = substr($d->task , 0, 25) . "...";} 
             else {
                 $title = $d->task;
             }

            $pcode = explode('-', $d->pcode);
            $pcode = array_reverse($pcode);
            $event = t('Project') . ' ' . $pcode[0] . ', ';
            $event .= $d->event;
            
            if($d->end == ''){
            $allday= TRUE;
            $d1=date('Y-m-d', $d->start);
            $d2=date('Y-m-d', $d->start);  
            } elseif ($d->end - $d->start < 86400) {
            $allday= FALSE;
            $d1=date('Y-m-d H:i:s', $d->start);
            $d2=date('Y-m-d H:i:s', $d->end);          
            } 
            else {
            $allday= TRUE;
            $d1=date('Y-m-d', $d->start);
            $d2=date('Y-m-d', $d->end);

            }
            
            $values = array(
                'id' => $d->id,
                'title' => $title,
                'description' => $event,
                'start' => $d1,
                'end' => $d2,
                'url' => "../projects/project/". $d->pid,
                'allDay' => $allday,
                'className' => "",
                'color' => $color_array[$x],  
                'textColor' => 'black',
                'backgroundColor' => '',
                'modal' => '',
            );
            array_push($events, $values);
            
        }
        
        break;//id=1
      
        case 2 :
            case 3 :
                case 4 :
                    case 5 :
 
        if($id == 2) {
         //select project submission
         $query = "SELECT p.id,p.pcode,pname,cid,submission as date from {ek_project} p "
                 . "LEFT JOIN {ek_project_description} d "
                 . "ON p.pcode=d.pcode "
                 . "WHERE submission <> '0000-00-00'";
        } 
                            
        if($id == 3) {
         //select project validation
         $query = "SELECT p.id,p.pcode,pname,cid,validation as date from {ek_project} p "
                 . "LEFT JOIN {ek_project_description} d "
                 . "ON p.pcode=d.pcode "
                 . "WHERE validation <> '0000-00-00'";
        } 
        
         if($id == 4) {
         //select project start dates
         $query = "SELECT p.id,p.pcode,pname,cid,start_date as date from {ek_project} p LEFT JOIN {ek_project_description} d "
                 . "ON p.pcode=d.pcode "
                 . "WHERE start_date <> '0000-00-00'";
        }   
        if($id == 5) {
         //select project deadline
         $query = "SELECT p.id,p.pcode,pname,cid,deadline as date from {ek_project} p LEFT JOIN {ek_project_description} d "
                 . "ON p.pcode=d.pcode "
                 . "WHERE deadline <> '0000-00-00'";
        }         
        if($id == 6) {
         //select project completion
         $query = "SELECT p.id,p.pcode,pname,cid,completion as date from {ek_project} p LEFT JOIN {ek_project_description} d "
                 . "ON p.pcode=d.pcode "
                 . "WHERE completion <> '0000-00-00'";
        }   
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query);
        
        
        //build array
         $x=-1;
         while ($d = $data->fetchObject()) {
             
             if(ProjectData::validate_access($d->id)) {
                $x++;  
                   
                     $pcode = explode('-', $d->pcode);
                     $pcode = array_reverse($pcode);
                     
                     if (strlen($d->pname > 15)) {
                       $title = $pcode[0]. ' - ' . substr($d->pname , 0, 15) . "...";} 
                      else {
                          $title = $pcode[0]. ' - ' . $d->pname ;
                      }
                   $country = Database::getConnection('external_db', 'external_db')
                          ->query("SELECT name FROM {ek_country} WHERE id=:cid", array(':cid' => $d->cid))
                          ->fetchField();
                   $d1=$d->date;
                   $d2=$d->date; 


                   $values = array(
                       'id' => $d->id,
                       'title' => $title,
                       'description' => $country,
                       'start' => $d1,
                       'end' => $d2,
                       'url' => "../projects/project/". $d->id,
                       'allDay' => TRUE,
                       'className' => "",
                       'color' => $color_array[$x],  
                       'textColor' => 'black',
                       'backgroundColor' => '',
                       'modal' => '',
                   );
                   array_push($events, $values);
             }//if access
        }
        
        break;
        
    }
   
      
      return new JsonResponse($events);   

      
   }

   
} //class