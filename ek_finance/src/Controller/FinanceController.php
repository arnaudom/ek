<?php
/**
* @file
* Contains \Drupal\ek\Controller\EkController.
*/
namespace Drupal\ek_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
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
   *  
   *
*/
  public function dashboard(Request $request) {
  
  return array('#markup' => '');
  
  }



/**
 * General modal display to view data
 * -> use for account history
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
   *
   * @param bool $is_modal
   *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  protected function dialog($is_modal = FALSE, $param = NULL) {
      
      $opt = unserialize($param);
      $content = '';
        switch ($opt['id']) {
            case 'trial':  
            case 'bs':
            case 'pl':
            case 'journal':
              $content = history($param);
              $options = array( 'width' => '50%', );   
                break;
            case 'reporting':
              $content = history($param);
              $options = array( 'width' => '50%', );
                break;

        }
      
    $response = new AjaxResponse();
    $title = $this->t('history @aid', array('@aid' => $opt['aid']));
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






} //class