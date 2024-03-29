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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_sales\SalesSettings;

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
                'id' => 'number',
            ),
            'revision' => array(
                'data' => $this->t('Revision'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'revision',
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'reference',
            ),
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'issuer',
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'sort' => 'desc',
                'id' => 'date',
            ),
            'value' => array(
                'data' => $this->t('Value'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'value',
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'status',
            ),
            'operations' => [
                $this->t('Operations'),
                'id' => 'operations',
            ]
        );



        /*
         * Table - query data
         */

         $globalsettings = new SalesSettings(0);
         if(null !== $globalsettings->get('listlength')) {
             $limit = $globalsettings->get('listlength');
         } else {
             $limit = 25;
         }
        $access = AccessCheck::GetCompanyByUser();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q');
        $or1 = $query->orConditionGroup();
        $or1->condition('head', $access, 'IN');
        $or1->condition('allocation', $access, 'IN');


        if (isset($_SESSION['qfilter']['filter']) && $_SESSION['qfilter']['filter'] == 1) {
            if ($_SESSION['qfilter']['keyword'] != '') {
                //search based on keyword
                $or2 = $query->orConditionGroup();
                $or2->condition('q.serial', '%' . $_SESSION['qfilter']['keyword'] . '%', 'like');
                $or2->condition('q.pcode', '%' . $_SESSION['qfilter']['keyword'] . '%', 'like');
                $f = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date',
                    'amount', 'pcode', 'incoterm', 'tax', 'type');
                $data = $query
                        ->fields('q', $f)
                        ->condition($or1)
                        ->condition($or2)
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($limit)
                        ->orderBy('id', 'ASC')
                        ->execute();
            } else {
                //search based on input fields
                $param = serialize(array(
                    'coid' => $_SESSION['qfilter']['coid'],
                    'from' => $_SESSION['qfilter']['from'],
                    'to' => $_SESSION['qfilter']['to'],
                    'client' => $_SESSION['qfilter']['client'],
                    'status' => $_SESSION['qfilter']['status'],
                    'currency' => $_SESSION['qfilter']['currency'],
                ));
                $excel = Url::fromRoute('ek_sales.quotations.excel', array('param' => $param))->toString();
                $build['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' title='" . $this->t('Excel download') . "'><span class='ico excel green'></span></a>"
                );
                $or2 = $query->orConditionGroup();
                $or2->condition('head', $_SESSION['qfilter']['coid']);
                $or2->condition('allocation', $_SESSION['qfilter']['coid']);
                $data = $query
                        ->fields('q')
                        ->condition($or1)
                        ->condition($or2)
                        ->condition('status', $_SESSION['qfilter']['status'], 'like')
                        ->condition('client', $_SESSION['qfilter']['client'], 'like')
                        ->condition('date', $_SESSION['qfilter']['from'], '>=')
                        ->condition('date', $_SESSION['qfilter']['to'], '<=')
                        ->condition('currency', $_SESSION['qfilter']['currency'], 'like')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($limit)
                        ->orderBy('id', 'ASC')
                        ->execute();
            }
        } else {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_quotation', 'q');
            $query->fields('q', ['date']);
            $query->orderBy('date', "DESC");
            $query->range(0, 1);
            $from = $query->execute()->fetchField();
            if ($from == '') {
                $from = date('Y-m-d');
            }
            $data = $query
                    ->fields('q')
                    ->condition($or1)
                    ->condition('status', '%', 'like')
                    ->condition('date', $from, '>=')
                    ->condition('date', date('Y-m-d'), '<=')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit($limit)
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
        $options = [];

        while ($r = $data->fetchObject()) {
            $number = "<a title='" . $this->t('view') . "' href='"
                    . Url::fromRoute('ek_sales.quotations.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";

            if (isset($abook[$r->client])) {
                $client_name = $abook[$r->client];
                $reference = \Drupal\ek_address_book\AddressBookData::geturl($r->client, ['short' => 8]);
            }

            $co = $companies[$r->head];
            if ($r->head <> $r->allocation) {
                $for = isset($companies[$r->allocation]) ? "<br/>" . $this->t('for') . ": " . $companies[$r->allocation] : '';
                $co = $co . $for;
            }

            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $reference .= "<div>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode, null, null, true) . "</div>";
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
                    ->query($query, [':s' => $r->serial]);

            $revision_numbers = array();

            while ($revs = $revisions->fetchObject()) {
                $revision_numbers[] = $revs->revision;
                $last = $revs->revision;
            }

            $query = 'SELECT sum(total) from {ek_sales_quotation_details} WHERE serial=:s and revision=:r';
            $taxable = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':s' => $r->serial, ':r' => $last])
                    ->fetchField();
            //$tax = $taxable*$r->taxvalue/100;


            if ($r->status == 0) {
                $status = $this->t('open');
            }
            if ($r->status == 1) {
                $status = $this->t('printed');
            }
            if ($r->status == 2) {
                $status = $this->t('invoiced');
            }

            $options[$r->id] = [
                'number' => ['data' => ['#markup' => $number]],
                'revision' => $last,
                'reference' => ['data' => ['#markup' => $reference]],
                'issuer' => ['data' => ['#markup' => $co], 'title' => $r->title],
                'date' => ['data' => ['#markup' => $r->date]],
                'value' => ['data' => ['#markup' => $value]],
                'status' => ['data' => ['#markup' => $status]],
            ];

            $links = [];
            $param = 'quick_edit|' . $r->id . '|quotation';
            $links['qedit'] = [
                'title' => $this->t('Quick edit'),
                'url' => Url::fromRoute('ek_sales.modal_more', ['param' => $param]),
                'attributes' => [
                    'class' => ['use-ajax'],
                    'data-dialog-type' => 'modal',
                    'data-dialog-options' => Json::encode(['width' => 700,]),
                ],
            ];
            if ($r->status <> 2) {

                $links['edit'] = [
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_sales.quotations.edit', ['id' => $r->id]),
                ];

                $links['invoice'] = [
                    'title' => $this->t('Convert to invoice'),
                    'url' => Url::fromRoute('ek_sales.quotations.invoice', ['id' => $r->id]),
                ];
            }

            if (\Drupal::currentUser()->hasPermission('print_share_quotation')) {
                $links['qprint'] = [
                    'title' => $this->t('Print'),
                    'url' => Url::fromRoute('ek_sales.quotations.print_share', ['id' => $r->id]),
                    'attributes' => ['class' => ['ico', 'pdf']]
                ];
                $links['qexcel'] = [
                    'title' => $this->t('Excel download'),
                    'url' => Url::fromRoute('ek_sales.quotations.print_excel', ['id' => $r->id]),
                    'attributes' => ['class' => ['ico', 'excel']]
                ];
            }

            $links['clone'] = [
                'title' => $this->t('Clone'),
                'url' => Url::fromRoute('ek_sales.quotations.edit', ['id' => $r->id], ['query' => ['action' => 'clone']]),
            ];

            if (\Drupal::currentUser()->hasPermission('delete_quotation') && $r->status == 0) {
                $links['delete'] = [
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_sales.quotations.delete', ['id' => $r->id]),
                ];
            }


            $options[$r->id]['operations']['data'] = [
                '#type' => 'operations',
                '#links' => $links,
            ];
        } 


        $build['quotations_table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'quotations_table'),
            '#empty' => $this->t('No quotation available.'),
            '#attached' => array(
                'library' => array('ek_sales/ek_sales_css', 'ek_admin/ek_admin_css'),
            ),
        ];

        $build['pager'] = [
            '#type' => 'pager',
        ];

        return $build;
    }

    public function NewQuotations(Request $request) {
        $build['new_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Quotation');

        return $build;
    }

    public function EditQuotation(Request $request, $id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q')
                ->fields('q', ['status'])
                ->condition('id', $id, '=');
        $status = $query->execute()->fetchField();
        $clone = ($request->query->get('action') == 'clone') ? true : false;
        if ($clone == true || $status <> 2) {
            $build['edit_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Quotation', $id, $clone);
        } else {
            $opt = ['0' => $this->t('open'), 1 => $this->t('printed'), 2 => $this->t('invoiced')];
            $url = Url::fromRoute('ek_sales.quotations.list', array(), array())->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => $this->t('@document cannot be edited.', array('@document' => $this->t('Qotation')))];
            $items['description'] = ['#markup' => $opt[$status]];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }


        return $build;
    }

    public function InvoiceQuotation(Request $request, $id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q')
                ->fields('q', ['status'])
                ->condition('id', $id, '=');
        $status = $query->execute()->fetchField();
        if ($status < 2) {
            $build['edit_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ConvertQuotation', $id);
        } else {
            $opt = ['0' => $this->t('open'), 1 => $this->t('printed'), 2 => $this->t('invoiced')];
            $url = Url::fromRoute('ek_sales.quotations.list', array(), array())->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => $this->t('@document cannot be converted.', array('@document' => $this->t('Qotation')))];
            $items['description'] = ['#markup' => $opt[$status]];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }

        return $build;
    }

    public function PrintShareQuotations($id) {

        //filter access to document
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q');
        $query->fields('q', ['head', 'allocation', 'pcode']);
        $query->condition('q.id', $id);
        $data = $query->execute()->fetchObject();

        $access = AccessCheck::GetCompanyByUser();

        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $format = 'pdf';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'quotation', $format);
            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {
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
                if ($data->pcode != null && $data->pcode != 'n/a') {
                    $build['filter_post'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterPostDoc', $param, $data->pcode);
                }
                $path = $GLOBALS['base_url'] . "/quotations/print/pdf/" . $param;

                $iframe = "<iframe src ='" . $path . "' width='100%' height='1000px' id='view' name='view'></iframe>";
                $build['iframe'] = $iframe;
                $build['external'] = '<i class="fa fa-external-link" aria-hidden="true"></i>';
            }
            return array(
                '#items' => $build,
                '#theme' => 'iframe',
                '#attached' => array(
                    'library' => array('ek_sales/ek_sales_print', 'ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $url = Url::fromRoute('ek_sales.quotations.list')->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
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

    public function PdfQuotations(Request $request, $param) {
        $markup = array();
        $format = 'pdf';
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/manage_print_output.inc';
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
        $query = "SELECT `head`, `allocation` FROM {ek_sales_quotation} WHERE id=:id";
        $doc_data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($doc_data->head, $access) || in_array($doc_data->allocation, $access)) {
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
                include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/manage_print_output.inc';


                $build['quotation'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_sales/ek_sales_html_documents_css', 'ek_admin/ek_admin_css'),
                    ),
                ];
            }
            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.quotations.list')->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
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

    /**
     * @retun
     *  a form to download quotation in excel format
     *
     * @param
     *  INT $id document id
     */
    public function Excel($id) {
        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_quotation} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'quotation', 'excel');

            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {
                //$_SESSION['printfilter']['filter'] = 0;
                $id = explode('_', $_SESSION['printfilter']['for_id']);

                $param = serialize(
                        array(
                            $id[0], //id
                            $id[1], //source
                            $_SESSION['printfilter']['signature'],
                            $_SESSION['printfilter']['stamp'],
                            $_SESSION['printfilter']['template'],
                            $_SESSION['printfilter']['contact'],
                            $_SESSION['printfilter']['output_format'],
                        )
                );
                $_SESSION['printfilter'] = array();
                $format = 'excel';

                include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/manage_excel_output.inc';
            }

            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.quotations.list')->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
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

    /**
     * Render excel form for quotations list
     *
     * @param array $param coid,from,to,client,status, currency
     *
     *
     */
    public function ExportExcel($param) {
        $markup = [];

        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $options = unserialize($param);
            $access = AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            $status = ['0' => (string) $this->t('open'), '1' => (string) $this->t('printed'), '2' => (string) $this->t('invoiced')];

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_quotation', 'q');
            $query->leftJoin('ek_address_book', 'b', 'q.client=b.id');
            $query->leftJoin('ek_company', 'c', 'q.head=c.id');

            $or = $query->orConditionGroup();
            $or->condition('head', $access, 'IN');
            $or->condition('allocation', $access, 'IN');

            $result = $query
                    ->fields('q')
                    ->fields('b', array('name'))
                    ->fields('c', array('name'))
                    ->condition($or)
                    ->condition('q.client', $options['client'], 'like')
                    ->condition('q.date', $options['from'], '>=')
                    ->condition('q.date', $options['to'], '<=')
                    ->condition('q.head', $options['coid'], 'like')
                    ->condition('q.currency', $options['currency'], 'LIKE')
                    ->orderBy('q.id', 'ASC')
                    ->execute();
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/excel_list_quotations.inc';
        }

        return ['#markup' => $markup];
    }

    public function DeleteQuotations(Request $request, $id) {
        //filter del
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_quotation', 'q')
                ->fields('q', ['status'])
                ->condition('id', $id, '=');
        $status = $query->execute()->fetchField();
        if ($status == '0') {
            $build['delete_quotation'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\DeleteQuotation', $id);
        } else {
            $items = [];
            $opt = ['0' => $this->t('Open'), 1 => $this->t('Printed'), 2 => $this->t('Invoiced')];
            $url = Url::fromRoute('ek_sales.purchases.list', array(), array())->toString();
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => $this->t('@document cannot be deleted.', array('@document' => $this->t('Quotation')))];
            $items['description'] = ['#markup' => $opt[$status]];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            $build['delete_quotation'] = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }

        return $build;
    }

}
