<?php

/**
* @file
* Contains \Drupal\ek_finance\Controller\FinanceController.
*/
namespace Drupal\ek_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;


/**
* Controller routines for ek module routes.
*/
class FinanceController extends ControllerBase {
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
      $container->get('form_builder')
    );
  }

  /**
   * Constructs a AccountsChartController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }


/**
* Dashboard content
*   @return array
*/
  public function dashboard(Request $request) {
  
  return array('#markup' => '');
  
  }



/**
 * General modal display to view data

 * AJAX callback handler for AjaxTestDialogForm.
 */
  public function modal($param) {
    return $this->dialog(TRUE, $param);
  }

  /**
   * AJAX callback handler for AjaxTestDialogForm.
   */
  public function nonModal($param) {
    return $this->dialog(FALSE, $param);
  }


  /**
   * Util to render dialog in ajax callback.
   *  -> use for account (journal) history display
   *  -> use to add currency
   *
   * @param bool $is_modal
   *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
   * 
   * @param array $param
   *    serialized array of keys => values
   * 
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  protected function dialog($is_modal = FALSE, $param = NULL) {
      
      $opt = unserialize($param);
      $content = [];
        switch ($opt['id']) {
            case 'trial':  
            case 'bs':
            case 'pl':
            case 'journal':
              $content = history($param);
              $options = array( 'width' => '50%', );
              $title = ucfirst($this->t('history @aid', array('@aid' => $opt['aid'])));
                break;
            case 'reporting':
              $content = history($param);
              $options = array( 'width' => '50%', );
              $title = ucfirst($this->t('history @aid', array('@aid' => $opt['aid'])));
                break;
            case 'currency' :
              $content = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewCurrencyForm'); 
              $options = array( 'width' => '50%', );
              $title = $this->t('new currency');
                break;
        }
      
    $response = new AjaxResponse();
    
    $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
        
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
   * Util to retrieve data with ajax callback.
   *  -> use for memo attachments display
   *  
   *
   * @param string $type
   *   define key of query type
   * 
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   An json response object.
   */
  public function ajaxCall(Request $request, $type = NULL) {
      
      switch($type) {
          case 'memofiles':
              $memo_id = $request->get('id');
              $memo_serial = $request->get('serial');
              $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_expenses_memo_documents', 'doc');
              
              if(!null == $memo_id) {
                $or = $query->orConditionGroup();
                  $or->condition('memo.id', $memo_id);
                  $or->condition('doc.serial', $memo_serial);
                  $query->fields('doc', ['id', 'uri']);
                  $query->leftJoin('ek_expenses_memo', 'memo', 'doc.serial = memo.serial');
                  $query->condition($or);
              } else {
                  $query->fields('doc', ['id', 'uri']);
                  $query->condition('doc.serial', $memo_serial);
              }
                $docs = $query->execute();
                $output = '';
                While($doc = $docs->fetchObject()) {
                    $name = explode('/', $doc->uri);
                    $output .="<div class='row' id='row-". $doc->id ."'>
                                <div class='cell'>
                                  <a href='" . file_create_url($doc->uri)  ."' target='_blank'>". array_pop($name) ."</a>
                                </div>
                                <div class='cell'>
                                <a  class='button delButton' id='" . $doc->id ."' name='attachment-" . $doc->id ."'>" . t('delete attachment') . "</a>                                </div>
                               </div>";
                }
                if($output == '') {
                    $output = "<div class='row'>
                                <div class='cell'>
                                  " . t('no attachment'). "
                                </div>";
                }
                
                 return new JsonResponse(array('list' => $output));
              break;
              
          case 'memofilesdelete':
              $file_id = $request->get('id');
              
              $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo_documents', 'doc');
              $query->fields('doc', ['serial','id', 'uri']);
              $query->leftJoin('ek_expenses_memo', 'memo', 'doc.serial = memo.serial');
              $query->fields('memo', ['category','entity']);
              $query->condition('doc.id', $file_id);
               
              $data = $query->execute()->fetchObject();
              $del = FALSE;
              //filter access
              if($data->category < 5) {
                  $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
                  if(in_array($data->entity, $access)) {
                      $del = TRUE;
                  }
              } else {
                  if(\Drupal::currentUser()->id() == $data->entity) {
                      $del = TRUE;
                  }
              }
              
              if($del == TRUE) {
                \Drupal::service('file_system')->delete($data->uri);
              
                Database::getConnection('external_db', 'external_db')
                ->delete('ek_expenses_memo_documents')
                ->condition( 'id', $file_id)
                ->execute();
              
                return new JsonResponse(array('response' => TRUE));
              } else {
                return new JsonResponse(array('response' => FALSE));  
              }
              break;
      }
      
      
  }
}