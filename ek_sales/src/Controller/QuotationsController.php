<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\QuotationsController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
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

        $build['filter_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterQuotation');
        $header = array(
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'revision' => array(
                'data' => $this->t('Revision'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'sort' => 'desc',
            ),
            'client' => array(
                'data' => $this->t('Client'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
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
                ->select('ek_quotation', 'q');
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
                    ->query("SELECT SQL_CACHE date from {ek_quotation} order by date limit 1")
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

            $query = "SELECT name from {ek_address_book} where id=:id";
            $client_name = $abook[$r->client];
            $client_part = substr($client_name, 0, 15);
            $link = Url::fromRoute('ek_address_book.view', array('id' => $r->client))->toString();
            $client = "<a title='" . t('client') . ": " . $client_name . "' href='" . $link . "'>" . $client_part . "</a>";
            $query = "SELECT name from {ek_company} where id=:id";
            $co = $companies[$r->header];
            if($r->header <> $r->allocation) {
                $co = $co . "<br/>" . t('for') . ": " . $companies[$r->allocation];
            }
            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', array(':p' => $r->pcode))
                            ->fetchField();
                    $link = Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                    $pcode_parts = explode('-', $r->pcode);
                    $reference = $r->serial . "<br/><a title='" . 
                            t('project') . ": " . $r->pcode . "' href='" . $link . "'>" . $pcode_parts[4] . "</a>";
                }
            } else {
                $reference = $r->serial;
            }

            $value = $r->currency . ' ' . number_format($r->amount, 2);

            if ($r->incoterm && !stristr($r->incoterm, 'na')) {

                $incoterm = explode('|', $r->incoterm);
                $value .= '<br/>' . $incoterm[0] . ' ' . $r->currency . ' ' . number_format(($r->amount * $incoterm[1] / 100), 2);
                $term = $r->amount * $incoterm[1] / 100;
            } else {
                $term = 0;
            }

            if ($r->tax) {

                $tax = explode('|', $r->tax);
                $value .= '<br/>' . $tax[0] . ' ' . $r->currency . ' ' . number_format(($r->amount + $term ) * $tax[1] / 100, 2);
            }

            //quotations are recorded by revision No. Each revision is kept in history
            //only last revision is displayed

            $query = "SELECT DISTINCT revision FROM {ek_quotation_details} WHERE serial=:s order by revision";
            $revisions = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $r->serial));

            $revision_numbers = array();

            WHILE ($revs = $revisions->fetchObject()) {

                $revision_numbers[] = $revs->revision;
                $last = $revs->revision;
            }

            $query = 'SELECT sum(total) from {ek_quotation_details} WHERE serial=:s and revision=:r';
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
                'reference' => ['data' => ['#markup' => $reference]],
                'revision' => $last,
                'issuer' => array('data' => ['#markup' => $co], 'title' => $r->title),
                'date' => $r->date,
                'client' => ['data' => ['#markup' => $client]],
                'value' => ['data' => ['#markup' => $value]],
                'status' => $status,
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

        $build['new_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\NewQuotation');

        return $build;
    }

    public function EditQuotation(Request $request, $id) {

        $build['edit_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\NewQuotation', $id);

        return $build;
    }

    public function InvoiceQuotation(Request $request, $id) {

        $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ConvertQuotation', $id);
        return $build;
    }

    public function PrintShareQuotations($id) {
        
        //filter access to document
        $query = "SELECT `header`, `allocation` FROM {ek_quotation} WHERE id=:id";
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

    public function DeleteQuotations(Request $request, $id) {

        $build['delete_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\DeleteQuotation', $id);
        return $build;
    }

//end class  
}
