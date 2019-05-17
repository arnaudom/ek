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
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    //protected $uuidService;

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
        $query->fields('book', ['name', 'type']);
        $query->leftJoin('ek_address_book_comment', 'c', 'book.id = c.abid');
        $query->fields('c', ['comment']);
        $query->condition('id', $abid);
        $ab = $query->execute()->fetchObject();

        if ($ab) {
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
                    if ($d->sum == NULL) {
                        $d->sum = '0';
                    }
                    $total += $d->sum;
                    $items['category_statistics'][$d->status] = (int) $d->sum;
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
                    $categories = [t('open'), t('awarded'), t('completed'), t('closed')];

                    $seriesData = [
                        [
                            "data" => [ $items['category_statistics']['open'], $items['category_statistics']['awarded'], $items['category_statistics']['completed'], $items['category_statistics']['closed']],
                            "colors" => [$chartSettings['colors'][0], $chartSettings['colors'][1], $chartSettings['colors'][2], $chartSettings['colors'][3]]
                        ]
                    ];

                    $uuid_service = \Drupal::service('uuid');
                    $element = [
                        '#theme' => 'charts_api',
                        '#library' => (string) $chartSettings['library'],
                        '#categories' => $categories,
                        '#seriesData' => $seriesData,
                        '#options' => $options,
                        '#id' => 'chart-' . $uuid_service->generate(),
                        '#override' => [],
                    ];

                    $items['project_status_chart'] = \Drupal::service('renderer')->render($element);
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
            if ($ab->type == '1') {
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
            while ($d = $data->fetchObject()) {
                ;
                array_push($inv, $d->amount);
            }
            $items['invoices'] = array(
                'max' => (int) max($inv),
                'min' => (int) min($inv),
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
                $options['title_position'] = 'top';
                $categories = [];
                $seriesData = [
                    ["name" => t('Highest'), "color" => $chartSettings['colors'][0], "type" => "bar", "data" => [$items['invoices']['max']]],
                    ["name" => t('Lowest'), "color" => $chartSettings['colors'][1], "type" => "bar", "data" => [$items['invoices']['min']]],
                    ["name" => t('Average'), "color" => $chartSettings['colors'][2], "type" => "bar", "data" => [$items['invoices']['avg']]]
                ];

                $uuid_service = \Drupal::service('uuid');
                $element = [
                    '#theme' => 'charts_api',
                    '#library' => (string) $chartSettings['library'],
                    '#categories' => $categories,
                    '#seriesData' => $seriesData,
                    '#options' => $options,
                    '#id' => 'chart-' . $uuid_service->generate(),
                    '#override' => [],
                ];

                $items['invoices_chart'] = \Drupal::service('renderer')->render($element);


                $options = [];
                $options['type'] = 'line';
                $options['title'] = ($ab->type == 1) ? t('Sales per year') : t('Purchases per year');
                $options['yaxis_title'] = $items['baseCurrency'];
                $options['yaxis_min'] = '';
                $options['yaxis_max'] = '';
                $options['xaxis_title'] = t('Years');
                $options['legend_position'] = 'bottom';
                $options['title_position'] = 'top';
                $categories = [date('Y') - 6, date('Y') - 5, date('Y') - 4, date('Y') - 3, date('Y') - 2, date('Y') - 1, date('Y')];
                $seriesData = [
                    [ "name" => t('Transactions') . " " . $items['baseCurrency'],
                        "type" => 'line',
                        "data" => [
                            (int) $items['sales_year'][date('Y') - 6],
                            (int) $items['sales_year'][date('Y') - 5],
                            (int) $items['sales_year'][date('Y') - 4],
                            (int) $items['sales_year'][date('Y') - 3],
                            (int) $items['sales_year'][date('Y') - 2],
                            (int) $items['sales_year'][date('Y') - 1],
                            (int) $items['sales_year'][date('Y')]
                        ],
                        "color" => $chartSettings['colors'][0],
                    ],
                ];

                $uuid_service = \Drupal::service('uuid');
                $element = [
                    '#theme' => 'charts_api',
                    '#library' => (string) $chartSettings['library'],
                    '#categories' => $categories,
                    '#seriesData' => $seriesData,
                    '#options' => $options,
                    '#id' => 'chart-' . $uuid_service->generate(),
                    '#override' => [],
                ];

                $items['sales_year_chart'] = \Drupal::service('renderer')->render($element);
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
                'max' => (int) max($af),
                'min' => (int) min($af),
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
                $options['legend_position'] = 'bottom';
                $options['title_position'] = 'top';

                $categories = ['days'];
                $seriesData = [
                    ["name" => t('Highest'), "color" => $chartSettings['colors'][0], "type" => "bar", "data" => [$items['payment_performance']['max']]],
                    ["name" => t('Lowest'), "color" => $chartSettings['colors'][1], "type" => "bar", "data" => [$items['payment_performance']['min']]],
                    ["name" => t('Average'), "color" => $chartSettings['colors'][2], "type" => "bar", "data" => [$items['payment_performance']['avg']]]
                ];

                $uuid_service = \Drupal::service('uuid');
                $element = [
                    '#theme' => 'charts_api',
                    '#library' => (string) $chartSettings['library'],
                    '#categories' => $categories,
                    '#seriesData' => $seriesData,
                    '#options' => $options,
                    '#id' => 'chart-' . $uuid_service->generate(),
                    '#override' => [],
                ];

                $items['payment_performance_chart'] = \Drupal::service('renderer')->render($element);
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
                    'ek_sales/ek_sales_css', 'ek_admin/ek_admin_css'),
            ),
            '#cache' => [
                'tags' => ['sales_data']
            ],
        );
    }

    /**
     * Return sales document data page
     * data by address book entry
     * @param abid
     *  id of address book
     */
    public function DataBookDocuments(Request $request, $abid) {
        
        $items['abidlink'] = ['#markup' => \Drupal\ek_address_book\AddressBookData::geturl($abid)];
        //upload form for documents
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\UploadForm', $abid);
        $query = "SELECT count(id) FROM {ek_sales_documents} WHERE "
                        . "abid=:abid";
        $items['document'] = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':abid' => $abid])
                    ->fetchField();
        
        return array(
            '#title' => t('Documents'),
            '#items' => $items,
            '#theme' => 'ek_sales_documents',
            '#attached' => array(
                'drupalSettings' => array('abid' => $abid),
                'library' => array(
                    'ek_sales/ek_sales_docs_updater',
                    'ek_sales/ek_sales_css', 'ek_admin/ek_admin_css', 'ek_admin/classic_doc'),
            ),
            '#cache' => [
                'tags' => ['sales_data']
            ],
        );
        
    }

    /**
     * return folders name autocomplete
     * @param request
     * @return Json response
     */
    public function lookupFolders(Request $request,$abid = NULL) {
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_documents'); 
        $data = $query
              ->fields('ek_sales_documents', ['folder'])
              ->distinct()
              ->condition('abid', $abid)
              ->condition('folder', $request->query->get('q') . '%', 'LIKE')
              ->execute()
              ->fetchCol();

        return new JsonResponse($data);
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
        $query->orderBy('folder', 'ASC');
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
                $items[$l->folder][$i]['folder'] = $l->folder;
                $items[$l->folder][$i]['id'] = $l->id;
                $items[$l->folder][$i]['fid'] = 1; //default file status on
                $items[$l->folder][$i]['delete'] = 1; //default delete action is on
                $items[$l->folder][$i]['icon'] = 'file'; //default icon 
                $items[$l->folder][$i]['file_url'] = ''; //default
                $items[$l->folder][$i]['access_url'] = 0; //default access management if off


                $share = explode(',', $l->share);
                $deny = explode(',', $l->deny);

                if ($l->share == '0' || ( in_array(\Drupal::currentUser()->id(), $share) && !in_array(\Drupal::currentUser()->id(), $deny) )) {
                    $items[$l->folder][$i]['uri'] = $l->uri;
                    $extension = explode(".", $l->filename);
                    $extension = strtolower(array_pop($extension));
                    $items[$l->folder][$i]['icon']  = '_doc_list';  
                    if (ek_admin_filter_ico($extension)) {
                        $items[$l->folder][$i]['icon'] = $extension . '_doc_list';
                    }

                    //filename formating
                    if (strlen($l->filename) > 30) {
                        $items[$l->folder][$i]['doc_name'] = substr($l->filename, 0, 30) . " ... ";
                    } else {
                        $items[$l->folder][$i]['doc_name'] = $l->filename;
                    }

                    if ($l->fid == '0') { //file was deleted
                        $items[$l->folder][$i]['fid'] = 0;
                        $items[$l->folder][$i]['delete'] = 0;
                        $items[$l->folder][$i]['email'] = 0;
                        $items[$l->folder][$i]['extranet'] = 0;
                        $items[$l->folder][$i]['comment'] = $l->comment . " " . date('Y-m-d', $l->uri);
                    } else {
                        if (!file_exists($l->uri)) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $items[$l->folder][$i]['fid'] = 2;
                            $items[$l->folder][$i]['delete'] = 0;
                            $items[$l->folder][$i]['email'] = 0;
                            $items[$l->folder][$i]['extranet'] = 0;
                            $items[$l->folder][$i]['comment'] = t('Document not available. Please contact administrator');
                        } else {
                            //file exist
                            $route = Url::fromRoute('ek_sales_delete_file', array('id' => $l->id))->toString();
                            $items[$l->folder][$i]['delete_url'] = $route;
                            $items[$l->folder][$i]['file_url'] = file_create_url($l->uri);
                            $items[$l->folder][$i]['delete'] = 1;
                            $items[$l->folder][$i]['comment'] = $l->comment;
                            $items[$l->folder][$i]['date'] = date('Y-m-d', $l->date);
                            $items[$l->folder][$i]['size'] = round($l->size / 1000, 0) . " Kb";
                        }
                    }


                    if ($l->fid != '0') {
                        //add access link for non deleted files
                        $param_access = 'access|' . $l->id . '|sales_doc';
                        $link = Url::fromRoute('ek_sales_modal', ['param' => $param_access])->toString();
                        $items[$l->folder][$i]['access_url'] = $link;
                    }
                } //built list of accessible files by user
            }
        }
        if($i > 0){
            $render = ['#theme' => 'ek_sales_doc_view', '#items' => $items];
            $data = \Drupal::service('renderer')->render($render);
        } else {
            $data == NULL;
        }
        
        return new JsonResponse(array('data' => $data));
    }

    /**
     * Return ajax drag & drop
     *
     */
    public function dragDrop(Request $request) {

        $from = explode("-", $request->get('from'));
        $fields = array('folder' => $request->get('to'));
        $result = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_documents')
                ->condition('id', $from[1])
                ->fields($fields)
                ->execute();
        
        return new Response('', 204);
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
        $content = [];
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
                $settings = new \Drupal\ek_finance\FinanceSettings();
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
                $settings = new \Drupal\ek_finance\FinanceSettings();
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
                $options = array('width' => '50%',);
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
     * Return ajax delete confirmation alert
     * @param $id document id
     * @return ajax response
     */
    public function deleteFile($id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_documents', 'd')
                ->fields('d', ['filename'])
                ->condition('id', $id, '=');
        $file = $query->execute()->fetchField();
        $url = Url::fromRoute('ek_sales_delete_file_confirm', ['id' => $id])->toString();
        $content = array('content' =>
            array('#markup' =>
                "<div><a href='" . $url . "' class='use-ajax'>"
                . t('delete') . "</a> " . $file . "</div>")
        );

        $response = new AjaxResponse();

        $title = $this->t('Confirm');
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        $response->addCommand(new OpenModalDialogCommand($title, $content));


        return $response;
    }

    /**
     * delete confirmed action
     * @param $id document id
     * @return ajax response
     */
    public function deleteFileConfirmed($id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_documents', 'd')
                ->fields('d')
                ->condition('id', $id, '=');
        $data = $query->execute()->fetchObject();

        $share = explode(',', $data->share);
        $deny = explode(',', $data->deny);
        $user = \Drupal::currentUser()->id();
        $del = 0;
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand());

        if ($data->share != '0') {

            if (in_array($user, $share) and ! in_array($user, $deny)) {
                //user has access
                $del = 1;
            }
        } else {
            //any user can delete
            $del = 1;
        }

        if ($del == 1) {
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
            \Drupal\Core\Cache\Cache::invalidateTags(['sales_data']);
            $log = 'sales document|user|' . \Drupal::currentUser()->id() . '|delete|' . $data->filename;
            \Drupal::logger('ek_sales')->notice($log);
            $response->addCommand(new RemoveCommand('#row' . $id));
        } else {
            $content = array('content' =>
                array('#markup' => "<div>" . t('access denied') . "</div>")
            );
            $log = 'sales document|user|' . \Drupal::currentUser()->id() . '|error delete|' . $data->filename;
            \Drupal::logger('ek_sales')->notice($log);
            $title = $this->t('Error');
            $response->addCommand(new OpenModalDialogCommand($title, $content));
        }

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
     * @return array form to reset a payment
     * @param $doc = document key i.e invoice|purchase
     * @param $id = id of doc
     *
     */
    public function ResetPayment($doc, $id) {

        $build = [];
        switch ($doc) {
            case 'invoice':
                $tb = "ek_sales_invoice";
                $route = 'ek_sales.invoices.list';
                break;
            case 'purchase' :
                $tb = "ek_sales_purchase";
                $route = 'ek_sales.purchases.list';
                break;
        }

        $query = Database::getConnection('external_db', 'external_db')
                ->select($tb, 't');
        $query->fields('t', ['head', 'status', 'serial']);
        $query->condition('id', $id);
        $data = $query->execute()->fetchObject();

        $read = 1;
        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        if (!in_array($data->head, $access)) {
            $read = 0;
            $message = t('You are not authorized to view this content');
        }

        $reco = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($doc == 'invoice') {
                $source = 'receipt';
            }
            if ($doc == 'purchase') {
                $source = 'payment';
            }
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 't');
            $query->addExpression('count(id)', 'sumValue');
            $query->condition('coid', $data->head);
            $query->condition('source', $source);
            $query->condition('reference', $id);
            $query->condition('reconcile', 1);

            $reco = $query->execute()->fetchField();
            if ($reco > 0) {
                $message = t('This entry cannot be deleted because it has been reconciled.');
            }
        }

        if ($read == 0 || $reco > 0 || $data->status != 1) {
            if (!isset($message)) {
                $message = t('This @doc cannot be reset because it has not been paid', ['@doc' => $doc]);
            }
            $url = Url::fromRoute($route)->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => $message];
            $items['link'] = ['#markup' => t('Go to <a href="@url" >List</a>.', ['@url' => $url])];
            return [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        } else {
            $build['reset_pay'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ResetPay', $doc, $id, $data->head, $data->serial);
        }

        return $build;
    }

}
