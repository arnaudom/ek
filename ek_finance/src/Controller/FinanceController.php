<?php

/**
* @file
* Contains \Drupal\ek_finance\Controller\FinanceController.
*/
namespace Drupal\ek_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
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
      $content = '';
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
              $options = array( 'width' => '30%', );
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


}