<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\QuotationsController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Controller routines for ek module routes.
 */
class QuotationsController extends ControllerBase {
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
     * Return quotes 
     *
     */
    public function SettingsQuotations(Request $request) {

        $build['settings'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SettingsQuotation');
        return $build;
    }

    public function ListQuotations(Request $request) {

        $build['filter_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterQuotation');
        $header = array(
            'number' => array(
                'data' => $this->t('Number'),
                
            ),
            'revision' => array(
                'data' => $this->t('Revision'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
             'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),           
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'sort' => 'desc',
            ),
            'value' => array(
                'data' => $this->t('Value'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => $this->t('Operations'),
        );



        /*
         * Table - query data
         */


        $access = AccessCheck::GetCompanyByUser();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q');
        $or1 = db_or();
        $or1->condition('header', $access, 'IN');
        $or1->condition('allocation', $access, 'IN');


        if (isset($_SESSION['qfilter']['filter'])) {
            $data = $query
                    ->fields('q')
                    ->condition($or1)
                    ->condition('status', $_SESSION['qfilter']['status'], 'like')
                    ->condition('client', $_SESSION['qfilter']['client'], 'like')
                    ->condition('date', $_SESSION['qfilter']['from'], '>=')
                    ->condition('date', $_SESSION['qfilter']['to'], '<=')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(10)
                    ->orderBy('id', 'ASC')
                    ->execute();
        } else {


            $from = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT date from {ek_sales_quotation} order by date limit 1")
                    ->fetchField();
            $data = $query
                    ->fields('q')
                    ->condition($or1)
                    ->condition('status', '%', 'like')
                    ->condition('date', $from, '>=')
                    ->condition('date', date('Y-m-d'), '<=')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(10)
                    ->orderBy('id', 'ASC')
                    ->execute();
        }

        //store company data
        $companies = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name from {ek_company}")
                    ->fetchAllKeyed();
        //store a. book data
        $abook = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name from {ek_address_book}")
                    ->fetchAllKeyed();
        
        while ($r = $data->fetchObject()) {

            $number = "<a title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_sales.quotations.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            
            if(isset($abook[$r->client])) {
                $client_name = $abook[$r->client];
                $reference = \Drupal\ek_address_book\AddressBookData::geturl($r->client, ['short' => 8]);
            }
            
            $co = $companies[$r->header];
            if($r->header <> $r->allocation) {
                $for = isset($companies[$r->allocation]) ? "<br/>" . t('for') . ": " . $companies[$r->allocation] : '';
                $co = $co . $for;
            }
            
            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $reference .= "<br/>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode, NULL, NULL, TRUE);
                }
            } 

            $value = $r->currency . ' ' . number_format($r->amount, 2);
            
            $incoterm = explode('|', $r->incoterm);
            if ($incoterm[0] != '0') {                
                $value .= '<br/>' . $incoterm[0] . ' ' . $r->currency . ' ' . number_format(($r->amount * $incoterm[1] / 100), 2);
                $term = $r->amount * $incoterm[1] / 100;
            } else {
                $term = 0;
            }

            if ($r->tax) {
                $tax = explode('|', $r->tax);
                $value .= '<br/>' . $tax[0] . ' ' . $r->currency . ' ' . number_format(($r->amount) * $tax[1] / 100, 2);
            }

            //quotations are recorded by revision No. Each revision is kept in history
            //only last revision is displayed

            $query = "SELECT DISTINCT revision FROM {ek_sales_quotation_details} WHERE serial=:s order by revision";
            $revisions = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $r->serial));

            $revision_numbers = array();

            WHILE ($revs = $revisions->fetchObject()) {

                $revision_numbers[] = $revs->revision;
                $last = $revs->revision;
            }

            $query = 'SELECT sum(total) from {ek_sales_quotation_details} WHERE serial=:s and revision=:r';
            $taxable = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $r->serial, ':r' => $last))
                    ->fetchField();
            //$tax = $taxable*$r->taxvalue/100;


            if ($r->status == 0)
                $status = t('open');
            if ($r->status == 1)
                $status = t('printed');
            if ($r->status == 2)
                $status = t('invoiced');

