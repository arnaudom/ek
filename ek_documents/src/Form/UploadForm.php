<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Form\UploadForm
 */

namespace Drupal\ek_documents\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\ek_documents\Settings;


/**
 * Provides a form to upload document.
 */
class UploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_documents_upload';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $here = $this->getRouteMatch();
    if($here->getRouteName() == 'ek_documents_documents_common') {
        $form['common'] = array(
          '#type' => 'hidden',
          '#value' => 1,
          );
        
        $alert = "<div id='alert' class='messages messages--warning'>" 
                .t('File will be accessible in common area.'). "</div>";
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => $alert,
          );        
    }

    $form['upload_doc'] = array(
      '#type' => 'file',
      '#title' => t('Select file'),
      );

    $form['folder'] = array(
      '#type' => 'textfield',
      '#size' => 20,
      '#attributes' => array('placeholder' => t('tag or folder') ),
      '#autocomplete_route_name' => 'ek_look_up_folders',
    );
      
    
    $form['actions'] = array('#type' => 'actions');   
    $form['actions']['upload'] = array(
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' =>  t('Upload') ,
            //'#attributes' => array('class' => array('use-ajax-submit')),
            '#ajax' => array(
              'callback' => array($this, 'saveFile'), 
              'wrapper' => 'doc_upload_message',
              'method' => 'replace',
             ),
      );

    
    
    $form['doc_upload_message'] = array(
      '#type' => 'item',
      '#markup' => '',
      '#prefix' => '<div id="doc_upload_message" class="red" >',
      '#suffix' => '</div>',
      
      );

 
     return $form;  

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
  }

  /**
   * {@inheritdoc}
   */  
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
  

  /**
   * Save file callback
   */  
  public function saveFile(array &$form, FormStateInterface $form_state) {

      if(!null == ($form_state->getValue('common')) ) {
            $user = 0;
        } else {
            $user = \Drupal::currentUser()->id();
        }
      $settings = new Settings(); 
      //upload
      
      //$extensions = 'csv png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
      $extensions = $settings->get('file_extensions');
      $validators = array( 'file_validate_extensions' => array($extensions));
      $dir = "private://documents/users/" . $user ;
      file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
      $file = file_save_upload("upload_doc" , $validators, $dir , 0 , FILE_EXISTS_RENAME);
      

         
      if ($file) { 
        if ($settings->get('filter_char') == '1' && preg_match('/[^\00-\255]+/u', $file->getFileName())) { 
            //filter file name for special characters
            $form['doc_upload_message']['#markup'] = t('file name contains non authorized characters');

        } else  {  
                $file->setPermanent();
                $file->save();
                $filename  = $file->getFileName();
                $uri = $file->getFileUri();
                    $fields = array(
                      'uid' => $user,
                      //'fid' => '',
                      'type' => 0,
                      'filename' => $filename,
                      'uri' => $uri,
                      'folder' => Xss::filter($form_state->getValue('folder')),
                      'comment' => '',
                      'date' => time(),
                      'size' => filesize($uri),
                      'share' => 0,
                      'share_uid' => 0,
                      'share_gid' => 0,
                      'expire' => 0
                    );            

              $insert = Database::getConnection('external_db', 'external_db')
                      ->insert('ek_documents')
                      ->fields($fields)
                      ->execute();

                $log = 'user ' . \Drupal::currentUser()->id() .'|'. \Drupal::currentUser()->getUsername() .'|upload|'. $filename;
                \Drupal::logger('ek_documents')->notice( $log );  
                $form['doc_upload_message']['#markup'] = t('file uploaded @f', array('@f' => $filename ));
                if($user == 0){
                    \Drupal\Core\Cache\Cache::invalidateTags(['common_documents']);
                } else {
                    \Drupal\Core\Cache\Cache::invalidateTags(['my_documents']);
                }
            }
            
        } else {
          
          $form['doc_upload_message']['#markup'] = t('error copying file');
          
        }

       return $form['doc_upload_message'];
  }


}
