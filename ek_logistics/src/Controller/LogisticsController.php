<?php
/**
* @file
* Contains \Drupal\ek\Controller\EkController.
*/
namespace Drupal\ek_logistics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;

/**
* Controller routines for ek module routes.
*/
class LogisticsController extends ControllerBase {

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
   * Return dashboard
   *
*/

 public function dashboard(Request $request) {
 
 return array();
 
 }
 

/**
   * Return stock list
   *
*/

 public function ListStock(Request $request) {
 
    $build['filter'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\FilterItemsList');
    
    if(isset($_SESSION['istockfilter']['filter']) && $_SESSION['istockfilter']['filter'] == 1) {
    
        $header = array(
      'id' => array(
        'data' => $this->t('Id'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'itemcode' => array(
        'data' => $this->t('Item Code'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'name' => array(
        'data' => $this->t('Name'),
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
        
      ),

      'stock' => array(
        'data' => $this->t('Stock'),
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
        
      ),
           
            
    );
      
      if($_SESSION['istockfilter']['coid'] == '%') {
                  $access = AccessCheck::GetCompanyByUser();
        } else {
                  $access = array($_SESSION['istockfilter']['coid']);
           }    
        
      if(isset($_SESSION['istockfilter']['keyword']) 
              && $_SESSION['istockfilter']['keyword'] != NULL
              && $_SESSION['istockfilter']['keyword'] != '%') {
          
          $keyword = SafeMarkup::checkPlain($_SESSION['istockfilter']['keyword']) . '%';
          $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_item_packing','p');
          $query->leftJoin('ek_items', 'i', 'i.itemcode=p.itemcode');
          $query->leftJoin('ek_item_barcodes', 'b', 'b.itemcode=p.itemcode');
          
          $or = db_or();
            $or->condition('p.itemcode', $keyword, 'like');
            $or->condition('i.description1', $keyword , 'like');
            $or->condition('b.barcode', $keyword , 'like');
            
            $data = $query->fields('p', array('itemcode', 'units','unit_measure'))
              ->fields('i', array('id'))
              ->distinct()
              ->fields('i', array('description1')) 
              ->condition('coid', $access , 'IN')
              ->condition($or)
              ->extend('Drupal\Core\Database\Query\TableSortExtender')
              ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
              ->limit(30)->orderBy('p.id', 'ASC')
              ->execute();  
          
      } else {
        


                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_item_packing','p');
                $query->leftJoin('ek_items', 'i', 'i.itemcode=p.itemcode');
                
                if($_SESSION['istockfilter']['tag'] != '%') {
                //filter by tag.
                  switch($_SESSION['istockfilter']['tag']) {

                  case 'type' :
                    $query->condition('type', $_SESSION['istockfilter']['tagvalue'] , '=');
                  break;

                  case 'department' :
                    $query->condition('department', $_SESSION['istockfilter']['tagvalue'] , '=');
                  break;    

                  case 'family' :
                    $query->condition('family', $_SESSION['istockfilter']['tagvalue'] , '=');
                  break; 

                  case 'collection' :
                    $query->condition('collection', $_SESSION['istockfilter']['tagvalue'] , '=');
                  break;

                  case 'color' :
                     $query->condition('color', $_SESSION['istockfilter']['tagvalue'] , '=');
                  break;    

                  }

                } else {
                    //todo
                    }
      
          $data = $query->fields('p', array('itemcode', 'units','unit_measure'))
                ->fields('i', array('description1')) 
                ->fields('i', array('id'))
                ->condition('active', $_SESSION['istockfilter']['status'] , 'like')
                ->condition('coid', $access , 'IN')
                ->extend('Drupal\Core\Database\Query\TableSortExtender')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(30)
                ->orderBy('id', 'ASC')
                ->execute();        
                
                
                }//no keyword
    
    
  $i=0;
  $extract = array();
    while ($r = $data->fetchObject()) { 
    $i++;
    $url = Url::fromRoute('ek_products.view', array('id' => $r->id), array())->toString();
    $item = "<a href='" . $url ."' target='_blank'>" . $r->itemcode . "</a>";
      $options[$i] = array(
      
      'id' => $r->id,
      'itemcode' => array ('data' => ['#markup' => $item] , 'title' => '' ),
      'name' => array ('data' => $r->description1 , 'title' => '' ),
      'stock' => array ('data' => $r->units . ' ' . $r->unit_measure , 'title' => '' ),
      );    
   
    //build array list of items for excel
    array_push($extract, $r->id);
    }
    $extract = serialize($extract);
    $excel =  Url::fromRoute('ek_logistics_excel_stock', array('param' => $extract), array())->toString();
    $build['excel'] = array(
      '#markup' => "<a href='" . $excel ."' target='_blank'>" . t('Export current view') . "</a>",
    ); 
    $build['items_table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $options,
      '#attributes' => array('id' => 'items_table'),
      '#empty' => $this->t('No item found'),
      '#attached' => array(
        'library' => array('ek_logistics/ek_logistics_css'),       
      ),
    );       
  
    
    $build['pager'] = array(
      '#type' => 'pager',
    ); 
           
   }//filter = 1
    
    
    return $build;  
 
 }
 
 
/**
   * Render excel file for items list
   *
   * @param array $param id list
   *
*/

 public function excelItemsStock($param = NULL) {
    $markup = array();    
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else { 
            $extract = unserialize($param);
            
            if(!empty($extract)){
                $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_items', 'i');
                $query->leftJoin('ek_item_packing', 'p', 'i.itemcode=p.itemcode');
                $query->leftJoin('ek_item_prices', 'c', 'i.itemcode=c.itemcode');
                $query->leftJoin('ek_company', 'y', 'i.coid=y.id');
                $query->leftJoin('ek_address_book', 'a', 'i.supplier=a.id');
                $data = $query
                            ->fields('i')->fields('p')->fields('c')->fields('y', ['name'])
                            ->fields('a', ['name']) 
                            ->condition('i.id',$extract , 'IN')
                            ->orderBy('i.id', 'ASC')
                            ->execute();

                $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_item_barcodes', 'b');
                $query->join('ek_items', 'i', 'i.itemcode=b.itemcode');
                $barcode_data = $query
                            ->fields('b', ['itemcode', 'barcode'])          
                            ->condition('i.id', $extract, 'IN')
                            ->orderBy('b.id', 'ASC')
                            ->execute();
                $barcode = $barcode_data->fetchAll(\PDO::FETCH_ASSOC);
                $markup = array();
                include_once drupal_get_path('module', 'ek_logistics').'/excel_items_stock.inc';
            } else {
               $markup = t('No data available'); 
            }
        }
    return ['#markup' => $markup];
     
     
 }
 
} //class
