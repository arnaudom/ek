<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\SalesController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_projects\ProjectData;

use Drupal\ek_finance\Journal;


/**
 * Controller routines for ek module routes.
 */
class SalesController extends ControllerBase {
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
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
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
     * Return main 
     *
     */
    public function ManageSales(Request $request) {

        return array('#markup' => '');
    }

    /**
     * Return sales data page
     * data by address book entry
     * @param abid
     *  id of address book
     */
    public function DataSales(Request $request, $abid) {
        
        $theme = 'ek_sales_data';
        $items = array();

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'book');
        $query->fields('book', ['name','type']);
        $query->leftJoin('ek_address_book_comment', 'c', 'book.id = c.abid');
        $query->fields('c', ['comment']);
        $query->condition('id', $abid);
        $ab = $query->execute()->fetchObject();
        
        if($ab) {
            $items['data'] = 1;
            $items['abidname'] = $ab->name;
            
            $items['abidlink'] = ['#markup' => \Drupal\ek_address_book\AddressBookData::geturl($abid)];
            //upload form for documents
            $items['form'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\UploadForm', $abid);

            //comments
            $items['comment'] = html_entity_decode($ab->comment, ENT_QUOTES, "utf-8");
            $param_edit = 'comment|' . $abid . '|address_book|50%';
            $items['url_comment'] = Url::fromRoute('ek_sales_modal', ['param' => $param_edit])->toString();
            $items['edit_comment'] = t('<a href="@url" class="@c"  >[ edit ]</a>', array('@url' => $items['url_comment'], '@c' => 'use-ajax red '));


            //projects linked
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                $query = "SELECT p.id,p.status,date,pcode,pname,level,priority,cid,last_modified,c.name
                    FROM {ek_project} p
                    INNER JOIN {ek_country} c
                    ON p.cid=c.id
                     WHERE client_id= :abid
                     order by date";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':abid' => $abid));
                $items['projects'] = array();
                while ($d = $data->fetchObject()) {

                    $items['projects'][] = array(
                        'link' => \Drupal\ek_projects\ProjectData::geturl($d->id),
                        'pcode' => $d->pcode,
                        'pname' => $d->pname,
                        'date' => $d->date,
                        'last_modified' => date('Y-m-d', $d->last_modified),
                        'country' => $d->name,
                        'status' => $d->status,
                        'level' => $d->level,
                        'priority' => $d->priority,
                    );
                }
            }
            //reports
            if ($this->moduleHandler->moduleExists('ek_intelligence')) {
                $query = "SELECT id,serial,edit FROM {ek_ireports} WHERE abid=:c";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':c' => $abid));
                $items['reports'] = array();
                while ($d = $data->fetchObject()) {
                    $link = Url::fromRoute('ek_intelligence.read', ['id' => $d->id])->toString();
                    $items['reports'][] = array(
                        'link' => $link,
                        'serial' => '<a href="' . $link . '">' . $d->serial . '</a>',
                        'edit' => date('Y-m-d', $d->edit),
                    );
                }
            }

            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //statistics cases
                $query = "SELECT count(pcode) as sum, status FROM {ek_project}"
                        . " WHERE client_id=:abid group by status";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':abid' => $abid));
                $total = 0;
                $items['category_statistics'] = array();
                while ($d = $data->fetchObject()) {
                    if($d->sum == NULL) {
                        $d->sum = '0';
                    }
                    $total += $d->sum;
                    $items['category_statistics'][$d->status] = (int)$d->sum;
                }
                $items['category_statistics']['total'] = $total;
                
                if ($this->moduleHandler->moduleExists('charts')) {
                    $theme = 'ek_sales_data_charts';
                    $chartSettings = \Drupal::service('charts.settings')->getChartsSettings();

                    $options = [];
                    $options['type'] = 'donut';
                    $options['title'] = t('Projects');
                    
                    $options['xaxis_title'] = $items['baseCurrency'];
                    
                    $options['title_position'] = 'in';
                    $categories = [t('open'),t('awarded'),t('completed'),t('closed')];
                   
                    $seriesData = [
                      [
                       "data" => [ $items['category_statistics']['open'],$items['category_statistics']['awarded'],$items['category_statistics']['completed'],$items['category_statistics']['closed'] ],
                       "colors" => [$chartSettings['colors'][0],$chartSettings['colors'][1],$chartSettings['colors'][2],$chartSettings['colors'][3]]  
                      ]
                    ];
                    
                
                    $element = [
                      '#theme' => 'charts_api',
                      '#library' => $chartSettings['library'],
                      '#categories' => $categories,
                      '#seriesData' => $seriesData,
                      '#options' => $options,
                    ];                

                    $items['project_status_chart'] =  \Drupal::service('renderer')->render($element); 
                }
                                
                
                
                
                

                $items['category_year_statistics'] = array();
                $query = "SELECT id,type FROM {ek_project_type}";
                $type = Database::getConnection('external_db', 'external_db')
                                ->query($query)->fetchAllKeyed();

                for ($y = date('Y') - 6; $y <= date('Y'); $y++) {
                    $total = 0;
                    $query = "SELECT count(pcode) as sum, category FROM {ek_project} WHERE "
                            . "client_id=:abid ANd date like :d group by category";

                    $data = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':abid' => $abid, ':d' => $y . '%'));

                    $items['category_year_statistics'][$y] = array();
                    while ($d = $data->fetchObject()) {
                        $items['category_year_statistics'][$y][$type[$d->category]] = $d->sum;
                    }
                }
            }
            if ($this->moduleHandler->moduleExists('ek_finance')) {
                //statistics sales
                $settings = new \Drupal\ek_finance\FinanceSettings();
                $items['baseCurrency'] = $settings->get('baseCurrency');
            }   
            
            //sales data
            if($ab->type == '1') {
                $source = 'invoice';
                $query = "SELECT sum(totalbase) as total FROM {ek_sales_invoice_details} d "
                        . "INNER JOIN {ek_sales_invoice} i ON d.serial=i.serial "
                        . "WHERE i.client=:abid";
                
                $query2 = "SELECT amountbase as amount FROM {ek_sales_invoice} i "
                        . "WHERE i.client=:abid";
                
                $query3 = "SELECT sum(amountbase) as sum FROM {ek_sales_invoice} WHERE "
                            . "client=:abid AND date like :d";
                
                $query4 = "SELECT date,pay_date FROM {ek_sales_invoice} "
                        . "WHERE client = :abid and status=:s";
            } else {
                $source = 'purchase';
                $query = "SELECT sum(amountbc) as total FROM {ek_sales_purchase} "
                        . "WHERE client=:abid";
                
                $query2 = "SELECT amountbc as amount FROM {ek_sales_purchase} "
                        . "WHERE client=:abid";
                
                $query3 = "SELECT sum(amountbc) as sum FROM {ek_sales_purchase} WHERE "
                            . "client=:abid AND date like :d";
                
                $query4 = "SELECT date,pdate FROM {ek_sales_purchase} "
                        . "WHERE client = :abid and status=:s";
            }
            

                $a = array(
                    ':abid' => $abid,
                );
                $items['total_income'] = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)
                        ->fetchField();
                
                
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query2, $a);
                
                $inv = array();
                while ($d = $data->fetchObject()) {;
                    array_push($inv, $d->amount);
                }
                $items['invoices'] = array(
                    'max' => (int)max($inv),
                    'min' => (int)min($inv),
                    'avg' => round((array_sum($inv) / count($inv)), 1)
                );   
                
                $items['sales_year'] = array();
                for ($y = date('Y') - 6; $y <= date('Y'); $y++) {
                    $total = 0;
                    
                    $data = Database::getConnection('external_db', 'external_db')
                            ->query($query3, array(':abid' => $abid, ':d' => $y . '%'));

                    
                    while ($d = $data->fetchObject()) {
                        $items['sales_year'][$y] = $d->sum;
                    }
                }
                
                if (isset($chartSettings)) {
                    
                    $options = [];
                    $options['type'] = 'bar';
                    $options['title'] = ($ab->type == 1) ? t('Sales structure') : t('Purchases structure');
                    $options['yaxis_title'] = t($source);
                    $options['yaxis_min'] = '';
                    $options['yaxis_max'] = '';
                    $options['xaxis_title'] = $items['baseCurrency'];
                    $options['legend_position'] = 'bottom';
                    $options['title_position'] = 'in';
                    $categories = [];
                    $seriesData = [
                      ["name" => t('Highest'), "color" => $chartSettings['colors'][0], "type" => "bar", "data" => [$items['invoices']['max']]],
                      ["name" => t('Lowest'), "color" => $chartSettings['colors'][1], "type" => "bar", "data" => [$items['invoices']['min']]],
                      ["name" => t('Average'), "color" => $chartSettings['colors'][2], "type" => "bar", "data" => [$items['invoices']['avg']]]
                    ];

                    $element = [
                      '#theme' => 'charts_api',
                      '#library' => $chartSettings['library'],
                      '#categories' => $categories,
                      '#seriesData' => $seriesData,
                      '#options' => $options,
                    ];                

                    $items['invoices_chart'] =  \Drupal::service('renderer')->render($element); 
                    
                    
                    $options = [];
                    $options['type'] = 'line';
                    $options['title'] = ($ab->type == 1) ? t('Sales per year') : t('Purchases per year');
                    $options['yaxis_title'] = $items['baseCurrency'];
                    $options['yaxis_min'] = '';
                    $options['yaxis_max'] = '';
                    $options['xaxis_title'] = t('Years');
                    $options['legend_position'] = 'bottom';
                    $options['title_position'] = 'in';
                    $categories = [date('Y') - 6, date('Y') - 5, date('Y') - 4, date('Y') - 3, date('Y') - 2, date('Y') - 1, date('Y')];
                    $seriesData = [
                      [ "name" => t('Transactions') . " " . $items['baseCurrency'],
                        "type" => 'line',
                        "data" => [
                          (int)$items['sales_year'][date('Y') - 6],
                          (int)$items['sales_year'][date('Y') - 5],
                          (int)$items['sales_year'][date('Y') - 4],
                          (int)$items['sales_year'][date('Y') - 3],
                          (int)$items['sales_year'][date('Y') - 2],
                          (int)$items['sales_year'][date('Y') - 1],
                          (int)$items['sales_year'][date('Y')]
                          ],
                       "color" => $chartSettings['colors'][0],
                      ],
                      ];

                    $element = [
                      '#theme' => 'charts_api',
                      '#library' => $chartSettings['library'],
                      '#categories' => $categories,
                      '#seriesData' => $seriesData,
                      '#options' => $options,
                    ];                

                    $items['sales_year_chart'] =  \Drupal::service('renderer')->render($element);                     
                    
                }
                
                //Payment performance
                $query4 = "SELECT date,pay_date FROM {ek_sales_invoice} "
                        . "WHERE client = :abid and status=:s";

                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query4, array(':abid' => $abid, ':s' => 1));

                $af = array();

                while ($d = $data->fetchObject()) {
                    $long = round((strtotime($d->pay_date) - strtotime($d->date)) / (24 * 60 * 60), 0);
                    array_push($af, $long);
                }
                $items['payment_performance'] = array(
                    'max' => (int)max($af),
                    'min' => (int)min($af),
                    'avg' => round((array_sum($af) / count($af)), 1)
                );
                             
                if (isset($chartSettings)) { 
                    $options = [];
                    $options['type'] = 'bar';
                    $options['title'] = t('Payments performance');
                    $options['yaxis_title'] = t('terms');
                    $options['yaxis_min'] = '';
                    $options['yaxis_max'] = '';
                    $options['xaxis_title'] = '';

                    $categories = ['days'];
                    $seriesData = [
                      ["name" => t('Highest'), "color" => $chartSettings['colors'][0], "type" => "bar", "data" => [$items['payment_performance']['max']]],
                      ["name" => t('Lowest'), "color" => $chartSettings['colors'][1], "type" => "bar", "data" => [$items['payment_performance']['min']]],
                      ["name" => t('Average'), "color" => $chartSettings['colors'][2], "type" => "bar", "data" => [$items['payment_performance']['avg']]]
                    ];

                    $element = [
                      '#theme' => 'charts_api',
                      '#library' => $chartSettings['library'],
                      '#categories' => $categories,
                      '#seriesData' => $seriesData,
                      '#options' => $options,
                    ];                

                    $items['payment_performance_chart'] =  \Drupal::service('renderer')->render($element);      
                 
                         
                
            }
        } else {
            $items['abidname'] = t('No data');
            $items['abidlink'] = Url::fromRoute('ek_address_book.search')->toString();
            $items['data'] = NULL;
        }

        return array(
            '#items' => $items,
            '#title' => t('Sales data'),
            '#theme' => $theme,
            '#attached' => array(
                'drupalSettings' => array('abid' => $abid),
                'library' => array(
                    'ek_sales/ek_sales_docs_updater', 
                    'ek_sales/ek_sales_css','ek_admin/ek_admin_css'),
                
            ),
        );
    }

    /**
     * Return data called to update documents for sales data
     *
     */
    public function load(Request $request) {


        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_documents', 'd');
        $query->fields('d');
        $query->condition('abid', $request->get('abid'), '=');
        $query->orderBy('id', 'ASC');
        $list = $query->execute();

        //build list of documents
        $t = '';
        $i = 0;
        $items = [];
        if (isset($list)) {
            while ($l = $list->fetchObject()) {
                $i++;
                /* default values */
                    $items[$i]['fid'] = 1; //default file status on
                    $items[$i]['delete'] = 1; //default delete action is on
                    $items[$i]['icon'] = 'file';//default icon 
                    $items[$i]['file_url'] = ''; //default
                    $items[$i]['access_url'] = 0; //default access management if off
                    
                    
                $share = explode(',', $l->share);
                $deny = explode(',', $l->deny);

                if ($l->share == '0' || ( in_array(\Drupal::currentUser()->id(), $share) 
                        && !in_array(\Drupal::currentUser()->id(), $deny) )) {

                    
                    $items[$i]['uri'] = $l->uri;

                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);
                    
                    $items[$i]['icon_path'] = drupal_get_path('module', 'ek_sales') . '/art/icons/';
                    
                    if (file_exists(drupal_get_path('module', 'ek_sales') . '/art/icons/' . $extension . ".png")) {
                        $items[$i]['icon'] = strtolower($extension);
                    } 

                    //filename formating
                    if (strlen($l->filename) > 30) {
                        $items[$i]['doc_name'] = substr($l->filename, 0, 30) . " ... ";
                    } else {
                        $items[$i]['doc_name'] = $l->filename;
                    }

                    if ($l->fid == '0') { //file was deleted
                        $items[$i]['fid'] = 0;
                        $items[$i]['delete'] = 0;
                        $items[$i]['email'] = 0;
                        $items[$i]['extranet'] = 0;
                        $items[$i]['comment'] = $l->comment . " " . date('Y-m-d', $l->uri);
                    } else {
                        if (!file_exists($l->uri)) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $items[$i]['fid'] = 2;
                            $items[$i]['delete'] = 0;
                            $items[$i]['email'] = 0;
                            $items[$i]['extranet'] = 0;
                            $items[$i]['comment'] = t('Document not available. Please contact administrator');
                        } else {
                            //file exist
                            $route = Url::fromRoute('ek_sales_delete_file', array('id' => $l->id))->toString();
                            $items[$i]['delete_url'] = $route;
                            $items[$i]['file_url'] = file_create_url($l->uri);
                            $items[$i]['delete'] = 1;
                            $items[$i]['comment'] = $l->comment;
                            $items[$i]['date'] = date('Y-m-d', $l->date);
                            $items[$i]['size'] = round($l->size / 1000, 0) . " Kb";
                            
                        }
                    }


                    if ($l->fid != '0') {
                        //add access link for non deleted files
                        $param_access = 'access|' . $l->id . '|sales_doc';
                        $link = Url::fromRoute('ek_sales_modal', ['param' => $param_access])->toString();
                        $items[$i]['access_url'] = $link;
                    } 
                    
 
                } //built list of accessible files by user
            }
            
             
        }
        
        $render = ['#theme' => 'ek_sales_doc_view', '#items' => $items];
        $data =  \Drupal::service('renderer')->render($render);
        return new JsonResponse(array('data' => $data));
    }

    /**
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

        $param = explode('|', $param);
        $content = '';
        switch ($param[0]) {

            case 'access' :
                $id = $param[1];
                $type = $param[2];
                $content = $this->formBuilder->getForm('Drupal\ek_sales\Form\DocAccessEdit', $id, $type);
                $options = array('width' => '30%',);
                break;

            case 'comment' :
                $content = $this->formBuilder->getForm('Drupal\ek_sales\Form\SalesFieldEdit', $param[1], 'comment');
                $options = array('width' => $param[3],);

                break;
            
            case 'invoice':
                $id = $param[1];
                $options = array('width' => '30%',);
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
                $query = 'SELECT currency,amount,amountreceived,pay_date,amountbase,balancebase,taxvalue '
                        . 'FROM {ek_sales_invoice} WHERE id=:id';
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchObject();
                $gross = $data->amount + (round($data->amount * $data->taxvalue / 100, 2));
                $bal = $gross - $data->amountreceived;
                $base = $data->amountbase - $data->balancebase;

                $content['#markup'] = "<table>"
                        . "<tbody>"
                        . "<tr>"
                        . "<td>" . t('Receivable') . "</td><td>" . $data->currency . " " . number_format($gross, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Received') . "</td><td>" . $data->currency . " " . number_format($data->amountreceived, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Balance') . "</td><td>" . $data->currency . " " . number_format($bal, 2) . "<td>"
                        . "</tbody></table><br/>";

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    //extract journal transactions;
                    $journal = new Journal();
                    $content['#markup'].= $journal->entity_history(array('entity' => 'invoice', 'id' => $id));
                }
                break;
            
            case 'purchase':
                $id = $param[1];
                $options = array('width' => '30%',);
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
                $query = 'SELECT currency,amount,amountpaid,pdate,amountbc,balancebc,taxvalue '
                        . 'FROM {ek_sales_purchase} WHERE id=:id';
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchObject();
                $gross = $data->amount + (round($data->amount * $data->taxvalue / 100, 2));
                $bal = $gross - $data->amountpaid;
                $base = $data->amountbc - $data->balancebc;

                $content['#markup'] = "<table>"
                        . "<tbody>"
                        . "<tr>"
                        . "<td>" . t('Payable') . "</td><td>" . $data->currency . " " . number_format($gross, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Paid') . "</td><td>" . $data->currency . " " . number_format($data->amountpaid, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Balance') . "</td><td>" . $data->currency . " " . number_format($bal, 2) . "<td>"
                        . "</tbody></table><br/>";

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    //extract journal transactions;
                    $content['#markup'].= Journal::entity_history(array('entity' => 'purchase', 'id' => $id));
                }
                break;                
                
            case 'quick_edit':
                $param[0] = str_replace("_", " ", $param[0]) . " " . $param[2];
                $options = array('width' => '30%',);
                $content = $this->formBuilder->getForm('Drupal\ek_sales\Form\QuickEdit', $param[1], $param[2]);
                break;
                
        }

        $response = new AjaxResponse();
        $title = ucfirst($this->t($param[0]));
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';


        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content, $options);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-text-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $content));
        }
        return $response;
    }

    /**
     * delete a file from list 
     *
     */
    public function deletefile(Request $request, $id) {

        //$id = $request->query->get('id');
        $p = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_sales_documents} WHERE id=:f", array(':f' => $id))
                ->fetchObject();
        $fields = array(
            'uri' => date('U'),
            'fid' => 0,
            'comment' => t('deleted by') . ' ' . \Drupal::currentUser()->getUsername(),
            'date' => time()
        );
        $delete = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_documents')->fields($fields)->condition('id', $id)
                ->execute();
        if ($delete) {

            $query = "SELECT * FROM {file_managed} WHERE uri=:u";
            $file = db_query($query, [':u' => $p->uri])->fetchObject();
            file_delete($file->fid);
        }


        $log = $p->pcode . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
        \Drupal::logger('ek_sales')->notice($log);

        $response = new AjaxResponse();
        $response->addCommand(new RemoveCommand('#sd' . $id));
        return $response;
    }

    /**
     * Return ajax user autocomplete data
     *
     */
    public function userautocomplete(Request $request) {

        $text = $request->query->get('term');
        $name = array();

        $query = "SELECT distinct name from {users_field_data} WHERE mail like :t1 or name like :t2 ";
        $a = array(':t1' => "$text%", ':t2' => "$text%");
        $name = db_query($query, $a)->fetchCol();

        return new JsonResponse($name);
    }

    /**
     * @retun
     *  a from to reset a payment
     * @param $doc = document key i.e invoice|purchase
     * @param $id = id of doc
     *
     */
    public function ResetPayment($doc, $id) {
        
        
        $build['reset_pay'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ResetPay', $doc, $id);

        return $build;
    }
//end class  
}
