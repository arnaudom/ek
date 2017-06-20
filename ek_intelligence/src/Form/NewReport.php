<?php

/**
 * @file
 * Contains \Drupal\ek_intelligence\Form\NewReport.
 */

namespace Drupal\ek_intelligence\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_address_book\AddressBookData;
use Drupal\ek_admin\Access\AccessCheck;
/**
 * Provides a form to create new business report.
 */
class NewReport extends FormBase {
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
  public function buildForm(array $form, FormStateInterface $form_state) {
  
    $form['assign'] = array(
      '#type' => 'textfield',
      '#attributes' => array('placeholder' => t('user assignment')),
      '#required' => TRUE,
      '#title' => t('Assignment'),
      '#autocomplete_route_name' => 'ek_admin.user_autocomplete',

    );  

    $form['type'] = array(
      '#type' => 'select',
      '#options' => array(1 => t('briefing'), 2 => t('report'), 3 => t('training')),
      '#required' => TRUE,
      '#title' => t('Type'),
    ); 
       
    $form['description'] = array(
      '#type' => 'textfield',
      '#default_value' => '' ,
      '#title' => t('Description'),      
    );

     $form['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => AccessCheck::CompanyListByUid(),
        '#title' => t('Company'),
        '#required' => TRUE,
     );

if($this->moduleHandler->moduleExists('ek_projects')) {

    $form['pcode'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => ProjectData::listprojects(0),
      '#title' => t('Project'),

      );

} // project    
if($this->moduleHandler->moduleExists('ek_address_book')) {

    $form['abid'] = array(
      '#type' => 'textfield',
      '#size' => 50,
      '#title' => t('Client'),
      '#attributes' => array('placeholder'=>t('optional client ref.')),
      '#autocomplete_route_name' => 'ek.look_up_contact_ajax',
      );

} // address book  

    $form['email'] = array(
      '#type' => 'checkbox',
      '#title' => t('Notify via email') ,
      
    );        
              
    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Register report'),
    );

        

    
   
  return $form;
  
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {


        $users = explode(',', $form_state->getValue('assign'));
        $error = '';
        foreach ($users as $u) {
        if (trim($u) != NULL) {
          //check it is a registered user 
          $query = "SELECT uid from {users_field_data} WHERE name=:u";
          $id = db_query($query, array(':u' => $u))->FetchField();
          if ($id == FALSE) $error.= $u . ' ';
          }
        }  
         
        if($error <> '') {
         $form_state->setErrorByName("assign",  t('Invalid user(s)') . ': '. $error);
         
        }
       
  }
  
 /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  
  $description = Xss::filter( $form_state->getValue('description') ) ;
  $users = explode(',', $form_state->getValue('assign'));
  $assign = '';
    foreach ($users as $u) {
        if (trim($u) != NULL) {
          
          $query = "SELECT uid from {users_field_data} WHERE name=:u";
          $id = db_query($query, array(':u' => $u))->FetchField();
          $assign .= $id . ' ';
        }
    }
  $pcode = '';
  if($form_state->getValue('pcode') != '') {
      $pcode = $form_state->getValue('pcode');
  }
  $query = "SELECT count(id) as id FROM {ek_ireports}";
  $count = Database::getConnection('external_db','external_db')->query($query)
          ->fetchField();
  $type = array(1 => t('BR'), 2 => t('RP'), 3 => t('TR'));
  $count++;
  $serial = $type[$form_state->getValue('type')] . '-' . $id . '-' . date('m') . '_' . date('y') . '-' . $count;
  if ($form_state->getValue('abid') != NULL) {
      $client = Xss::filter( $form_state->getValue('abid') );
      $abid = AddressBookData::getid($client);
  }        
          
    
    $fields = array(
        'serial' => $serial,
        'owner' => \Drupal::currentUser()->id(),
        'assign' => trim($assign),
        'edit' => date('U'),
        'description' => $description,
        'status' => 1,
        'pcode' => $pcode,
        'month' => date('m'),
        'year' => date('Y'),
        'coid' => $form_state->getValue('coid'),
        'abid' => $abid,
        'type' => $form_state->getValue('type'),
    );

    $result = Database::getConnection('external_db','external_db')->insert('ek_ireports')
            ->fields($fields)
            ->execute();
  

    if($result) {
         if($form_state->getValue('email') == 1) {
            $params = [
              'subject' => $subject,
              'body' => $body,
              'from' => $currentuserMail,
            ];
            foreach ($users as $key => $name) {
              if (trim($name) != NULL) {

                $query = "SELECT mail from {users_field_data} WHERE name=:n";
                $email = db_query($query, array(':n' => trim($name) ))->fetchField();
                if ($target_user = user_load_by_mail($email)) {
                        $target_langcode = $target_user->getPreferredLangcode();
                    } else {
                        $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                    }
                $send = \Drupal::service('plugin.manager.mail')->mail(
                  'ek_intelligence',
                  'notify_message',
                  trim($email),
                  $target_langcode,
                  $params,
                  \Drupal::currentUser()->getEmail(),
                  TRUE
                );

                if($send['result'] == FALSE) {
                  $error .= $email . ' ';
                }
              }
            }
        
            if($error != '') {
                drupal_set_message(t('Error sending email to @m'), array('@m' => $error));
            } else {
                drupal_set_message(t('Message sent'), 'status');
            }            
        }   

        $form_state->setRedirect('ek_intelligence.report' ) ;
    }
 }

  
}