            $options[$r->id] = array(
                'number' => ['data' => ['#markup' => $number]],
                'revision' => $last,
                'reference' => ['data' => ['#markup' => $reference]],
                'issuer' => array('data' => ['#markup' => $co], 'title' => $r->title),
                'date' => ['data' => ['#markup' => $r->date]],
                'value' => ['data' => ['#markup' => $value]],
                'status' => ['data' => ['#markup' => $status]],
            );

            $links = array();

            if ($r->status <> 2) {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_sales.quotations.edit', ['id' => $r->id]),
                );


                $links['invoice'] = array(
                    'title' => $this->t('Convert to invoice'),
                    'url' => Url::fromRoute('ek_sales.quotations.invoice', ['id' => $r->id]),
                );
            }

            if (\Drupal::currentUser()->hasPermission('print_share_quotation')) {
                $links['print'] = array(
                    'title' => $this->t('Print'),
                    'url' => Url::fromRoute('ek_sales.quotations.print_share', ['id' => $r->id]),
                );
            }

            if (\Drupal::currentUser()->hasPermission('delete_quotation') && $r->status == 0) {

                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_sales.quotations.delete', ['id' => $r->id]),
                );
            }


            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        } //while


        $build['quotations_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'quotations_table'),
            '#empty' => $this->t('No quotation available.'),
            '#attached' => array(
                'library' => array('ek_sales/ek_sales_css'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
    }

    public function NewQuotations(Request $request) {

        $build['new_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Quotation');

        return $build;
    }

    public function EditQuotation(Request $request, $id) {

        $build['edit_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Quotation', $id);

        return $build;
    }

    public function InvoiceQuotation(Request $request, $id) {

        $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ConvertQuotation', $id);
        return $build;
    }

    public function PrintShareQuotations($id) {
        
        //filter access to document
        $query = "SELECT `header`, `allocation` FROM {ek_sales_quotation} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                  ->query($query, [':id' => $id])
                  ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if(in_array($data->header, $access) || in_array($data->allocation, $access)) {
            
            $format = 'pdf';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'quotation', $format);
            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id){
                
                $id = explode('_', $_SESSION['printfilter']['for_id']);

                $param = serialize(
                        array(
                            $id[0],
                            $id[1],
                            $_SESSION['printfilter']['signature'],
                            $_SESSION['printfilter']['stamp'],
                            $_SESSION['printfilter']['template'],
                            $_SESSION['printfilter']['contact'],
                        )
                );

                $build['filter_mail'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $param);

                $path = $GLOBALS['base_url'] . "/invoices/print/pdf/" . $param;

                $iframe = "<iframe src ='" . $path . "' width='100%' height='1000px' id='view' name='view'></iframe>";
                $build['iframe'] = $iframe;
                $build['external'] = '<i class="fa fa-external-link" aria-hidden="true"></i>';
            }
            return array(
                '#items' => $build,
                '#theme' => 'iframe',
                '#attached' => array(
                    'library' => array('ek_sales/ek_sales_print','ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $url = Url::fromRoute('ek_sales.quotations.list')->toString();
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];          
        }
    }

    public function PdfQuotations(Request $request, $param) {

        $markup = array();
        $format = 'pdf';
        include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';
        return new Response($markup);
    }

    

    /**
     * @retun
     *  a display of quotation in html format
     * 
     * @param 
     *  INT $id document id
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `header`, `allocation` FROM {ek_sales_quotation} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'quotation', 'html');
            $document = '';

            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {
                
                $id = explode('_', $_SESSION['printfilter']['for_id']);
                $doc_id = $id[0];
                $param = serialize(
                        array(
                            $id[0], //id
                            $id[1], //source
                            $_SESSION['printfilter']['signature'],
                            $_SESSION['printfilter']['stamp'],
                            $_SESSION['printfilter']['template'],
                            $_SESSION['printfilter']['contact'],
                        )
                );

                $format = 'html';
                
                $url_pdf = Url::fromRoute('ek_sales.quotations.print_share', ['id' => $doc_id], [])->toString();
                $url_edit = Url::fromRoute('ek_sales.quotations.edit', ['id' => $doc_id], [])->toString();
                include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';


                $build['quotation'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_sales/ek_sales_html_documents_css','ek_admin/ek_admin_css'),
                        'placeholders' => $css,
                    ),
                ];
            }
            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.quotations.list')->toString();
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];
        }
    }    
    
    
    public function DeleteQuotations(Request $request, $id) {

        $build['delete_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\DeleteQuotation', $id);
        return $build;
    }

//end class  
}
