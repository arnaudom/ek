<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\InvoicesController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_sales\SalesSettings;
use Drupal\ek_finance\FinanceSettings;

/**
 * Controller routines for ek module routes.
 */
class InvoicesController extends ControllerBase {
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
     * Return invoices list view
     *
     */
    public function ListInvoices(Request $request) {

        $build['filter_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterInvoice');
        $header = array(
            'number' => array(
                'data' => $this->t('Number'),
                'class' => array(),
                'id' => 'number',
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'reference',
            ),
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'issuer',
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'id' => 'date',
            ),
            'due' => array(
                'data' => $this->t('Due'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'due',
            ),
            'value' => array(
                'data' => $this->t('Value'),
                'class' => array(),
                'id' => 'value',
            ),
            'paid' => array(
                'data' => $this->t('Payment date'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'paid',
            ),
            'status' => array(
                'data' => $this->t('Status'),
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
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_invoice', 'i');
        $or1 = $query->orConditionGroup();
        $or1->condition('head', $access, 'IN');
        $or1->condition('allocation', $access, 'IN');

        if (isset($_SESSION['ifilter']['filter']) && $_SESSION['ifilter']['filter'] == 1) {

            if ($_SESSION['ifilter']['keyword'] == '') {
                //search via options fields
                //build the export link
                $param = serialize(array(
                    'coid' => $_SESSION['ifilter']['coid'],
                    'from' => $_SESSION['ifilter']['from'],
                    'to' => $_SESSION['ifilter']['to'],
                    'client' => $_SESSION['ifilter']['client'],
                    'status' => $_SESSION['ifilter']['status']
                ));
                $excel = Url::fromRoute('ek_sales.invoices.excel', array('param' => $param))->toString();
                $build['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
                );

                $or2 = $query->orConditionGroup();
                if ($_SESSION['ifilter']['status'] == 3) {
                    //any status
                    $or2->condition('i.status', $_SESSION['ifilter']['status'], '<');
                } elseif ($_SESSION['ifilter']['status'] == 0) {
                    //unpaid
                    $or2->condition('i.status', 0, '=');
                    $or2->condition('i.status', 2, '=');
                } else {
                    //paid
                    $or2->condition('i.status', 1, '=');
                }


                $f = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                    'amount', 'amountreceived', 'pcode', 'taxvalue', 'pay_date', 'alert', 'type');
                $data = $query
                        ->fields('i', $f)
                        ->condition($or1)
                        ->condition($or2)
                        ->condition('i.client', $_SESSION['ifilter']['client'], 'like')
                        ->condition('i.date', $_SESSION['ifilter']['from'], '>=')
                        ->condition('i.date', $_SESSION['ifilter']['to'], '<=')
                        ->condition('i.head', $_SESSION['ifilter']['coid'], '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(20)
                        ->orderBy('id', 'ASC')
                        ->execute();
            } else {
                //search based on keyword
                $or2 = $query->orConditionGroup();
                $or2->condition('i.serial', '%' . $_SESSION['ifilter']['keyword'] . '%', 'like');
                $or2->condition('i.pcode', '%' . $_SESSION['ifilter']['keyword'] . '%', 'like');
                $f = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                    'amount', 'amountreceived', 'pcode', 'taxvalue', 'pay_date', 'alert', 'type');
                $data = $query
                        ->fields('i', $f)
                        ->condition($or1)
                        ->condition($or2)
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(20)
                        ->orderBy('id', 'ASC')
                        ->execute();
            }
        } else {
            //no filter

            $from = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT SQL_CACHE date from {ek_sales_invoice} order by date limit 1")
                    ->fetchField();
            $or2 = $query->orConditionGroup();
            $or2->condition('i.status', 0, '=');
            $or2->condition('i.status', 2, '=');
            $f = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                'amount', 'amountreceived', 'pcode', 'taxvalue', 'pay_date', 'alert', 'type');
            $data = $query
                    ->fields('i', $f)
                    ->condition($or1)
                    ->condition($or2)
                    ->condition('i.client', '%', 'like')
                    ->condition('i.date', $from, '>=')
                    ->condition('i.date', date('Y-m-d'), '<=')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)
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

            $settings = new SalesSettings($r->head);
            $client_name = '';
            $client = '';
            $co = '';
            $duetitle = '';
            $weight = '';
            $doctype = '';
            $total_value = 0;

            if(isset($abook[$r->client])) {
                $client_name = $abook[$r->client];
                $client = \Drupal\ek_address_book\AddressBookData::geturl($r->client, ['short' => 8]);
            }
            $co = $companies[$r->head];
            if ($r->head <> $r->allocation) {
                $for = isset($companies[$r->allocation]) ? "<br/>" . t('for') . ": " . $companies[$r->allocation] : '';
                $co = $co . $for;
            }
            
            if ($r->type == 4) {
                $doctype = 'red';
            }
            
            $number = "<a class='" . $doctype . "' title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_sales.invoices.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";

            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $reference = $client . "<div>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode, NULL, NULL, TRUE) . "</div>";
                } else {
                    $reference = $client;
                }
            } else {
                $reference = $client;
            }
            
