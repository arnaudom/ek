<?php

/**
 * @file
 * Contains \Drupal\ek_intelligence\Form\WriteReport.
 */

namespace Drupal\ek_intelligence\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_address_book\AddressBookData;
/**
 * Provides a form to write business report.
 */
class WriteReport extends FormBase {
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
    return 'ek_intelligence_report_new';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    
    $access = AccessCheck::GetCompanyByUser();
    $company = implode(',',$access);
    $u = \Drupal::currentUser()->id();
    if(\Drupal::currentUser()->hasPermission('generate_i_report')) {
        //user with permission and company access can edit
        //if owner is later remove from company access he/she cannot edit anymore
        $permission = 1;
        $query = "SELECT owner from {ek_ireports} WHERE id=:id "
                . "AND FIND_IN_SET (coid, :c )";
        $a = array(':id' => $id, ':c' => $company); 
    } else {
        //user assigned only can view the report
        $permission = 2;
        $query = "SELECT assign from {ek_ireports} WHERE id=:id "
                . "AND  FIND_IN_SET (coid, :c )";
        $a = array(':id' => $id, ':c' => $company);         
    }
    $edit = Database::getConnection('external_db', 'external_db')
            ->query($query,$a)
            ->fetchField();
    
    if($u == $edit) {
    
        $query = "SELECT * from {ek_ireports} WHERE id=:id ";
        $a = array(':id' => $id); 
        $data = Database::getConnection('external_db', 'external_db')
            ->query($query,$a)
            ->fetchObject();
        
        $form['perm'] = array(
          '#type' => 'hidden',
          '#value' => $permission,
        );          

        $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,
        );  
        
      if($permission == 1) {
          if($data->assign > 0) {
              $assign = User::load($data->assign)->getAccountName();
          } else {
              $assign = '';
          }
        $form['assign'] = array(
          '#type' => 'textfield',
          '#attributes' => array('placeholder' => t('user assignment')),
          '#required' => TRUE,
          '#default_value' => $assign,
          '#title' => t('Assignment'),
          '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
        );  


        $form['type'] = array(
          '#type' => 'select',
          '#options' => array(1 => t('briefing'), 2 => t('report'), 3 => t('training')),
          '#required' => TRUE,
          '#default_value' => $data->type,
          '#title' => t('Type'),
        ); 

        $form['status'] = array(
          '#type' => 'select',
          '#options' => array(1 => t('active'), 2 => t('close')),
          '#required' => TRUE,
          '#default_value' => $data->status,
          '#title' => t('Status'),
        );
        
        $form['description'] = array(
          '#type' => 'textfield',
          '#default_value' => $data->description,
          '#title' => t('Description'),      
        );

         $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => $data->coid,
            '#title' => t('Company'),
            '#required' => TRUE,
         );

        if($this->moduleHandler->moduleExists('ek_projects')) {

        $form['pcode'] = array(
          '#type' => 'select',
          '#size' => 1,
          '#options' => ProjectData::listprojects(0),
          '#default_value' => $data->pcode,
          '#title' => t('Project'),

          );

          } // project   
        if($this->moduleHandler->moduleExists('ek_address_book')) {

        $form['abid'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#default_value' => AddressBookData::getname($data->abid),
            '#title' => t('Client'),
            '#attributes' => array('placeholder'=>t('optional client ref.')),
            '#autocomplete_route_name' => 'ek.look_up_contact_ajax',
         );

        } // address book   
        
        $form['description'] = array(
          '#type' => 'textfield',
          '#default_value' => $data->description,
          '#title' => t('Description'),      
        );        
        
        $form['report'] = array(
          //'#type' => 'textarea',
          '#type' => 'text_format',
          '#default_value' => unserialize($data->report),
          '#format' => isset($data->format) ? $data->format : 'full_html',
        );         
        
        
        } else {
        
            $form['previous'] = array(
              '#type' => 'details',
              '#title' => $this->t('Previous edition'),
              '#open' => TRUE,
              '#attributes' => array('class' => array('container-inline')),
            ); 
            $form['previous']['text'] = array(
              '#type' => 'item',
              '#markup' => unserialize($data->report),    
            );        

            $form['report'] = array(
              //'#type' => 'textarea',
              '#type' => 'text_format',
              '#default_value' => '',
              '#format' => isset($data->format) ? $data->format : 'full_html',
              '#title' => t('New content'), 
            );         
        
        }  
   
                  
        $form['actions'] = array(
          '#type' => 'actions',
          '#attributes' => array('class' => array('container-inline')),
        );
        
        $form['actions']['submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Register report'),
        );

            
  } else {
     $form['alert'] = array(
      '#type' => 'item',
      '#markup' => t('You do not have enough privileges to edit this report'),
    ); 
  
  }
    
   
  return $form;
  
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

      if($form_state->getValue('perm') == 1) {
        
        $users = explode(',', $form_state->getValue('assign'));
        $error = '';
        $list = [];
        foreach ($users as $u) {
            if (trim($u) != NULL) {
              //check it is a registered user 
              $query = Database::getConnection()->select('users_field_data', 'u');
                $query->fields('u', ['uid']);
                $query->condition('name', $u);
                $id = $query->execute()->fetchField();
              //$query = "SELECT uid from {users_field_data} WHERE name=:u";
              //$id = db_query($query, array(':u' => $u))->FetchField();
                if (!$id ) {
                    $error.= $u . ' ';
                } else {
                    $list[] = $id;
                }
            }
        }  
         
        if($error <> '') {
            $form_state->setErrorByName("assign",  t('Invalid user(s)') . ': '. $error);
        } else {
            $form_state->setValue('assign', $list);
        }
      } 
  }
  
 /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  if($form_state->getValue('perm') == 1) {
  $description = Xss::filter( $form_state->getValue('description') ) ;
  
  $pcode = '';
  if($form_state->getValue('pcode') != '') {
      $pcode = $form_state->getValue('pcode');
  }
  if ($form_state->getValue('abid') != NULL) {
      $client = $form_state->getValue('abid');
      $abid = AddressBookData::getid($client);
  }  
  
  $report = $form_state->getValue('report') ;
         
    $fields = array(
        'assign' => implode(',', $form_state->getValue('assign')),
        'description' => $description,
        'status' => $form_state->getValue('status'),
        'edit' => date('U'),
        'pcode' => $pcode,
        'coid' => $form_state->getValue('coid'),
        'abid' => $abid,
        'type' => $form_state->getValue('type'),
        'format' => $report['format'],
        'report' => serialize($report['value'])
    );
  
    
  } else {
      $query = 'SELECT report FROM {ek_ireports} WHERE id=:id';
      $report = Database::getConnection('external_db', 'external_db')
            ->query($query,array(':id' => $form_state->getValue('for_id')))
            ->fetchField();
      $report_ = $form_state->getValue('report') ;
      $new = unserialize($report) . '<br><hr>' . date('l jS \of F Y h:i:s A') . '<br>' 
              .$report_['value'] ;
      
    $fields = array(
        'edit' => date('U'),
        'format' => $report_['format'],
        'report' => serialize($report_['value'])
    );          
  }
  
  
  
  
  
    $result = Database::getConnection('external_db','external_db')->update('ek_ireports')
            ->fields($fields)
            ->condition('id', $form_state->getValue('for_id'))
            ->execute();
  

    if($result) {
        \Drupal::messenger()->addStatus(t('Report updated'));
        $form_state->setRedirect('ek_intelligence.report' ) ;
    }
 }

  
}