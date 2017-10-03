<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\PurchasesController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_sales\SalesSettings;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Controller routines for ek module routes.
 */
class PurchasesController extends ControllerBase {

    /**
     * The module handler.
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
     * Purchases list with filter
     *
     */
    public function ListPurchases(Request $request) {

        $build['filter_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPurchase');
        $header = array(
            'number' => array(
                'data' => $this->t('Number'),
                'class' => array(),
                'id' => 'number',
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'purchase' => array(
                'data' => $this->t('Purchaser'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'sort' => 'desc',
            ),
            'due' => array(
                'data' => $this->t('Due'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'value' => array(
                'data' => $this->t('Value'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'paid' => array(
                'data' => $this->t('Payment date'),
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

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $query = Database::getConnection('external_db', 'external_db')->select('ek_sales_purchase', 'p');
        $or1 = db_or();
        $or1->condition('head', $access, 'IN');
        $or1->condition('allocation', $access, 'IN');

        $or2 = db_or();
        //$or2->condition('p.status', $_SESSION['pfilter']['status'], '=');
        //$or2->condition('p.status', $status2, '=');

        if (isset($_SESSION['pfilter']) && $_SESSION['pfilter']['filter'] == 1) {
            
            if ($_SESSION['pfilter']['keyword'] == '') {
                //search based on input fields
                $param = serialize(array(
                    'coid' => $_SESSION['pfilter']['coid'],
                    'from' => $_SESSION['pfilter']['from'],
                    'to' => $_SESSION['pfilter']['to'],
                    'client' => $_SESSION['pfilter']['client'],
                    'status' => $_SESSION['pfilter']['status']
                ));
                $excel = Url::fromRoute('ek_sales.purchases.excel', array('param' => $param))->toString();
                $build['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
                );

                if ($_SESSION['pfilter']['status'] == 3) {
                    //any status
                    $or2->condition('p.status', $_SESSION['pfilter']['status'], '<');
                } elseif ($_SESSION['pfilter']['status'] == 0) {
                    //unpaid
                    $or2->condition('p.status', 0, '=');
                    $or2->condition('p.status', 2, '=');
                } else {
                    //paid
                    $or2->condition('p.status', 1, '=');
                }

                $data = $query
                        ->fields('p', array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                            'amount', 'amountpaid', 'pcode', 'taxvalue', 'pdate', 'alert', 'alert_who', 'uri', 'type'))
                        ->condition($or1)
                        ->condition($or2)
                        ->condition('p.client', $_SESSION['pfilter']['client'], 'like')
                        ->condition('p.date', $_SESSION['pfilter']['from'], '>=')
                        ->condition('p.date', $_SESSION['pfilter']['to'], '<=')
                        ->condition('p.head', $_SESSION['pfilter']['coid'], '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(20)
                        ->orderBy('id', 'ASC')
                        ->execute();

                } else { 
                    //search based on keyword
                    $or2 = db_or();
                    $or2->condition('p.serial', '%' . $_SESSION['pfilter']['keyword'] . '%', 'like');
                    $or2->condition('p.pcode', '%' . $_SESSION['pfilter']['keyword'] . '%', 'like');
                    $f = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                            'amount', 'amountpaid', 'pcode', 'taxvalue', 'pdate', 'alert', 'alert_who', 'uri', 'type');
                    $data = $query
                            ->fields('p', $f)
                            ->condition($or1)
                            ->condition($or2)
                            ->extend('Drupal\Core\Database\Query\TableSortExtender')
                            ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                            ->limit(20)
                            ->orderBy('id', 'ASC')
                            ->execute();
                }
            
        } else {

            $from = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT SQL_CACHE date from {ek_sales_purchase} order by date limit 1")->fetchField();

            $param = serialize(array(
                'coid' => '%',
                'from' => $from,
                'to' => date('Y-m-d'),
                'client' => '%',
                'status' => '0'
            ));
            $excel = Url::fromRoute('ek_sales.purchases.excel', array('param' => $param))->toString();
            $build['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
            );

            $or2 = db_or();
            $or2->condition('p.status', 0, '=');
            $or2->condition('p.status', 2, '=');

            $data = $query
                    ->fields('p', array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                        'amount', 'amountpaid', 'pcode', 'taxvalue', 'pdate', 'alert', 'alert_who', 'uri', 'type'))
                    ->condition($or1)
                    ->condition($or2)
                    ->condition('p.client', '%', 'like')
                    ->condition('p.date', $from, '>=')
                    ->condition('p.date', date('Y-m-d'), '<=')
                    ->condition('p.head', '%', 'like')
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

        while ($r = $data->fetchObject()) {

            $settings = new SalesSettings($r->head);

            $query = "SELECT name from {ek_address_book} where id=:id";
            $supplier_name = $abook[$r->client];
            $supplier_part = substr($supplier_name, 0, 8);
            $link = Url::fromRoute('ek_address_book.view', array('id' => $r->client))->toString();
            $supplier = "<a title='" . t('supplier') . ": " . $supplier_name . "' href='" . $link . "'>" . $supplier_part . "</a>";
            $query = "SELECT name from {ek_company} where id=:id";
            $co = $companies[$r->head];
            if ($r->head <> $r->allocation) {
                $co = $co . "<br/>" . t('for') . ": " . $companies[$r->allocation];
            }
            $doctype = '';
            if($r->type == 4) {
                $doctype = 'green';
            }
            $number = "<a class='". $doctype ."' title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_sales.purchases.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', array(':p' => $r->pcode))
                            ->fetchField();
                    $link = Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                    $pcode_parts = explode('-', $r->pcode);
                    $count = count($pcode_parts);
                    $reference = $supplier . "<br/><a title='"
                            . t('project') . ": " . $r->pcode . "' href='" . $link . "'>" . $pcode_parts[$count - 1] . "</a>";
                } else {
                    $reference = $supplier;
                }
            } else {
                $reference = $supplier;
            }
            if ($r->uri <> '') {
                $attachment = "<a class='blue' href='" . file_create_url($r->uri) . "' target='_blank'>" . t('Attachment') . "</a>";
                $reference .= "<br/>" . $attachment;
            }

            $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($r->date)) . "+" . $r->due . "days"));
            $weight = '';
            $long = round((strtotime($due) - strtotime(date('Y-m-d'))) / (24 * 60 * 60), 0);
            if ($long > $settings->get('longdue')) {
                if ($r->status != 1) {
                    $due .= " <i class='fa fa-circle green' aria-hidden='true'></i>";
                }
                $link = 2;
            } elseif ($long >= $settings->get('shortdue') && $long <= $settings->get('longdue')) {
                if ($r->status != 1) {
                    $due .= " <i class='fa fa-circle orange' aria-hidden='true'></i>";
                }
                $link = 2;
            } else {
                if ($r->status != 1) {
                    $due .= " <i class='fa fa-circle red' aria-hidden='true'></i>";
                }
                $weight = 0;
            }
            $duetitle = t('past due') . ' ' . -1 * $long . ' ' . t('day(s)');
            if($r->type < 4) {
                $value = $r->currency . ' ' . number_format($r->amount, 2);
            } else {
                $value = $r->currency . ' (' . number_format($r->amount, 2) . ')';
            }
            
            $query = 'SELECT sum(total) from {ek_sales_purchase_details} WHERE serial=:s and opt=:o';
            $taxable = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $r->serial, ':o' => 1))
                    ->fetchField();
            $tax = $taxable * $r->taxvalue / 100;

            if ($tax > 0) {
                $value .= '<br/>' . t('tax:') . " " . $r->currency . " " . number_format($tax, 2);
            }

            if ($r->status == 0)
                $status = t('unpaid');
            if ($r->status == 1)
                $status = t('paid') . "<div class='greendot right'></div>";
            if ($r->status == 2)
                $status = t('partially paid');

            //build modal to display extended information
            $param = 'purchase|' . $r->id;
            $url = Url::fromRoute('ek_sales.modal_more', ['param' => $param])->toString();

            $more = '<a href="' . $url . '" '
                    . 'class="use-ajax red"  data-accepts="application/vnd.drupal-modal"  >' . $status . '</a>';

            $options[$r->id] = array(
                'number' => ['data' => ['#markup' => $number]],
                'reference' => ['data' => ['#markup' => $reference]],
                'purchase' => array('data' => ['#markup' => $co], 'title' => $r->title),
                'date' => $r->date,
                'due' => array('data' => ['#markup' => $due], 'title' => $duetitle),
                'value' => ['data' => ['#markup' => $value]],
                'paid' => isset($r->pdate) ? $r->pdate : '-',
                'status' => array('data' => ['#markup' => $more]),
            );

            $links = array();

            if ($r->status == 0) {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_sales.purchases.edit', ['id' => $r->id]),
                );
            }
            if ($r->status != 1) {
                if($r->type < 3) {
                    $links['pay'] = array(
                        'title' => $this->t('Pay'),
                        'url' => Url::fromRoute('ek_sales.purchases.pay', ['id' => $r->id]),
                        'weight' => $weight,
                    );
                } elseif($r->type == 4) {
                    $links['pay'] = array(
                        'title' => $this->t('Assign debit note'),
                        'url' => Url::fromRoute('ek_sales.purchases.assign.dn', ['id' => $r->id]),
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
                'url' => Url::fromRoute('ek_sales.purchases.alert', ['id' => $r->id]),
            );
            $links['task'] = array(
                'title' => $this->t('Edit task'),
                'url' => Url::fromRoute('ek_sales.purchases.task', ['id' => $r->id]),
            );

            if (\Drupal::currentUser()->hasPermission('print_share_purchase')) {

                $links['print'] = array(
                    'title' => $this->t('Print and share'),
                    'url' => Url::fromRoute('ek_sales.purchases.print_share', ['id' => $r->id]),
                    'route_name' => 'ek_sales.purchases.print_share',
                );
                $links['excel'] = array(
                    'title' => $this->t('Excel download'),
                    'url' => Url::fromRoute('ek_sales.purchases.print_excel', ['id' => $r->id]),
                );
            }
            if (\Drupal::currentUser()->hasPermission('delete_purchase') && $r->status == '0') {

                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_sales.purchases.delete', ['id' => $r->id]),
                    'route_name' => 'ek_sales.purchases.delete',
                );
            }
            if (\Drupal::currentUser()->hasPermission('reset_pay') && $r->status == 1) {

                $links['reset'] = array(
                    'title' => $this->t('Reset'),
                    'url' => Url::fromRoute('ek_sales.reset_payment', ['doc' => 'purchase', 'id' => $r->id]),
                );
            }
            $links['clone'] = array(
                'title' => $this->t('Clone'),
                'url' => Url::fromRoute('ek_sales.purchases.clone', ['id' => $r->id]),
            );

            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        } //while


        $build['purchase_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'purchases_table'),
            '#empty' => $this->t('No purchase available.'),
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
     * Purchasess aging report
     * @return array
     *
     */
    public function AgingPurchases() {

        $build['filter_coid'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterCompany');

        if (isset($_SESSION['coidfilter']['coid'])) {


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_purchase', 'p');
            $fields = array('id', 'head', 'allocation', 'serial', 'client', 'status', 'title', 'currency', 'date', 'due',
                'amount', 'amountpaid', 'amountbc', 'balancebc', 'pcode', 'taxvalue');

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

            $or = db_or();
            $or->condition('p.status', '0', '=');
            $or->condition('p.status', '2', '=');

            $data = $query
                    ->fields('p', $fields)
                    ->condition($or)
                    ->condition('p.head', $_SESSION['coidfilter']['coid'], '=')
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
                $client_part = substr($client_name, 0, 8);
                $link = Url::fromRoute('ek_address_book.view', array('id' => $r->client))->toString();
                $client = "<a title='" . t('client') . ": " . $client_name . "' href='" . $link . "'>" . $client_part . "</a>";
                $co = $companies[$r->head];
                $number = "<a title='" . t('view') . "' href='"
                        . Url::fromRoute('ek_sales.purchases.print_html', ['id' => $r->id], [])->toString() . "'>"
                        . $r->serial . "</a>";

                if ($r->pcode <> 'n/a') {
                    if ($this->moduleHandler->moduleExists('ek_projects')) {
                        $pid = Database::getConnection('external_db', 'external_db')
                                ->query('SELECT id from {ek_project} WHERE pcode=:p', array(':p' => $r->pcode))
                                ->fetchField();
                        $link = Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                        $pcode_parts = explode('-', $r->pcode);
                        $reference = $client . "<br/><a title='"
                                . t('project') . ": " . $r->pcode . "' href='" . $link . "'>" . $pcode_parts[4] . "</a>";
                    } else {
                        $reference = $client;
                    }
                } else {
                    $reference = $client;
                }
                if ($r->status == 2) {
                    $status = "(" . t("Partially paid") . ")";
                    $value = $r->currency . ' ' . number_format($r->amount - $r->amountpaid, 2);
                    $basevalue = $r->balancebc;
                } else {
                    $status = "";
                    $value = $r->currency . ' ' . number_format($r->amount, 2);
                    $basevalue = $r->amountbc;
                }

                $query = 'SELECT sum(total) from {ek_sales_purchase_details} WHERE serial=:s and opt=:o';
                $taxable = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':s' => $r->serial, ':o' => 1))
                        ->fetchField();
                $tax = $taxable * $r->taxvalue / 100;

                if ($tax > 0) {
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
     * Render excel form for purchases list
     *
     * @param array $param coid,from,to,client,status
     *   
     *
     */
    public function ExportExcel($param) {


        $markup = array();

        if (!class_exists('PHPExcel')) {
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

            if ($options['status'] == 0) {
                $status2 = 2;
            } else {
                $status2 = 1;
            }
            $or = db_or();
            $or->condition('p.status', $options['status'], '=');
            $or->condition('p.status', $status2, '=');


            $or1 = db_or();
            $or1->condition('head', $access, 'IN');
            $or1->condition('allocation', $access, 'IN');

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_purchase', 'p');
            $query->leftJoin('ek_address_book', 'b', 'p.client=b.id');
            $query->leftJoin('ek_company', 'c', 'p.head=c.id');

            $result = $query
                    ->fields('p')
                    ->fields('b', array('name'))
                    ->fields('c', array('name'))
                    ->condition($or)->condition($or1)
                    ->condition('p.client', $options['client'], 'like')
                    ->condition('p.date', $options['from'], '>=')
                    ->condition('p.date', $options['to'], '<=')
                    ->condition('p.head', $options['coid'], 'like')
                    ->orderBy('p.id', 'ASC')
                    ->execute();
            include_once drupal_get_path('module', 'ek_sales') . '/excel_list_purchases.inc';
        }

        return ['#markup' => $markup];
    }

    /**
     * @retun
     *  Purchase form 
     *
     */
    public function NewPurchases(Request $request) {

        $build['new_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\NewPurchase');

        return $build;
    }

    /**
     * @retun
     *  Purchase form for editing existing data
     * @param $id = id of purchase
     *
     */
    public function EditPurchases(Request $request, $id) {
        $build['edit_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\NewPurchase', $id);

        return $build;
    }

    /**
     * @retun
     *  Purchase form for replicating existing data
     * @param $id = id of purchase
     *
     */
    public function ClonePurchases(Request $request, $id) {
        $build['new_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\NewPurchase', $id, 'clone');

        return $build;
    }

    /**
     * @retun
     *  Purchase form for recording payment
     * @param $id = id of purchase
     *
     */
    public function PayPurchases(Request $request, $id) {
        $build['pay_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\PayPurchase', $id);

        return $build;
    }

    /**
     * @retun
     *  Debit note assignment form for recording payment
     * @param $id = id of Debdit note
     *
     */
    public function AssignDebitNote($id) {
        $build['assign_debit_note'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\AssignNote','DT', $id);

        return $build;
    }
    
    /**
     * @retun
     *  Form for setting a cron alert
     * @param $id = id of purchase
     *
     */
    public function alertPurchases(Request $request, $id) {
        $build['alert_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\AlertPurchase', $id);

        return $build;
    }

    /**
     * @retun
     *  Form for creating a pourchase task
     * @param $id = id of purchase
     *
     */
    public function TaskPurchases(Request $request, $id) {
        $build['task_purchase'] = $this->formBuilder
                ->getForm('Drupal\ek_sales\Form\TaskPurchase', $id);
        $build['#attached'] = array(
            'library' => array('ek_sales/ek_task'),
        );
        return $build;
    }

    /**
     * @retun
     *  a list purchases tasks
     * 
     *
     */
    public function ListTaskPurchases() {

        $build['filter'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SelectTask');

        if (isset($_SESSION['taskfilter']) && $_SESSION['taskfilter']['filter'] == 1) {

            $stamp = date('U');
            switch ($_SESSION['taskfilter']['type']) {

                case 0:
                    $query = "SELECT * FROM {ek_sales_purchase_tasks} order by id";
                    $a = [];
                    break;
                case 1:
                    $query = "SELECT * FROM {ek_sales_purchase_tasks} WHERE completion_rate >=:v order by id ";
                    $a = [':v' => '100'];
                    break;
                case 2:
                    $query = "SELECT * FROM {ek_sales_purchase_tasks} WHERE completion_rate<:v or completion_rate = :n order by id";
                    $a = [':v' => 100, ':n' => ''];
                    break;
                case 3:
                    $query = "SELECT * FROM {ek_sales_purchase_tasks} WHERE uid=:v order by id";
                    $a = [':v' => \Drupal::currentUser()->id()];
                    break;
                case 4:
                    $query = "SELECT * FROM {ek_sales_purchase_tasks} WHERE end < :v order by id";
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

                $query = "SELECT id FROM {ek_sales_purchase} WHERE serial=:s";
                $id = Database::getConnection('external_db', 'external_db')
                                ->query($query, [':s' => $r->serial])->fetchField();
                $url = Url::fromRoute('ek_sales.purchases.task', ['id' => $id])->toString();
                $link = "<a href='" . $url . "'>" . t('edit') . '</a>';

                $data['list'][] = [
                    'serial' => $r->serial,
                    'username' => $username,
                    'task' => $r->task,
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

            $build['purchases_tasks_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $data['list'],
                '#attributes' => array('id' => 'purchases_tasks_table'),
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
    public function PrintSharePurchases($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_purchase} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {

            $format = 'pdf';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'purchase', $format);

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

                $path = $GLOBALS['base_url'] . "/purchases/print/pdf/" . $param;

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
            $url = Url::fromRoute('ek_sales.purchases.list')->toString();
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];
        }
    }

    /**
     * @retun
     *  a display of purchase in pdf format
     * 
     *
     */
    public function pdfPurchases(Request $request, $param) {

        $markup = array();
        $format = 'pdf';
        include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';
        return new Response($markup);
    }

    /**
     * @retun
     *  a display of puchasee in html format
     * 
     * @param 
     *  INT $id document id
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_purchase} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'purchase', 'html');
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
                include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';

                $build['excel'] = [
                    '#markup' => "<a class='button button-action' href='"
                    . Url::fromRoute('ek_sales.purchases.print_excel', ['id' => $doc_id], [])->toString() . "' >"
                    . t('Excel') . "</a>"
                    . "<a class='button button-action' href='"
                    . Url::fromRoute('ek_sales.purchases.print_share', ['id' => $doc_id], [])->toString() . "' >"
                    . t('Pdf') . "</a>"
                        ,
                ];

                $build['purchase'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_sales/ek_sales_html_documents_css'),
                        'placeholders' => $css,
                    ),
                ];
            }
            return array($build);
        } else {
            $url = Url::fromRoute('ek_sales.purchases.list')->toString();
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];
        }
    }

    /**
     * @retun
     *  a form to download purchase in excel format
     * 
     * @param 
     *  INT $id document id
     */
    public function Excel($id) {
        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_sales_purchase} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\FilterPrint', $id, 'purchase', 'excel');

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
            $url = Url::fromRoute('ek_sales.purchases.list')->toString();
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];
        }
    }

    /**
     * @retun
     *  a from to delete purchase
     * @param $id = id of purchase
     *
     */
    public function DeletePurchases(Request $request, $id) {

        $build['delete_purchase'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\DeletePurchase', $id);

        return $build;
    }

//end class  
}
