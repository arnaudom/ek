<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\TaskProject.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to record and edit project tasks.
 */
class TaskProject extends FormBase {

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
    return 'ek_task_project';
  }


  /**
   * {@inheritdoc}
   * @param pid: project id
   * @param id: task id
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = NULL, $id = NULL) {
  
  
  $access = AccessCheck::GetCountryByUser(); 
  $perm = \Drupal::currentUser()->hasPermission('delete_project_task');
  
  
  if($id != NULL && $id > 0) {
      //edit a task mode
    $form['for_id'] = array(
      '#type' => 'hidden',
      '#value' => $id,
    );
  
  $query = Database::getConnection('external_db', 'external_db')
          ->select('ek_project_tasks', 't');
  $query->leftJoin('ek_project', 'p', 'p.pcode=t.pcode');    
  $or1 = $query->orConditionGroup();
        $or1->condition('cid', $access , 'IN');
        
        
  $data = $query
              ->fields('t')
              ->fields('p', array('id','pcode'))
              ->condition($or1)              
              ->condition('p.id', $pid , '=')
              ->condition('t.id', $id , '=')
              ->execute()
              ->fetchObject();
 
    if(  \Drupal::currentUser()->id() == $data->uid && $perm == FALSE) {
        //if the task is assigned to current user, limited edition available
        $read = array(
            'event' => 'readonly',
            'uid' => TRUE,
            'task' => 'readonly',
            'start' => 'readonly',
            'end' => 'readonly',
            'class' => 'date',
            'notify' => TRUE,
            'notify_who'=> TRUE,
        );
        
    } elseif(\Drupal::currentUser()->id() != $data->uid && $perm == FALSE) {
        $read = array(
            'event' => 'readonly',
            'uid' => TRUE,
            'task' => 'readonly',
            'start' => 'readonly',
            'end' => 'readonly',
            'class' => 'date',
            'notify' => TRUE,
            'notify_who'=> FALSE,
        );       
    } 
  
  } else {
     //new task

     $query = "SELECT pcode FROM {ek_project} WHERE id=:id";
     $data = Database::getConnection('external_db', 'external_db')
             ->query($query, array(':id' => $pid))
             ->fetchObject();
  }
  

   if($form_state->get('error') <> '' ) {
      $form['alert'] = array(
        '#markup' => "<div class='red' id='alert'>" . $form_state->get('error') . "</div>",
      ); 
      $form_state->set('error', '');
      $form_state->setRebuild();
    }      
      
 
if($data->pcode) {
  
  
  
    $form['edit_task_project'] = array(
      '#type' => 'item',
      '#markup' => t('Project ref. @p', array('@p' => $data->pcode)),

    );  


    $form['for_pcode'] = array(
      '#type' => 'hidden',
      '#value' => $data->pcode,
    );
    $form['for_pid'] = array(
      '#type' => 'hidden',
      '#value' => $pid,
    );

    
    if($id != NULL && \Drupal::currentUser()->id() == $data->uid) {
     
    $rate = array('0' => '0 %', '10' => '10 %','20' => '20 %','30' => '30 %','40' => '40 %',
        '50' => '50 %','60' => '60 %','70' => '70 %','80' => '80 %','900' => '90 %',
        '100' => t('completed'));       
        
        $form['completion_rate'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $rate,
            '#required' => TRUE,
            '#default_value' => isset($data->completion_rate) ? $data->completion_rate : 0,
            '#title' => $this->t('Completion rate'),
          );        
    }
    
    if($id && $perm){
      $form['delete'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Delete this task'),
       );       
    }
    
    $form['event'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Event name'),
      '#size' => 25,
      '#maxlength' => 100,
      '#default_value' => isset($data->event) ? $data->event : NULL,
      '#attributes' => isset($read['event']) ? array('readonly' => $read['event']): NULL,
    );   
    
    
    $form['uid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => \Drupal\ek_admin\Access\AccessCheck::listUsers(),
        '#required' => TRUE,
        '#default_value' => isset($data->uid) ? $data->uid : NULL,
        '#title' => $this->t('Assigned to'),
        '#disabled' => isset($read['uid']) ? $read['uid'] : FALSE,
      );
    
    $form['task'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Task description'),
      '#size' => 25,
      '#required' => TRUE,
      '#maxlength' => 150,
      '#default_value' => isset($data->task) ? $data->task : NULL,
      '#attributes' => isset($read['task']) ? array('readonly' => $read['task']): NULL,
    );
    
    
    $form['start'] = array(
      '#type' => 'date',
      '#size' => 12,
      '#required' => TRUE,
      '#default_value' => isset($data->start) ? date('Y-m-d', $data->start) : date('Y-m-d'),
      '#title' => $this->t('Starting'),
      '#prefix' => "<div class='container-inline'>",

    ); 
    
    
    $form['end'] = array(
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => isset($data->end) ? date('Y-m-d', $data->end) : NULL,
      '#title' => $this->t('ending'),
      '#suffix' => '</div>',
    );

    $form['color'] = array(
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => isset($data->color) ? $data->color : '#80ff80',
      );
    
    if(isset($data->notify_who) && $data->notify_who != NULL){
        $who = explode(',', $data->notify_who);
        $list = '';
    
        foreach ($who as $w) {
        if (trim($w) != NULL) {
          //$query = "SELECT name from {users_field_data} WHERE uid=:u";
          //$name = db_query($query, array(':u' => $w))->fetchField();
            $acc = \Drupal\user\Entity\User::load($w);
            if($acc) {
                $list .= $acc->getAccountName() . ',';
            }
          }
        }  
    } else {
        $list = '';
    }
 

    $form['notify_who'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Notification recipients'),
      '#rows' => 2,
      '#id' => 'edit-email',
      '#attributes' => array('placeholder' => t('enter users names separated by comma (autocomplete enabled).')),
      '#default_value' => $list,
      '#disabled' => isset($read['notify_who']) ? $read['notify_who'] : FALSE,
      '#attached' => array(
            'library' => array(
                'ek_projects/ek_projects_autocomplete',
            ),
        ),
      
    ); 
    
    $notify = array(
        '0' => t('Never'),
        '5' => t('Daily'),
        '1' => t('Weekly'),
        '6' => t('Monthly'),
        '2' => t('5 days before deadline'),
        '3' => t('3 days before dealine'),
        '4' => t('1 day before dealine'),

    );
    
    $form['notify'] = array(
      '#type' => 'select',
      '#title' => $this->t('Notification period'),
      '#options' => $notify,
      '#default_value' => isset($data->notify) ? $data->notify : NULL,
      '#disabled' => isset($read['notify']) ? $read['notify'] : FALSE,
    );
    
   if($form_state->get('message') != '' ) {
    $form['actions']['message'] = array(
      '#markup' => "<div class='red'>" . t('Data') . ": " . $form_state->get('message') . "</div>",
    ); 
    $form_state->set('message', '');
    
        if($form_state->get('error') == '0' ) {
            $form_state->set('error', '');    
            $response = new AjaxResponse();
            $response->addCommand(new CloseDialogCommand());
            return $response;
        } 
    }
    
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['record'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Record'),
        '#attributes' => array('class' => array('use-ajax-submit')),
      );     
    $form['actions']['cancel'] = array(
      '#type' => 'button',
      '#value' => $this->t('cancel'),
      '#ajax' => array(
        'callback' => array($this, 'cancel'), 
      ),
    );
  
    
} else {
      
      $form['info'] = array(
        '#type' => 'item',
        '#markup' => $this->t('You cannot edit this task.'),  
      );
      
      $form['cancel'] = array(
      '#type' => 'button',
      '#value' => t('cancel'),
      '#ajax' => array(
        'callback' => array($this, 'cancel'),
       ),
      );
  }  
    

  return $form;    
  }

  /**
   * Callback
   */
  public function cancel(){
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */  
  public function validateForm(array &$form, FormStateInterface $form_state) {
 
     $error = ''; 
     if($form_state->getValue('delete') != 1) {
        if($form_state->getValue('notify_who') != '' ) {

           $users = explode(',', $form_state->getValue('notify_who'));
           
           $notify_who = '';
           foreach ($users as $u) {
           if (trim($u) != '') {
                //check it is a registered user 
                
                $query = Database::getConnection()->select('users_field_data', 'u');
                $query->fields('u', ['uid']);
                $query->condition('name', trim($u));
                $id = $query->execute()->fetchField();
                if ($id == '') {
                    $error.= $u . ',';
                   } else {
                       $notify_who .= $id . ',';
                   }
                }
           }  

           if($error <> '') {
            //$error = t('invalid user(s)') . ': ' .rtrim($error, ',');
            $form_state->setErrorByName("notify_who",  t('Invalid user(s)') . ': '. $error);

           } else {
               $form_state->setValue('notify_who', $notify_who);
           }
         }
         $or = $form_state->getValue('notify') == 2 
                 || $form_state->getValue('notify') == 3 
                 || $form_state->getValue('notify') == 4;

         if($form_state->getValue('end') == '' && ( $or )){
            //$error .= '<br/>' . t('You need a deadline for the selected period.');  
            $form_state->setErrorByName("end",  t('You need a deadline for the selected period.') . ': '. $error);
         }
     }       
      
     if($error != '') {
         $form_state->set('error', 1 );
         $form_state->set('message', $error );
         $form_state->setRebuild();
         
        } 
  }

 
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

      
            if($form_state->getValue('delete') == 1) {
                   $update = Database::getConnection('external_db', 'external_db')
                       ->delete('ek_project_tasks')
                       ->condition('id', $form_state->getValue('for_id'))
                       ->execute(); 

             } else {

             if($form_state->getValue('notify_who') != '' ) {

               $notify_who = rtrim($form_state->getValue('notify_who') , ',');

             } else {
                 $notify_who = NULL;
             }     

             if($form_state->getValue('completion_rate') == '') {
                $completion = 0; 
             } else {
                $completion = $form_state->getValue('completion_rate');
             }


               $fields = array(
                 'pcode' => $form_state->getValue('for_pcode'),
                 'event' => Xss::filter($form_state->getValue('event')), 
                 'uid' => $form_state->getValue('uid'),
                 'completion_rate' => $completion,
                 'task' => Xss::filter($form_state->getValue('task')), 
                 'start' => strtotime($form_state->getValue('start')),
                 'end' => strtotime($form_state->getValue('end')),
                 'notify' => $form_state->getValue('notify'),
                 'notify_who' => $notify_who,
                 'color' => $form_state->getValue('color'),
               );

               if($form_state->getValue('for_id') != NULL) {

                $update = Database::getConnection('external_db', 'external_db')
                       ->update('ek_project_tasks')->fields($fields)
                       ->condition('id', $form_state->getValue('for_id'))
                       ->execute();           
               } else {

                $update = Database::getConnection('external_db', 'external_db')
                       ->insert('ek_project_tasks')->fields($fields)
                       ->execute();            
               }

                //$query = "SELECT name from {users_field_data} WHERE uid=:u";
                //$name = db_query($query, array(':u' => $form_state->getValue('uid') ))->fetchField();
                $acc = \Drupal\user\Entity\User::load($form_state->getValue('uid'));
                $name = '';
                if($acc) {
                    $name = $acc->getAccountName();
                }
                $param = serialize (
                array (
                  'id' => $form_state->getValue('for_pid'),
                  'field' => t('New task added for') . ": " . $name ,
                  'value' => Xss::filter($form_state->getValue('task'))
                )
               );
               ProjectData::notify_user($param);             
               
            }
             
             Cache::invalidateTags(['project_task_block']);
             $form_state->set('message', t('saved') );
             $form_state->set('error',0);
             $form_state->setRebuild();
        
  
     
   }


}