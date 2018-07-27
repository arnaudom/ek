<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Controller\ReceivingController.
 */

namespace Drupal\ek_logistics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_logistics\LogisticsSettings;

/**
 * Controller routines for ek module routes.
 */
class ReceivingController extends ControllerBase {
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
     * Return invoices
     *
     */
    public function listdata(Request $request) {

        $build['filter_receiving'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Filter');
        $header = array(
            'serial' => array(
                'data' => $this->t('number'),
                'class' => array(),
                'id' => 'Number',
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'reference',
            ),
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'issuer',
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'date',
                'id' => 'date',
            ),
            'delivery' => array(
                'data' => $this->t('Delivery'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'due',
                'field' => 'ddate',
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'specifier' => 'status',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'status',
            ),
            'operations' => array(
                $this->t('Operations'),
                'id' => 'operations',
            ),
        );



        /*
         * Table - query data
         */


        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $path = \Drupal::request()->getRequestUri();

        if (strpos($path, 'list-receiving')) {
            $type = 'RR';
            $edit_route = "ek_logistics_receiving_edit";
        } else {
            $type = 'RT';
            $edit_route = "ek_logistics_returning_edit";
        }


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_receiving', 'd');

        $or = db_or();
        $or->condition('head', $access, 'IN');
        $or->condition('allocation', $access, 'IN');


        if (isset($_SESSION['lofilter']['filter']) && $_SESSION['lofilter']['filter'] == 1) {

            $status = $_SESSION['lofilter']['status'];
            $client = $_SESSION['lofilter']['client'];
            $from = $_SESSION['lofilter']['from'];
            $to = $_SESSION['lofilter']['to'];
        } else {

            $status = '%';
            $client = '%';
            $from = date('Y-m') . '-01';
            $to = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT SQL_CACHE date from {ek_logi_delivery} order by date DESC limit 1")
                    ->fetchField();
            if ($to < $from) {
                $to = $from;
            }
        }

        //$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
        $data = $query
                ->fields('d', ['id', 'head', 'serial', 'supplier', 'status', 'title', 'date', 'ddate', 'pcode'])
                ->condition($or)
                ->condition('d.type', $type, '=')
                ->condition('d.status', $status, 'like')
                ->condition('d.supplier', $client, 'like')
                ->condition('d.date', $from, '>=')
                ->condition('d.date', $to, '<=')
                ->extend('Drupal\Core\Database\Query\TableSortExtender')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(20)->orderByHeader($header)
                ->execute();

        $options = array();
        while ($r = $data->fetchObject()) {

            $settings = new LogisticsSettings($r->head);
            $client = \Drupal\ek_address_book\AddressBookData::geturl($r->supplier,['short' => 8]);
            $query = "SELECT name from {ek_company} where id=:id";
            $co = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $r->head))
                    ->fetchField();
            $number = "<a title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_logistics.receiving.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            
            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $reference = $client . "<br/>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode);
                } else {
                    $reference = $client;
                }
            } else {
                $reference = $client;
            }

            if ($r->status == 0) {
                $status = "<i class='fa fa-circle green' aria-hidden='true'></i> " . t('open');
            }
            if ($r->status == 1) {
                $status = "<i class='fa fa-circle blue' aria-hidden='true'></i> " . t('printed');
            }
            if ($r->status == 2) {
                $status = "<i class='fa fa-circle red' aria-hidden='true'></i> " . t('posted');
            }
            
            $options[$r->id] = array(
                'number' => ['data' => ['#markup' => $number], 'title' => t('view in browser')],
                'reference' => ['data' => ['#markup' => $reference]],
                'issuer' => array('data' => $co, 'title' => $r->title),
                'date' => $r->date,
                'delivery' => $r->ddate,
                'status' => ['data' => ['#markup' => $status]],
            );

            $links = array();

            if ($r->status == 0 || ($settings->get('edit') == 1 && $r->status == 1 ) || ($settings->get('edit') == 2 && $r->status == 2 )
            ) {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute($edit_route, ['id' => $r->id]),
                );
            }


            if ($r->status == 1) {
                $links['post'] = array(
                    'title' => $this->t('Post quantities'),
                    'url' => Url::fromRoute('ek_logistics_receiving_post', ['id' => $r->id]),
                );
            }

           
            if (\Drupal::currentUser()->hasPermission('print_share_receiving')) {

                $links['print'] = array(
                    'title' => $this->t('Print and share'),
                    'url' => Url::fromRoute('ek_logistics_receiving_print_share', ['id' => $r->id]),
                );
                /*
                 * @param
                 * id
                 * source
                 * mode (0 download, 1 save)
                 * template, 0 = default
                 */
                $param = serialize([$r->id, 'logi_receiving', 0, 0]);

                $links['excel'] = array(
                    'title' => $this->t('Excel download'),
                    'url' => Url::fromRoute('ek_logistics_receiving_excel', ['param' => $param]),
                    'attributes' => array('target' => '_blank'),
                );
            }
            if (\Drupal::currentUser()->hasPermission('delete_receiving') && $r->status == 0) {

                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_logistics_receiving_delete', ['id' => $r->id]),
                );
            }
            $links['clone'] = array(
                'title' => $this->t('Clone'),
                'url' => Url::fromRoute('ek_logistics_receiving_clone', ['id' => $r->id]),
            );


            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        } //while


        $build['logistics_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'logistics_table'),
            '#empty' => $this->t('No receiving order available.'),
            '#attached' => array(
                'library' => array('ek_logistics/ek_logistics_css','ek_admin/ek_admin_css'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
    }

    public function edit(Request $request, $id) {

        //filter edit
        if($id){
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_receiving', 'd')
                    ->fields('d', ['head','status','type'])
                    ->condition('id', $id , '=');
            $d = $query->execute()->fetchObject();
            $settings = new LogisticsSettings($d->head); 
            if ( $d->status == 0 || ($settings->get('edit') == 1 && $d->status == 1 ) 
                    || ($settings->get('edit') == 2 && $d->status == 2)
                ) {
                  $build['receive'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Receiving', $id); 
            } else {
                if ($d->type = 'RR') {
                    $route = 'ek_logistics_list_receiving';
                } else {
                    $route = 'ek_logistics_list_returning';
                }
                $url = Url::fromRoute($route)->toString();
                $build['back'] = array(
                    '#markup' => t('Document not editable. Go to <a href="@url" >List</a>.', array('@url' => $url ) ) ,
                );
            }
        } else {
            //new
            $build['receive'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Receiving');
        }

        return $build;
    }

    public function cloneit(Request $request, $id) {
        $build['receive'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Receiving', $id, 'clone');

        return $build;
    }

    public function alert(Request $request, $id) {
        $build['alert_receiving'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\AlertReceiving', $id);

        return $build;
    }

    /**
     * @retun
     *  a display receiving order in html format
     * 
     * @param 
     *  INT $id document id
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation`, `type` FROM {ek_logi_receiving} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        
        if($data->type == 'RR') {
            $type = 'receiving';
            $edit_route = 'ek_logistics_receiving_edit';
        } else {
            $type = 'returning';
            $edit_route = 'ek_logistics_returning_edit';
        }
     
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\FilterPrint', $id, $type , 'html');
            $document = '';

            if (isset($_SESSION['logisticprintfilter']['filter']) && $_SESSION['logisticprintfilter']['filter'] == $id) {

                $id = explode('_', $_SESSION['logisticprintfilter']['for_id']);
                $doc_id = $id[0];
                $param = serialize(
                        array(
                            0 => $id[0], //id
                            1 => 'logi_' . $id[1], //source
                            2 => $_SESSION['logisticprintfilter']['signature'],
                            3 => $_SESSION['logisticprintfilter']['stamp'],
                            4 => $_SESSION['logisticprintfilter']['template'],
                            5 => $_SESSION['logisticprintfilter']['contact'],
                        )
                );

                $format = 'html';
                if ($this->moduleHandler->moduleExists('ek_products')) {
                    $product = TRUE;
                }
                $url_pdf = Url::fromRoute('ek_logistics_receiving_print_share', ['id' => $doc_id], [])->toString();
                $url_excel = Url::fromRoute('ek_logistics_receiving_excel', ['param' => serialize([$doc_id,'logi_delivery',0,0])], [])->toString();
                $url_edit = Url::fromRoute($edit_route, ['id' => $doc_id], [])->toString();
                
                include_once drupal_get_path('module', 'ek_logistics') . '/manage_print_output.inc';

                $build['receiving'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_logistics/ek_logistics_html_documents_css','ek_admin/ek_admin_css'),
                    ),
                ];
            }
            return array($build);
        } else {
            $url = Url::fromRoute('ek_logistics_list_' . $type)->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => t('Go to <a href="@url" >List</a>.',['@url' => $url])];
            return [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
            
        }
    }

    /*
     * Print and share receiving order
     * @param int $id document id
     * @return array form for email sharing
     * @return pdf output
     */

    public function printshare(Request $request, $id) {

        $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\FilterPrint', $id, 'receiving', 'pdf');

        if (isset($_SESSION['logisticprintfilter']['filter']) && $_SESSION['logisticprintfilter']['filter'] == $id) {

            $id = explode('_', $_SESSION['logisticprintfilter']['for_id']);
            $param = serialize(
                    array(
                        $id[0],
                        'logi_' . $id[1], //source
                        $_SESSION['logisticprintfilter']['signature'],
                        $_SESSION['logisticprintfilter']['stamp'],
                        $_SESSION['logisticprintfilter']['template'],
                        $_SESSION['logisticprintfilter']['contact'],
                    )
            );

            $build['filter_mail'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $param);

            $path = $GLOBALS['base_url'] . "/logistics/receiving/pdf/" . $param;
            $iframe = "<iframe src ='" . $path . "' width='100%' height='800px' id='view' name='view'></iframe>";
            $build['iframe'] = $iframe;
            $build['external'] = '<i class="fa fa-external-link" aria-hidden="true"></i>';
        }

        return array(
            '#items' => $build,
            '#theme' => 'iframe',
            '#attached' => array(
                'library' => array('ek_logistics/ek_logistics_print', 'ek_admin/ek_admin_css'),
            ),
        );
    }

    public function pdf(Request $request, $param) {
        $markup = array();
        $format = 'pdf';
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $product = TRUE;
        }
        include_once drupal_get_path('module', 'ek_logistics') . '/manage_print_output.inc';
        return $markup;
    }

    public function excel(Request $request, $param) {
        $markup = array();
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $product = TRUE;
        }        
        include_once drupal_get_path('module', 'ek_logistics') . '/manage_excel_output.inc';
        return $markup;
    }

    public function post(Request $request, $id) {
        $build['post_receiving'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Post', $id);

        return $build;
    }

    public function delete(Request $request, $id) {
        
        $query = "SELECT status,serial,type FROM {ek_logi_receiving} WHERE id=:id";
        $table = 'receiving';
        $opt = [0 => t('open'), 1 => t('printed'), 2 => t('invoiced'), 3 => t('posted')];
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))->fetchObject();
        if ($data->type == 'RR'){
            $route = "ek_logistics_list_receiving"; 
            $doc = t('Receiving');
        } else {
            $route = "ek_logistics_list_returning";
            $doc = t('Returning');
        } 
        if($data->status > 0){
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => t('@document cannot be deleted.' , array('@document' => $doc))];
            $items['description'] = ['#markup' => $opt[$data->status]];
            $url = Url::fromRoute($route, [],[])->toString();            
            $items['link'] = ['#markup' => t("<a href=\"@url\">Back</a>",['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
            ];  
        } else {
            $build['delete_delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Delete', $id, $table,$data->type);
        }        
        
         return $build;
    }

//end class  
}