            $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($r->date)) . "+" . $r->due . "days"));
            
            if ($r->status != '1') {
                $delta = round((strtotime(date('Y-m-d')) - strtotime($due)) / (24 * 60 * 60), 0);
                if ($delta <= $settings->get('shortdue')) {
                    $due = " <i class='fa fa-circle green' aria-hidden='true'></i> " . $due;
                    $link = 2;
                } elseif ($delta > $settings->get('shortdue') && $delta <= $settings->get('longdue')) {
                    $due = " <i class='fa fa-circle orange' aria-hidden='true'></i> " . $due;
                    $link = 2;
                } elseif ($delta > $settings->get('longdue') )  {
                    $due = " <i class='fa fa-circle red' aria-hidden='true'></i> " . $due;
                    $weight = 0;
                }
                $duetitle = t('due') . ' ' . $delta . ' ' . t('day(s)');
            }
            if ($r->type < 4) {
                $value = $r->currency . ' ' . number_format($r->amount, 2);
            } else {
                $value = $r->currency . ' (' . number_format($r->amount, 2) . ')';
            }
            $query = 'SELECT sum(total) from {ek_sales_invoice_details} WHERE serial=:s and opt=:o';
            $taxable = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $r->serial, ':o' => 1))
                    ->fetchField();
            $tax = $taxable * $r->taxvalue / 100;

            if ($tax > 0) {
                if ($r->type < 4) {
                    $value .= '<br/>' . t('tax:') . " " . $r->currency . " " . number_format($tax, 2);
                } else {
                    $value .= '<br/>' . t('tax:') . " " . $r->currency . " (" . number_format($tax, 2) . ')';
                }
            }

            if ($r->status == 0) {
                $status = t('unpaid');
                $status_class = 'red';
            }
            if ($r->status == 1) {
                $status = t('paid');
                $status_class = 'green';
            }
            if ($r->status == 2) {
                $status = t('partially paid');
                $status_class = 'red';
            }

            //build modal to display extended information
            $param = 'invoice|' . $r->id;
            $url = Url::fromRoute('ek_sales.modal_more', ['param' => $param])->toString();

            $more = '<a href="' . $url . '" '
                    . 'class="use-ajax ' . $status_class . '"  data-accepts="application/vnd.drupal-modal"  >' . $status . '</a>';


            $options[$r->id] = array(
                'number' => ['data' => ['#markup' => $number]],
                'reference' => ['data' => ['#markup' => $reference]],
                'issuer' => array('data' => ['#markup' => $co], 'title' => $r->title),
                'date' => $r->date,
                'due' => ['data' => ['#markup' => $due], 'title' => $duetitle],
                'value' => ['data' => ['#markup' => $value]],
                'paid' => ( $r->pay_date != '' ) ? $r->pay_date : '-',
                'status' => ['data' => ['#markup' => $more]],
            );

            $links = array();

            if ($r->status == 0) {

                if (\Drupal::currentUser()->hasPermission('create_invoice')) {
                    $param = 'quick_edit|' . $r->id . '|invoice';
                    $links['qedit'] = array(
                        'title' => $this->t('Quick edit'),
                        'url' => Url::fromRoute('ek_sales.modal_more', ['param' => $param]),
                        'attributes' => [
                            'class' => ['use-ajax'],
                            'data-dialog-type' => 'modal',
                            'data-dialog-options' => Json::encode(['width' => 700,]),
                        ],
                    );
                }
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_sales.invoices.edit', ['id' => $r->id]),
                );
            }

            if ($r->status != 1) {
                if ($r->type < 3) {
                    $links['pay'] = array(
                        'title' => $this->t('Receive'),
                        'url' => Url::fromRoute('ek_sales.invoices.pay', ['id' => $r->id]),
                        'weight' => $weight,
                    );
                } elseif ($r->type == 4) {
                    $links['pay'] = array(
                        'title' => $this->t('Assign credit note'),
                        'url' => Url::fromRoute('ek_sales.invoices.assign.cn', ['id' => $r->id]),
                        'weight' => $weight,
                    );
                }
            }

            if ($r->alert == 1)
                $alert = t('on');
            if ($r->alert == 0)
                $alert = t('off');

            $links['alert'] = array(
                'title' => $this->t('Set alert [@a]', array('@a' => $alert)),
                'url' => Url::fromRoute('ek_sales.invoices.alert', ['id' => $r->id]),
            );

            $links['task'] = array(
                'title' => $this->t('Edit task'),
                'url' => Url::fromRoute('ek_sales.invoices.task', ['id' => $r->id]),
            );

            if (\Drupal::currentUser()->hasPermission('print_share_invoice')) {

                $links['print'] = array(
                    'title' => $this->t('Print and share'),
                    'url' => Url::fromRoute('ek_sales.invoices.print_share', ['id' => $r->id]),
                );
                $links['excel'] = array(
                    'title' => $this->t('Excel download'),
                    'url' => Url::fromRoute('ek_sales.invoices.print_excel', ['id' => $r->id]),
                );
            }
            if (\Drupal::currentUser()->hasPermission('delete_invoice') && $r->status == 0) {

                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_sales.invoices.delete', ['id' => $r->id]),
                );
            }
            if (\Drupal::currentUser()->hasPermission('reset_pay') && $r->status == 1) {

                $links['reset'] = array(
                    'title' => $this->t('Reset'),
                    'url' => Url::fromRoute('ek_sales.reset_payment', ['doc' => 'invoice', 'id' => $r->id]),
                );
            }
            $links['clone'] = array(
                'title' => $this->t('Clone'),
                'url' => Url::fromRoute('ek_sales.invoices.clone', ['id' => $r->id]),
            );


            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        } //while
        
        $build['invoices_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'invoices_table'),
            '#empty' => $this->t('No invoice available'),
            '#attached' => array(
                'library' => array('ek_sales/ek_sales_css', 'ek_admin/ek_admin_css', 'core/drupal.ajax'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
    }

    /**
     * Render excel form for invoices list
     *
     * @param array $param coid,from,to,client,status
     *   
     *
     */
    public function ExportExcel($param) {


        $markup = array();

        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $options = unserialize($param);
            $access = AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            $status = array('0' => (string) t('unpaid'), '1' => (string) t('paid'), '2' => (string) t('partially paid'));
            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
            }


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i');
            $query->leftJoin('ek_address_book', 'b', 'i.client=b.id');
            $query->leftJoin('ek_company', 'c', 'i.head=c.id');

            $or = $query->orConditionGroup();
            if ($options['status'] == 3) {
                //any status
                $or->condition('i.status', $options['status'], '<');
            } elseif ($options['status'] == 0) {
                //unpaid
                $or->condition('i.status', 0, '=');
                $or->condition('i.status', 2, '=');
            } else {
                //paid
                $or->condition('i.status', 1, '=');
            }

            $or1 = $query->orConditionGroup();
            $or1->condition('head', $access, 'IN');
            $or1->condition('allocation', $access, 'IN');
            $result = $query
                    ->fields('i')
                    ->fields('b', array('name'))
                    ->fields('c', array('name'))
                    ->condition($or)->condition($or1)
                    ->condition('i.client', $options['client'], 'like')
                    ->condition('i.date', $options['from'], '>=')
                    ->condition('i.date', $options['to'], '<=')
                    ->condition('i.head', $options['coid'], '=')
                    ->orderBy('i.id', 'ASC')
                    ->execute();
            include_once drupal_get_path('module', 'ek_sales') . '/excel_list_invoices.inc';
        }

        return ['#markup' => $markup];
    }

    /**
     * Invoices aging report
     * @return array
     *
     */
    public function AgingInvoices() {

        $build['filter_coid'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterCompany');

        if (isset($_SESSION['coidfilter']['coid'])) {


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i');
            $fields = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                'amount', 'amountreceived', 'amountbase', 'balancebase', 'pcode', 'taxvalue');

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
            } else {
                $baseCurrency = '';
            }

            $header = array(
                'period' => array(
                    'data' => $this->t('Period'),
                    'class' => array(),
                    'id' => 'period',
                    'width' => '20%',
                ),
                'reference' => array(
                    'data' => $this->t('Reference'),
                    'class' => array(),
                    'id' => 'reference',
                    'width' => '30%',
                ),
                'value' => array(
                    'data' => $this->t('Value'),
                    'class' => array(),
                    'id' => 'value',
                    'width' => '20%',
                ),
                'total' => array(
                    'data' => $this->t('Total') . " " . $baseCurrency . " (" . $this->t('without tax') . ")",
                    'class' => array(),
                    'id' => 'total',
                ),
            );

            $or = $query->orConditionGroup();
            $or->condition('i.status', '0', '=');
            $or->condition('i.status', '2', '=');

            $data = $query
                    ->fields('i', $fields)
                    ->condition($or)
                    ->condition('i.head', $_SESSION['coidfilter']['coid'], '=')
                    ->orderBy('date', 'ASC')
                    //->orderBy('due', 'ASC')
                    ->execute();

            //store company data
            $companies = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name from {ek_company}")
                    ->fetchAllKeyed();
            //store a. book data
            $abook = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name from {ek_address_book}")
                    ->fetchAllKeyed();
            $options = [];
            $today = date("Y-m-d");
            $past_120 = 0;
            $past_90 = 0;
            $past_60 = 0;
            $past_30 = 0;
            $current = 0;
            $next_30 = 0;
            $next_60 = 0;
            $next_90 = 0;
            $next_120 = 0;



            while ($r = $data->fetchObject()) {

                $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($r->date)) . "+" . $r->due . "days"));
                $age = round((strtotime($today) - strtotime($due)) / (24 * 60 * 60), 0);

                $client_name = $abook[$r->client];
                $client = \Drupal\ek_address_book\AddressBookData::geturl($r->client);
                $co = $companies[$r->head];
                $number = "<a title='" . t('view') . "' href='"
                        . Url::fromRoute('ek_sales.invoices.print_html', ['id' => $r->id], [])->toString() . "'>"
                        . $r->serial . "</a>";

                if ($r->pcode <> 'n/a') {
                    if ($this->moduleHandler->moduleExists('ek_projects')) {
                        $reference = $client . "<br/>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode, NULL, NULL, TRUE);
                    } else {
                        $reference = $client;
                    }
                } else {
                    $reference = $client;
                }

                if ($r->status == 2) {
                    $status = "(" . t("Partially paid") . ")";
                    $value = $r->currency . ' ' . number_format($r->amount - $r->amountreceived, 2);
                    $basevalue = $r->balancebase;
                } else {
                    $status = "";
                    $value = $r->currency . ' ' . number_format($r->amount, 2);
                    $basevalue = $r->amountbase;
                }

                if ($r->taxvalue != 0) {
                    $query = 'SELECT sum(total) from {ek_sales_invoice_details} WHERE serial=:s and opt=:o';
                    $taxable = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':s' => $r->serial, ':o' => 1))
                            ->fetchField();
                    $tax = $taxable * $r->taxvalue / 100;

                    $value .= '<br/>' . t('tax:') . " " . $r->currency . " " . number_format($tax, 2);
                }

                /* AGE filter */
                if ($age > 120) {
                    if ($past_120 == 0) {
                        $options['a'][$r->id]['period'] = ['data' => t("More than 120 days aging")];
                        $past_120_id = $r->id;
                    }

                    $options['a'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['a'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($past_120 == 0) {
                        $options['a'][$r->id]['total'] = ['data' => 0];
                    }
                    $past_120++;
                    $past_120_value += $basevalue;
                }
                if ($age > 90 && $age <= 120) {

                    if ($past_90 == 0) {
                        $options['b'][$r->id]['period'] = ['data' => t("Between 90 & 120 days aging")];
                        $past_90_id = $r->id;
                    }

                    $options['b'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['b'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($past_90 == 0) {
                        $options['b'][$r->id]['total'] = ['data' => 0];
                    }
                    $past_90++;
                    $past_90_value += $basevalue;
                }

                if ($age > 60 && $age <= 90) {
                    if ($past_60 == 0) {
                        $options['c'][$r->id]['period'] = ['data' => t("Between 60 & 90 days aging")];
                        $past_60_id = $r->id;
                    }

                    $options['c'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['c'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($past_60 == 0) {
                        $options['c'][$r->id]['total'] = ['data' => 0];
                    }
                    $past_60++;
                    $past_60_value += $basevalue;
                }

                if ($age > 30 && $age <= 60) {
                    if ($past_30 == 0) {
                        $options['d'][$r->id]['period'] = ['data' => t("Between 30 & 60 days aging")];
                        $past_30_id = $r->id;
                    }

                    $options['d'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['d'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($past_30 == 0) {
                        $options['d'][$r->id]['total'] = ['data' => 0];
                    }
                    $past_30++;
                    $past_30_value += $basevalue;
                }

                if ($age >= 0 && $age <= 30) { /**/
                    if ($current == 0) {
                        $options['e'][$r->id]['period'] = ['data' => t("Between 0 & 30 days aging")];
                        $current_id = $r->id;
                    }

                    $options['e'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['e'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($current == 0) {
                        $options['e'][$r->id]['total'] = ['data' => 0];
                    }
                    $current++;
                    $current_value += $basevalue;
                }

                if ($age < 0 && $age >= -30) { /* */

                    if ($next_30 == 0) {
                        $options['f'][$r->id]['period'] = ['data' => t("Next 30 days due")];
                        $next_30_id = $r->id;
                    }

                    $options['f'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['f'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($next_30 == 0) {
                        $options['f'][$r->id]['total'] = ['data' => 0];
                    }
                    $next_30++;
                    $next_30_value += $basevalue;
                }

                if ($age < -30 && $age >= -60) {
                    if ($next_60 == 0) {
                        $options['g'][$r->id]['period'] = ['data' => t("Between 30 to 60 days due")];
                        $next_60_id = $r->id;
                    }

                    $options['g'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['g'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($next_60 == 0) {
                        $options['g'][$r->id]['total'] = ['data' => 0];
                    }
                    $next_60++;
                    $next_60_value += $basevalue;
                }

                if ($age < -60 && $age >= -90) {
                    if ($next_90 == 0) {
                        $options['h'][$r->id]['period'] = ['data' => t("Between 60 to 90 days due")];
                        $next_90_id = $r->id;
                    }

                    $options['h'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['h'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($next_90 == 0) {
                        $options['h'][$r->id]['total'] = ['data' => 0];
                    }
                    $next_90++;
                    $next_90_value += $basevalue;
                }

                if ($age < -90) {
                    if ($next_120 == 0) {
                        $options['i'][$r->id]['period'] = ['data' => t("More than 90 days due")];
                        $next_120_id = $r->id;
                    }

                    $options['i'][$r->id]['reference'] = ['data' => ['#markup' => $number . " " . $status . "<br/>" . $reference]];
                    $options['i'][$r->id]['value'] = ['data' => ['#markup' => $value]];
                    if ($next_120 == 0) {
                        $options['i'][$r->id]['total'] = ['data' => 0];
                    }
                    $next_120++;
                    $next_120_value += $basevalue;
                }

                if (isset($past_120_id)) {
                    $options['a'][$past_120_id]['period']['rowspan'] = $past_120;
                    $options['a'][$past_120_id]['total']['rowspan'] = $past_120;
                    $options['a'][$past_120_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($past_120_value, 2) . "</h2>"];
                }

                if (isset($past_90_id)) {
                    $options['b'][$past_90_id]['period']['rowspan'] = $past_90;
                    $options['b'][$past_90_id]['total']['rowspan'] = $past_90;
                    $options['b'][$past_90_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($past_90_value, 2) . "</h2>"];
                }
                if (isset($past_60_id)) {
                    $options['c'][$past_60_id]['period']['rowspan'] = $past_60;
                    $options['c'][$past_60_id]['total']['rowspan'] = $past_60;
                    $options['c'][$past_60_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($past_60_value, 2) . "</h2>"];
                }
                if (isset($past_30_id)) {
                    $options['d'][$past_30_id]['period']['rowspan'] = $past_30;
                    $options['d'][$past_30_id]['total']['rowspan'] = $past_30;
                    $options['d'][$past_30_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($past_30_value, 2) . "</h2>"];
                }
                if (isset($current_id)) {
                    $options['e'][$current_id]['period']['rowspan'] = $current;
                    $options['e'][$current_id]['total']['rowspan'] = $current;
                    $options['e'][$current_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($current_value, 2) . "</h2>"];
                }
                if (isset($next_30_id)) {
                    $options['f'][$next_30_id]['period']['rowspan'] = $next_30;
                    $options['f'][$next_30_id]['total']['rowspan'] = $next_30;
                    $options['f'][$next_30_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($next_30_value, 2) . "</h2>"];
                }
                if (isset($next_60_id)) {
                    $options['g'][$next_60_id]['period']['rowspan'] = $next_60;
                    $options['g'][$next_60_id]['total']['rowspan'] = $next_60;
                    $options['g'][$next_60_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($next_60_value, 2) . "</h2>"];
                }
                if (isset($next_90_id)) {
                    $options['h'][$next_90_id]['period']['rowspan'] = $next_90;
                    $options['h'][$next_90_id]['total']['rowspan'] = $next_90;
                    $options['h'][$next_90_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($next_90_value, 2) . "</h2>"];
                }
                if (isset($next_120_id)) {
                    $options['i'][$next_120_id]['period']['rowspan'] = $next_120;
                    $options['i'][$next_120_id]['total']['rowspan'] = $next_120;
                    $options['i'][$next_120_id]['total']['data'] = ['#markup' => "<h2>" . $baseCurrency . " " . number_format($next_120_value, 2) . "</h2>"];
                }

                $groups = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'];
                foreach ($groups as $group) {
                    if (isset($options[$group])) {
                        $build['aging_table_' . $group] = array(
                            '#type' => 'table',
                            '#header' => $header,
                            '#rows' => $options[$group],
                            '#attributes' => array('id' => 'aging_table_' . $group),
                            '#empty' => $this->t('No data available'),
                            '#attached' => array(
                                'library' => array('ek_sales/ek_sales_css', 'ek_admin/ek_admin_css', 'core/drupal.ajax'),
                            ),
                        );
                    }
                }
            }
        }

        return $build;
    }

    /**
     * @retun
     *  Invoice form 
     *
     */
    public function NewInvoices(Request $request) {
        $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Invoice');
        return $build;
    }

    /**
     * @retun
     *  Invoice form for editing existing data
     * @param $id = id of invoice
     *
     */
    public function EditInvoice(Request $request, $id) {
        //filter edit
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_invoice', 'i')
            ->fields('i', ['status'])
            ->condition('id', $id , '=');
        $status = $query->execute()->fetchField();
        if($status == '0') {    
            $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Invoice', $id);
        } else {
            $opt =['0' => t('Unpaid'),1 => t('Paid'), 2 => t('Partially paid')];
            $url = Url::fromRoute('ek_sales.invoices.list', array(), array())->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => t('@document cannot be edited.', array('@document' => t('Invoice')))];
            $items['description'] = ['#markup' => $opt[$status]];
            $items['link'] = ['#markup' => t('Go to <a href="@url" >List</a>.',['@url' => $url])];
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

    /**
     * @retun
     *  Invoice form for replicating existing data
     * @param $id = id of invoice
     *
     */
    public function CloneInvoices(Request $request, $id) {
        $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Invoice', $id, 'clone');

        return $build;
    }

    /**
     * @retun
     *  Invoice form for converting logistic DO
     * @param $id = id of DO
     *
     */
    public function DoInvoices(Request $request, $id) {
        //convert a delivery order into invoice
        //require logisticsmodule
        if ($this->moduleHandler->moduleExists('ek_logistics')) {
                $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_delivery', 'd')
                ->fields('d', ['status'])
                ->condition('id', $id , '=');
                $status = $query->execute()->fetchField();
            if($status < '2' ) {    
                $build['new_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Invoice', $id, 'delivery');
            } else {
                $opt =['0' => t('open'),1 => t('printed'), 2 => t('invoiced'),3 => t('posted')];
                $url = Url::fromRoute('ek_logistics_list_delivery', array(), array())->toString();
                $items['type'] = 'edit';
                $items['message'] = ['#markup' => t('@document cannot be converted.', array('@document' => t('Delivery')))];
                $items['description'] = ['#markup' => $opt[$status]];
                $items['link'] = ['#markup' => t('Go to <a href="@url" >List</a>.',['@url' => $url])];
                $build = [
                    '#items' => $items,
                    '#theme' => 'ek_admin_message',
                    '#attached' => array(
                        'library' => array('ek_admin/ek_admin_css'),
                    ),
                    '#cache' => ['max-age' => 0,],
                ];  
            }
        }
        return $build;
    }

    /**
     * @retun
     *  Invoice form for recording payment
     * @param $id = id of invoice
     *
     */
    public function PayInvoice(Request $request, $id) {
        $build['pay_invoice'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\ReceiveInvoice', $id);

        return $build;
    }

    /**
     * @retun
     *  Credit note assignment form for recording payment
     * @param $id = id of credit note
     *
     */
    public function AssignCreditNote($id) {
        $build['assign_credit_note'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\AssignNote', 'CT', $id);

        return $build;
    }

    /**
     * @retun
     *  Form for setting a cron alert
     * @param $id = id of invoice
     *
     */
    public function AlertInvoices(Request $request, $id) {
        $build['alert_invoice'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\AlertInvoice', $id);

        return $build;
    }

    /**
     * @retun
     *  Form for creating an invoice task
     * @param $id = id of invoice
     *
     */
    public function TaskInvoices(Request $request, $id) {
        $build['task_invoice'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\TaskInvoice', $id);
        $build['#attached'] = array(
            'library' => array('ek_sales/ek_task'),
        );
        return $build;
    }

    /**
     * @retun
     *  a list invoices tasks
     * 
     *
     */
    public function ListTaskInvoices() {

        $build['filter'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SelectTask');

        if (isset($_SESSION['taskfilter']['filter'])) {

            $stamp = date('U');
            switch ($_SESSION['taskfilter']['type']) {

                case 0:
                    $query = "SELECT * FROM {ek_sales_invoice_tasks} order by id";
                    $a = [];
                    break;
                case 1:
                    $query = "SELECT * FROM {ek_sales_invoice_tasks} WHERE completion_rate >=:v order by id ";
                    $a = [':v' => '100'];
                    break;
                case 2:
                    $query = "SELECT * FROM {ek_sales_invoice_tasks} WHERE completion_rate<:v or completion_rate = :n order by id";
                    $a = [':v' => 100, ':n' => ''];
                    break;
                case 3:
                    $query = "SELECT * FROM {ek_sales_invoice_tasks} WHERE uid=:v order by id";
                    $a = [':v' => \Drupal::currentUser()->id()];
                    break;
                case 4:
                    $query = "SELECT * FROM {ek_sales_invoice_tasks} WHERE end < :v order by id";
                    $a = [':v' => $stamp];
                    break;
            }
            $data = array();

            $result = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a);

            $notify = array(
                '0' => t('Never'),
                '5' => t('Daily'),
                '1' => t('Weekly'),
                '6' => t('Monthly'),
                '2' => t('5 days before deadline'),
                '3' => t('3 days before dealine'),
                '4' => t('1 day before dealine'),
            );




            while ($r = $result->fetchObject()) {
                if ($r->end < $stamp) {
                    $expired = t('yes');
                } else {
                    $expired = t('no');
                }
                $query = 'SELECT name FROM {users_field_data} WHERE uid=:u';
                $username = db_query($query, ['u' => $r->uid])->fetchField();

                $who = '';
                $notify_who = explode(',', $r->notify_who);

                foreach ($notify_who as $value) {
                    if ($value != '') {
                        $who .= db_query($query, ['u' => $value])->fetchField();
                        $who .= ',';
                    }
                }

                $query = "SELECT id FROM {ek_sales_invoice} WHERE serial=:s";
                $id = Database::getConnection('external_db', 'external_db')
                                ->query($query, [':s' => $r->serial])->fetchField();
                $url = Url::fromRoute('ek_sales.invoices.task', ['id' => $id])->toString();
                $link = "<a href='" . $url . "'>" . t('edit') . '</a>';

                $data['list'][] = [
                    'serial' => $r->serial,
                    'username' => $username,
                    'task' => ['data' => ['#markup' => $r->task ], 'style' => ['background-color:' . $r->color]],
                    'period' => date('Y-m-d', $r->start) . ' -> ' . date('Y-m-d', $r->end),
                    'expired' => $expired,
                    'rate' => $r->completion_rate . ' %',
                    'who' => $who,
                    'notify' => $notify[$r->notify],
                    'edit' => ['data' => ['#markup' => $link]],
                ];
            }

            $header = array(
                'reference' => array(
                    'data' => $this->t('Document'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    'id' => 'serial',
                ),
                'username' => array(
                    'data' => $this->t('Assigned'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    'id' => 'username',
                ),
                'task' => array(
                    'data' => $this->t('Task'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    'id' => 'task',
                ),
                'period' => array(
                    'data' => $this->t('From -> to'),
                    'id' => 'date',
                ),
                'expired' => array(
                    'data' => $this->t('Expired'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                    'id' => 'due',
                ),
                'completion' => array(
                    'data' => $this->t('Completion'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                    'id' => 'completion',
                ),
                'who' => array(
                    'data' => $this->t('Alert who'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                    'id' => 'who',
                ),
                'notify' => array(
                    'data' => $this->t('Alert when'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                    'id' => 'notify',
                ),
                'edit' => array(
                    'data' => $edit,
                    'id' => 'edit',
                ),
            );

            $build['invoices_tasks_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $data['list'],
                '#attributes' => array('id' => 'invoices_tasks_table'),
                '#empty' => $this->t('No task available'),
                '#attached' => array(
                    'library' => array('ek_sales/ek_sales_css'),
                ),
            );
        }

        return $build;
    }

    /**
     * @retun
     *  a form and printing and sharing pdf document
     * 
     *
     */
    public function PrintShareInvoices($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_invoice} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {

            $format = 'pdf';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'invoice', $format);

            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {

                $id = explode('_', $_SESSION['printfilter']['for_id']);

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
                    'library' => array('ek_sales/ek_sales_print', 'ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $url = Url::fromRoute('ek_sales.invoices.list')->toString();
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

    /**
     * @retun
     *  a display of invoice in pdf format
     * 
     *
     */
    public function PdfInvoices(Request $request, $param) {
        $markup = array();
        $format = 'pdf';
        include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';
        return new Response($markup);
    }

    /**
     * @retun
     *  a display of invoice in html format
     * 
     * @param 
     *  INT $id document id
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_invoice} WHERE id=:id";
        $doc_data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($doc_data->head, $access) || in_array($doc_data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'invoice', 'html');
            $document = '';

            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {
                //$_SESSION['printfilter']['filter'] = 0;
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
                
                $url_pdf = Url::fromRoute('ek_sales.invoices.print_share', ['id' => $doc_id], [])->toString();
                $url_excel = Url::fromRoute('ek_sales.invoices.print_excel', ['id' => $doc_id], [])->toString();
                $url_edit = Url::fromRoute('ek_sales.invoices.edit', ['id' => $doc_id], [])->toString();
                
                include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';
                $build['invoice'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_sales/ek_sales_html_documents_css','ek_admin/ek_admin_css'),
                    ),
                ];
            }
            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.invoices.list')->toString();
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

    /**
     * @retun  array form or denied content
     * 
     * @param  INT $id document id
     */
    public function Excel($id) {
        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_invoice} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'invoice', 'excel');

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

                include_once drupal_get_path('module', 'ek_sales') . '/manage_excel_output.inc';
            }


            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.invoices.list')->toString();
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

    /**
     * @param INT $id = id of invoice
     * @retun array form or denied message content
     *
     */
    public function DeleteInvoices(Request $request, $id) {
        //filter del
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_invoice', 'i')
            ->fields('i', ['status'])
            ->condition('id', $id , '=');
        $status = $query->execute()->fetchField();
        if($status == '0') {    
            $build['delete_invoice'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\DeleteInvoice', $id);
        } else {
            $items = [];
            $opt =['0' => t('Unpaid'),1 => t('Paid'), 2 => t('Partially paid')];
            $url = Url::fromRoute('ek_sales.invoices.list', array(), array())->toString();
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => t('@document cannot be deleted.' , array('@document' => t('Invoice') ))];
            $items['description'] = ['#markup' => $opt[$status]];
            $items['link'] = ['#markup' => t('Go to <a href="@url" >List</a>.',['@url' => $url])];
            $build['content'] = [
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
