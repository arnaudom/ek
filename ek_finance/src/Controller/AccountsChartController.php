<?php

/**
* @file
* Contains \Drupal\ek_finance\Controller\AccountsChartController.
*/

namespace Drupal\ek_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\FinanceSettings;

/**
* Controller routines for ek module routes.
*/
class AccountsChartController extends ControllerBase {

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
   *  Form to manage and edit accounts chart
   *
*/
  public function chartaccounts(Request $request) {
  
    $build['chart_accounts'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ChartAccounts');  
    Return $build; 
  
  
  }

/**
   * Download chart per company in pdf format
   * @param int $coid
   *    company id
*/
  public function pdf($coid) {
  
        $markup = array();
        $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts');
        $query->fields('ek_accounts');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'asc');
        $data = $query->execute();
        $company = Database::getConnection('external_db', 'external_db')
                ->query('SELECT name FROM {ek_company} WHERE id=:id', [':id' => $coid])
                ->fetchField();
        
        include_once drupal_get_path('module', 'ek_finance') . '/chart_pdf';
        return $markup;
  
  
  }

 /**
   * Export chart per company in excel format
   * @param int $coid
   *    company id
*/
  public function exportExcel($coid) {
  
        $markup = array();
        
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
        $settings = new FinanceSettings(); 
        $baseCurrency = $settings->get('baseCurrency');
        $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts');
        $query->fields('ek_accounts');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'asc');
        $data = $query->execute();
        $company = Database::getConnection('external_db', 'external_db')
                ->query('SELECT name FROM {ek_company} WHERE id=:id', [':id' => $coid])
                ->fetchField();
        
        include_once drupal_get_path('module', 'ek_finance') . '/excel_chart';
        }
        
        return $markup;
  
  
  }

  
  /**
   * AJAX callback handler.
   * @param string $param
   */
  public function modal($param) {
    return $this->dialog(TRUE, $param);
  }

  /**
   * AJAX callback handler.
   * @param string $param
   */
  public function nonModal($param) {
    return $this->dialog(FALSE, $param);
  }


  /**
   * Util to render dialog in ajax callback.
   *
   * @param bool $is_modal
   *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
   * @param string $param
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  protected function dialog($is_modal = FALSE, $param = NULL) {
  
  
  $content = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewAccountForm', $param ); 
  $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
    
    $response = new AjaxResponse();
    $title = $this->t('New account');
    
    
    if ($is_modal) {
      $response->addCommand(new OpenModalDialogCommand($title, $content));
    }
    else {
      $selector = '#ajax-dialog-wrapper-1';
      $response->addCommand(new OpenDialogCommand($selector, $title, $html));
    }
    return $response;
  }


} 