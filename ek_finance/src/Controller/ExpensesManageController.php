<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\ExpensesManageController
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;

/**
 * Controller routines for ek module routes.
 */
class ExpensesManageController extends ControllerBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

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
                $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a ExpensesManageController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * 
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  record an expense 
     *  @return array
     *
     */
    public function recordexpenses(Request $request) {

        $build['new_expense'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\RecordExpense');

        return $build;
    }

    /**
     *  clone an expense from existing record 
     *  @return array
     */
    public function cloneexpenses(Request $request, $id) {

        $build['new_expense'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\RecordExpense', $id, 'clone');

        return $build;
    }

    /**
     * get the expenses data
     * @param array $session_filter 
     *  filter options defined in filter form
     * @param string $sort
     *  field to sort query result
     * @param string $order
     *  field to order query result
     * @param array $setting
     *  finance settings
     */
    private function pullExpensesData($session_filter = NULL, $sort = NULL, $order = NULL, $settings) {

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $chart = $settings->get('chart');

        if (!isset($session_filter) || empty($session_filter)) {

            //no filter is set
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');

            if ($settings->get('listPurchases') == 1 && $this->moduleHandler->moduleExists('ek_projects')) {
                //query data with purchases
                $query->leftjoin('ek_expenses', 'e', 'e.id=j.reference');
                $query->leftjoin('ek_sales_purchase', 'p', 'p.id=j.reference');
                $or = db_or();
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['cos'] . '%', 'like');
                $or2 = db_or();
                $or2->condition('j.source', 'expense%', 'like');
                $or2->condition('j.source', 'purchase%', 'like');

                $data = $query
                        ->fields('j', array('id', 'aid', 'date', 'value', 'source', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'))
                        ->fields('p', array('id', 'taxvalue', 'title', 'pcode', 'client', 'uri', 'status'))
                        ->condition($or)
                        ->condition($or2)
                        ->condition('j.date', date('Y-m') . "-01", '>=')
                        ->condition('j.date', date('Y-m-d'), '<=')
                        ->condition('coid', $access, 'IN')
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(1000)->orderBy($order, $sort)
                        ->execute();
            } else {
                //query data without purchases
                $query->join('ek_expenses', 'e', 'e.id=j.reference');
                $or = db_or();
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['cos'] . '%', 'like');

                $data = $query
                        ->fields('j', array('id', 'aid', 'date', 'value', 'source', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'))
                        ->condition($or)
                        ->condition('j.source', 'expense%', 'like')
                        ->condition('date', date('Y-m') . "-01", '>=')
                        ->condition('date', date('Y-m-d'), '<=')
                        ->condition('coid', $access, 'IN')
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(1000)->orderBy($order, $sort)
                        ->execute();
            }
        } elseif (isset($session_filter['keyword']) && $session_filter['keyword'] != '' && $session_filter['keyword'] != '%') {

            //filter by keyword
            $keyword1 = '%' . trim(Xss::filter($session_filter['keyword'])) . '%';

            if ($settings->get('listPurchases') == 1 && $this->moduleHandler->moduleExists('ek_projects')) {
                //query data by keyword with purchases
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');

                $query->leftjoin('ek_expenses', 'e', 'e.id=j.reference');
                $query->leftjoin('ek_sales_purchase', 'p', 'p.id=j.reference');
                $or = db_or();
                $or->condition('e.id', $keyword1, 'like');
                $or->condition('e.comment', $keyword1, 'like');
                $or->condition('p.id', $keyword1, 'like');
                $or->condition('p.title', $keyword1, 'like');
                $or2 = db_or();
                $or2->condition('j.source', 'expense%', 'like');
                $or2->condition('j.source', 'purchase%', 'like');

                $data = $query
                        ->fields('j', array('id', 'aid', 'date', 'value', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'))
                        ->fields('p', array('id', 'taxvalue', 'title', 'pcode', 'client', 'uri', 'status'))
                        ->condition('coid', $access, 'IN')
                        ->condition($or)
                        ->condition($or2)
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($session_filter['rows'])->orderBy($order, $sort)
                        ->execute();
            } else {

                //query data by keyword without purchases
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                $query->join('ek_expenses', 'e', 'e.id=j.reference');
                $or = db_or();
                $or->condition('e.id', $keyword1, 'like');
                $or->condition('e.comment', $keyword1, 'like');


                $data = $query
                        ->fields('j', array('id', 'aid', 'date', 'value', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'))
                        ->condition('coid', $access, 'IN')
                        ->condition($or)
                        ->condition('j.source', 'expense%', 'like')
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($session_filter['rows'])->orderBy($order, $sort)
                        ->execute();
            }
        } else {

            //filter by tags
            if ($settings->get('listPurchases') == 1 && $this->moduleHandler->moduleExists('ek_projects')) {

                //query data by tag with purchases
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                $query->leftjoin('ek_expenses', 'e', 'e.id=j.reference');
                $query->leftjoin('ek_sales_purchase', 'p', 'p.id=j.reference');
                $query->fields('j', array('id', 'aid', 'date', 'value', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'))
                        ->fields('p', array('id', 'taxvalue', 'title', 'pcode', 'client', 'uri', 'status'));
                $or = db_or();
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['cos'] . '%', 'like');
                $or1 = db_or();
                $or1->condition('e.clientname', $session_filter['client'], 'like');
                $or1->condition('p.client', $session_filter['client'], 'like');
                $or3 = db_or();
                $or3->condition('e.pcode', $session_filter['pcode'], 'like');
                $or3->condition('p.pcode', $session_filter['pcode'], 'like');
                $or4 = db_or();
                $or4->condition('j.source', 'expense%', 'like');
                $or4->condition('j.source', 'purchase%', 'like');

                if ($session_filter['supplier'] != '%') {
                    $query->condition('e.suppliername', $session_filter['supplier'], 'like');
                }

                $query->condition('aid', $session_filter['aid'], 'like')
                        ->condition($or)
                        ->condition($or1)
                        ->condition($or3)
                        ->condition('j.date', $session_filter['from'], '>=')
                        ->condition('j.date', $session_filter['to'], '<=')
                        ->condition('coid', $session_filter['coid'], '=')
                        ->condition($or4)
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($session_filter['rows'])
                        ->orderBy($order, $sort);

                $data = $query->execute();
            }//by tag with purchase               
            else {
                //query data by tag without purchases
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                $query->join('ek_expenses', 'e', 'e.id=j.reference');
                $query->fields('j', array('id', 'aid', 'date', 'value', 'exchange', 'currency', 'reconcile', 'reference', 'coid', 'source'))
                        ->fields('e', array('id', 'tax', 'cash', 'comment', 'pcode', 'clientname', 'suppliername', 'attachment'));

                if ($session_filter['aid'] == '%') {
                    $or = db_or();
                    $or->condition('aid', $chart['expenses'] . '%', 'like');
                    $or->condition('aid', $chart['cos'] . '%', 'like');
                    $query->condition($or);
                } else {
                    $query->condition('aid', $session_filter['aid'], 'like');
                }
                $or = db_or();
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['cos'] . '%', 'like');

                $query->condition('e.clientname', $session_filter['client'], 'like')
                        ->condition('e.suppliername', $session_filter['supplier'], 'like')
                        ->condition('pcode', $session_filter['pcode'], 'like')
                        ->condition('date', $session_filter['from'], '>=')
                        ->condition('date', $session_filter['to'], '<=')
                        ->condition('coid', $session_filter['coid'], '=')
                        ->condition($or)
                        ->condition('j.source', 'expense%', 'like')
                        ->condition('j.type', 'debit', '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($session_filter['rows'])
                        ->orderBy($order, $sort);

                $data = $query->execute();
            }//default by tag without purchase
        } //filter by tag      

        return $data;
    }

    /**
     *  generate list of fitered expenses
     *  linked to journal entries
     * 
     *  @return array 
     *  rendered html
     */
    public function listexpenses(Request $request) {

        $build['filter_expenses'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterExpenses');
        $sort = 'asc';
        $order = 'j.id';
        if ($request->query->get('sort')) {
            $sort = $request->query->get('sort');
            $order = $request->query->get('order');
        }

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $chart = $settings->get('chart');

        $header = array(
            'id' => array(
                'data' => $this->t('Id'),
                'field' => 'e.id',
            //'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'type' => array(
                'data' => $this->t('Class'),
                //'field' => 'j.aid',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'company' => array(
                'data' => $this->t('Company'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'j.date',
                'sort' => 'asc',
            ),
            'value' => array(
                'data' => $this->t('Value'),
            //'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'basecurrency' => array(
                'data' => $this->t('in base currency') . " " . $baseCurrency,
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'receipt' => array(
                'data' => $this->t('Attachment'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => $this->t('Operations'),
        );

        $options = array();

        if (isset($_SESSION['efilter'])) {

            $data = $this->pullExpensesData($_SESSION['efilter'], $sort, $order, $settings);
            $chartList = Aidlist::chartList();

            $total = 0;
            $query = "SELECT id,name from {ek_company}";
            $company_array = Database::getConnection('external_db', 'external_db')
                    ->query($query)
                    ->fetchAllKeyed();
            $i = 0;
            $filteredIds = [];
            
            while ($r = $data->fetchObject()) {
                $links = array();
                $i++;
                $CompanySettings = new CompanySettings($r->coid);
                $aid = $r->aid;
                $aname = $chartList[$r->coid][$r->aid];
                $date = $r->date;
                $value = $r->value;
                $currency = $r->currency;
                $ecurrency = '';
                $edit = '';

                //type expense
                if (strpos($r->source, 'xpense')) {
                    if ($r->tax > 0) {
                        //if tax is collected, retrieve the tax collection account per company
                        //get the total tax record from journal
                        $stax_deduct_aid = $CompanySettings->get('stax_deduct_aid');
                        $query = "SELECT id,value,exchange FROM {ek_journal} WHERE reference=:r AND coid=:c AND aid=:a AND type=:t order by id";
                        $a = [':r' => $r->e_id, ':c' => $r->coid, ':a' => $stax_deduct_aid, ':t' => 'debit'];

                        $stax = Database::getConnection('external_db', 'external_db')
                                ->query($query, $a);

                        $stax_deduct_aid_value = array();
                        While ($st = $stax->fetchObject()) {
                            $stax_deduct_aid_value[$st->exchange] = $st->value;
                        }
                    } else {
                        $stax_deduct_aid_value = 0;
                    }

                    if ($r->currency <> $baseCurrency) {
                        $r = $data->fetchObject();
                        $ecurrency = $r->currency;
                        $evalue = ($value + $r->value);
                    } else {
                        $ecurrency = $currency;
                        $evalue = $value;
                    }

                    $total = $total + $evalue;

                    if ($r->tax > 0) {
                        $value = number_format($value, 2) . " " . $currency . "<br/>(" . t('Tax') . ' ' . number_format($stax_deduct_aid_value[0], 2) . ")";
                        $evalue = number_format($evalue, 2) . " " . $baseCurrency . "<br/>(" . t('Tax') . ' ' . number_format($stax_deduct_aid_value[0] + $stax_deduct_aid_value[1], 2) . ')';
                    } else {
                        $value = number_format($value, 2) . " " . $currency;
                        $evalue = number_format($evalue, 2) . " " . $baseCurrency;
                    }
                } elseif ($r->source == 'purchase') {

                    if ($r->currency <> $baseCurrency) {
                        $r = $data->fetchObject();
                        $ecurrency = $r->currency;
                        $evalue = ($value + $r->value);
                    } else {
                        $ecurrency = $currency;
                        $evalue = $value;
                    }

                    $total = $total + $evalue;

                    if ($r->taxvalue > 0) {
                        $value = number_format($value, 2) . " " . $currency . "<br/>(" . t('Tax') . ' ' . number_format($value * $r->taxvalue / 100, 2) . ")";
                        $evalue = number_format($evalue, 2) . " " . $baseCurrency . "<br/>(" . t('Tax') . ' ' . number_format($evalue * $r->taxvalue / 100, 2) . ')';
                    } else {
                        $value = number_format($value, 2) . " " . $currency;
                        $evalue = number_format($evalue, 2) . " " . $baseCurrency;
                    }
                }

                //type expense
                if (strpos($r->source, 'xpense')) {
                    $ref = '';
                    $receipt = '';
                    $clientname = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $r->clientname))
                            ->fetchField();
                    $suppliername = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $r->suppliername))
                            ->fetchField();

                    if ($r->clientname != 0) {
                        $url = Url::fromRoute('ek_address_book.view', array('id' => $r->clientname), array())->toString();
                        $ref = $ref . "<a href='" . $url . "' title='" . t('client') . ": " . $clientname . "'>" .
                                substr($clientname, 0, 6) . '</a><br/>';
                    }
                    if ($r->suppliername != 0) {
                        $url = Url::fromRoute('ek_address_book.view', array('id' => $r->suppliername), array())->toString();
                        $ref = $ref . "<a href='" . $url . "'  title='" . t('supplier') . ": " . $suppliername . "'>" .
                                substr($suppliername, 0, 6) . '</a><br/>';
                    }
                    if ($r->pcode <> 'n/a') {
                        if ($this->moduleHandler->moduleExists('ek_projects')) {
                            $pcode = str_replace('/', '-', $r->pcode);
                            $query = "SELECT id from {ek_project} WHERE pcode=:p";
                            $pid = Database::getConnection('external_db', 'external_db')
                                    ->query($query, array(':p' => $pcode))
                                    ->fetchField();
                            $pparts = array_reverse(explode('-', $pcode));
                            $url = Url::fromRoute('ek_projects_view', array('id' => $pid), array())->toString();

                            $ref .= "<a title='" . t('project') . ' ' . $pcode . "' href='" . $url . "'>" . $pparts[0] . "</a>";
                        }
                    }

                    if ($r->attachment <> '') {
                        $receipt = "<a href='" . file_create_url($r->attachment) . "' target='_blank'>" . t('open') . "</a>";
                        $edit = 'upload-' . $r->e_id . '-expense';
                    } else {
                        $param = 'upload-' . $r->e_id . '-expense';

                        $modal_route = Url::fromRoute('ek_finance.manage.modal_expense', ['param' => $param])->toString();
                        $receipt = t('<a href="@url" class="@c"  data-accepts=@a  >upload</a>', array('@url' => $modal_route, '@c' => 'use-ajax red', '@a' => "application/vnd.drupal-modal",));
                    }

                    //voucher
                    $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 1, 'id' => $r->reference])->toString();
                    $voucher = '<a href="' . $url . '" target="_blank"  title="' . t('voucher')
                            . ' - ' . $r->e_id . ' ' . $r->comment . '">' . $r->e_id . '</a>';
                    //save array for print range
                    array_push($filteredIds, $r->e_id);
                    
                } elseif ($r->source == 'purchase') {
                    $ref = '';
                    $receipt = '';

                    if ($r->client != '0') {
                        $clientname = Database::getConnection('external_db', 'external_db')
                                ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $r->client))
                                ->fetchField();

                        $url = Url::fromRoute('ek_address_book.view', array('id' => $r->client), array())->toString();
                        $ref = $ref . "<a href='" . $url . "' title='" . t('client') . ": " . $clientname . "'>" .
                                substr($clientname, 0, 6) . '</a><br/>';
                    }
                    if ($r->p_pcode <> 'n/a') {
                        if ($this->moduleHandler->moduleExists('ek_projects')) {
                            $pcode = str_replace('/', '-', $r->p_pcode);
                            $query = "SELECT id from {ek_project} WHERE pcode=:p";
                            $pid = Database::getConnection('external_db', 'external_db')
                                    ->query($query, array(':p' => $pcode))
                                    ->fetchField();
                            $pparts = array_reverse(explode('-', $pcode));
                            $url = Url::fromRoute('ek_projects_view', array('id' => $pid), array())->toString();

                            $ref .= "<a title='" . t('project') . ' ' . $pcode . "' href='" . $url . "'>" . $pparts[0] . "</a>";
                        }
                    }
                    if ($r->uri != '') {
                        $receipt = "<a href='" . file_create_url($r->uri) . "' target='_blank'>" . t('open') . "</a>";
                    }
                    //voucher
                    $url = Url::fromRoute('ek_sales.purchases.print_share', ['id' => $r->p_id])->toString();
                    $voucher = '<a href="' . $url . '" target="_blank"  title="' . t('purchase')
                            . ' - ' . $r->p_id . ' ' . $r->title . '">' . $r->p_id . '</a>';
                    
                }

                $options[$i] = array(
                    'id' => ['data' => ['#markup' => $voucher]],
                    'type' => array('data' => $aid . " " . $aname),
                    'reference' => ['data' => ['#markup' => $ref]],
                    'company' => $company_array[$r->coid],
                    'date' => $date,
                    'value' => ['data' => ['#markup' => $value]],
                    'basecurrency' => ['data' => ['#markup' => $evalue]],
                    'receipt' => ['data' => ['#markup' => $receipt]],
                );
                //to prevent edition of expense with reconciled
                //journal entry, sum all reco flags for common source date and ref.
                //if not equal to 0, at least 1 entry is reconciled 
                $query = Database::getConnection('external_db', 'external_db')
                          ->select('ek_journal');
                $query->addExpression('SUM(reconcile)', 'reconcile');
                $query->condition('coid', $r->coid, '=');
                $query->condition('date', $r->date, '=');
                $query->condition('source', $r->source, '=');
                $query->condition('reference', $r->reference, '=');
                $reconcile_flag = $query->execute()->fetchObject()->reconcile;
                
                if (strpos($r->source, 'xpense')) {
                    if ($reconcile_flag == 0) {
                        $links['edit'] = array(
                            'title' => $this->t('Edit'),
                            'url' => Url::fromRoute('ek_finance.manage.edit_expense', ['id' => $r->reference]),
                        );

                        $links['del'] = array(
                            'title' => $this->t('Delete'),
                            'url' => Url::fromRoute('ek_finance.manage.delete_expense', ['id' => $r->reference]),
                        );
                    }

                    $links['clone'] = array(
                        'title' => $this->t('Clone'),
                        'url' => Url::fromRoute('ek_finance.manage.clone_expense', ['id' => $r->reference]),
                    );
                } elseif ($r->source == 'purchase') {

                    if ($r->status == 0) {
                        $links['edit'] = array(
                            'title' => $this->t('Edit'),
                            'url' => Url::fromRoute('ek_sales.purchases.edit', ['id' => $r->p_id]),
                        );
                    }
                    $links['clone'] = array(
                        'title' => $this->t('Clone'),
                        'url' => Url::fromRoute('ek_sales.purchases.clone', ['id' => $r->p_id]),
                    );
                }




                $options[$i]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => $links,
                );
            } //while

            if ($i > 0) {
                $total = '<h4>' . number_format($total, 2) . " " . $baseCurrency . '</h4>';
                $i++;
                $options[$i] = array(
                    'id' => '',
                    'type' => t('Total'),
                    'reference' => '',
                    'company' => '',
                    'date' => '',
                    'value' => '',
                    'basecurrency' => array('data' => ['#markup' => $total]),
                    'receipt' => '',
                    'operations' => '',
                );

                /* */
                if (isset($_SESSION['efilter']['filter']) && $_SESSION['efilter']['filter'] == 1) {


                    $param = serialize(array(
                        'keyword' => $_SESSION['efilter']['keyword'],
                        'coid' => $_SESSION['efilter']['coid'],
                        'aid' => $_SESSION['efilter']['aid'],
                        'client' => $_SESSION['efilter']['client'],
                        'supplier' => $_SESSION['efilter']['supplier'],
                        'pcode' => $_SESSION['efilter']['pcode'],
                        'from' => $_SESSION['efilter']['from'],
                        'to' => $_SESSION['efilter']['to'],
                        'rows' => $_SESSION['efilter']['rows'],
                            )
                    );
                } else {
                    $param = 0;
                }
                /**/

                $excel = Url::fromRoute('ek_finance.manage.excel_expense', array('param' => $param), array())->toString();
                $build['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
                );
            }
        }

        if(count($filteredIds) > 1 && count($filteredIds) < 26) {

            $s = serialize($filteredIds);
            $url = Url::fromRoute('ek_finance_voucher.pdf', array('type' => 1, 'id' => $s), array())->toString();
            $build['print_range'] = array(
                    '#markup' => "<p><a href='" . $url . "' target='_blank'>" . t('Print range') . "</a></p>",
                );
        }
        
        $build['expenses_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'expenses_table'),
            '#empty' => $this->t('No expense available.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
    }

    /**
     *  generate list of fitered expenses
     *  from expenses table
     * 
     *  @return array
     *  rendered html table
     */
    public function listexpensesraw(Request $request) {

        $build['filter_expenses'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterExpenses');

        $build['alert'] = ['#markup' => "<div class='messages messages--warning'>" . t('This is not an extract built from journal records. Data may not be accurate.') . "</div>"];


        $sort = 'asc';
        $order = 'e.id';
        if ($request->query->get('sort')) {
            $sort = $request->query->get('sort');
            $order = $request->query->get('order');
        }

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $chart = $settings->get('chart');

        $header = array(
            'id' => array(
                'data' => $this->t('Id'),
                'field' => 'e.id',
            //'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'type' => array(
                'data' => $this->t('Class'),
                //'field' => 'j.aid',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'company' => array(
                'data' => $this->t('Company'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'j.date',
                'sort' => 'asc',
            ),
            'value' => array(
                'data' => $this->t('Value'),
            //'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'basecurrency' => array(
                'data' => $this->t('in base currency') . " " . $baseCurrency,
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'receipt' => array(
                'data' => $this->t('Attachment'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => $this->t('Operations'),
        );

        $options = array();

        if (isset($_SESSION['efilter'])) {

            $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();

            if (isset($_SESSION['efilter']['keyword']) && $_SESSION['efilter']['keyword'] != '' && $_SESSION['efilter']['keyword'] != '%') {

                //filter by keyword
                $keyword1 = '%' . trim(Xss::filter($_SESSION['efilter']['keyword'])) . '%';

                //query data by keyword without purchases
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 'e');

                $or = db_or();
                $or->condition('e.id', $keyword1, 'like');
                $or->condition('e.comment', $keyword1, 'like');


                $data = $query
                        ->fields('e')
                        ->condition('company', $access, 'IN')
                        ->condition($or)
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($_SESSION['efilter']['rows'])->orderBy($order, $sort)
                        ->execute();
            } else {

                //filter by tags
                //query data by tag 
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 'e');

                $query->fields('e');

                if ($_SESSION['efilter']['aid'] == '%') {
                    $or = db_or();
                    $or->condition('type', $chart['expenses'] . '%', 'like');
                    $or->condition('type', $chart['cos'] . '%', 'like');
                    $query->condition($or);
                } else {
                    $query->condition('type', $_SESSION['efilter']['aid'], 'like');
                }

                $query->condition('e.clientname', $_SESSION['efilter']['client'], 'like')
                        ->condition('e.suppliername', $_SESSION['efilter']['supplier'], 'like')
                        ->condition('pcode', $_SESSION['efilter']['pcode'], 'like')
                        ->condition('pdate', $_SESSION['efilter']['from'], '>=')
                        ->condition('pdate', $_SESSION['efilter']['to'], '<=')
                        ->condition('company', $_SESSION['efilter']['coid'], '=')
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit($_SESSION['efilter']['rows'])
                        ->orderBy($order, $sort);

                $data = $query->execute();
            }

            $chartList = Aidlist::chartList();

            $total = 0;
            $query = "SELECT id,name from {ek_company}";
            $company_array = Database::getConnection('external_db', 'external_db')
                    ->query($query)
                    ->fetchAllKeyed();
            $i = 0;

            while ($r = $data->fetchObject()) {
                $links = array();
                $i++;
                $CompanySettings = new CompanySettings($r->company);
                $aid = $r->type;
                $aname = $chartList[$r->company][$r->type];
                $date = $r->pdate;
                $currency = $r->currency;
                $ecurrency = '';
                $edit = '';
                $total = $total + $r->amount;

                if ($r->tax > 0) {
                    $value = number_format($r->localcurrency, 2) . " " . $currency
                            . "<br/>(" . t('Tax') . ' ' . number_format(($r->localcurrency * $r->tax / 100), 2) . ")";
                    $evalue = number_format($r->amount, 2) . " " . $baseCurrency
                            . "<br/>(" . t('Tax') . ' ' . number_format(($r->amount * $r->tax / 100), 2) . ')';
                } else {
                    $value = number_format($r->localcurrency, 2) . " " . $currency;
                    $evalue = number_format($r->amount, 2) . " " . $baseCurrency;
                }

                $ref = '';
                $receipt = '';
                $clientname = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $r->clientname))
                        ->fetchField();
                $suppliername = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $r->suppliername))
                        ->fetchField();

                if ($r->clientname != 0) {
                    $url = Url::fromRoute('ek_address_book.view', array('id' => $r->clientname), array())->toString();
                    $ref = $ref . "<a href='" . $url . "' title='" . t('client') . ": " . $clientname . "'>" .
                            substr($clientname, 0, 6) . '</a><br/>';
                }
                if ($r->suppliername != 0) {
                    $url = Url::fromRoute('ek_address_book.view', array('id' => $r->suppliername), array())->toString();
                    $ref = $ref . "<a href='" . $url . "'  title='" . t('supplier') . ": " . $suppliername . "'>" .
                            substr($suppliername, 0, 6) . '</a><br/>';
                }
                if ($r->pcode <> 'n/a') {
                    if ($this->moduleHandler->moduleExists('ek_projects')) {
                        $pcode = str_replace('/', '-', $r->pcode);
                        $query = "SELECT id from {ek_project} WHERE pcode=:p";
                        $pid = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':p' => $pcode))
                                ->fetchField();
                        $pparts = array_reverse(explode('-', $pcode));
                        $url = Url::fromRoute('ek_projects_view', array('id' => $pid), array())->toString();

                        $ref .= "<a title='" . t('project') . ' ' . $pcode . "' href='" . $url . "'>" . $pparts[0] . "</a>";
                    }
                }

                if ($r->attachment <> '') {
                    $receipt = "<a href='" . file_create_url($r->attachment) . "' target='_blank'>" . t('open') . "</a>";
                    $edit = 'upload-' . $r->e_id . '-expense';
                } else {
                    $param = 'upload-' . $r->e_id . '-expense';

                    $modal_route = Url::fromRoute('ek_finance.manage.modal_expense', ['param' => $param])->toString();
                    $receipt = t('<a href="@url" class="@c"  data-accepts=@a  >upload</a>', array('@url' => $modal_route, '@c' => 'use-ajax red', '@a' => "application/vnd.drupal-modal",));
                }

                //voucher
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 1, 'id' => $r->id])->toString();
                $voucher = '<a href="' . $url . '" target="_blank"  title="' . t('voucher')
                        . ' - ' . $r->id . ' ' . $r->comment . '">' . $r->id . '</a>';


                $options[$i] = array(
                    'id' => ['data' => ['#markup' => $voucher]],
                    'type' => array('data' => $aid . " " . $aname),
                    'reference' => ['data' => ['#markup' => $ref]],
                    'company' => $company_array[$r->company],
                    'date' => $date,
                    'value' => ['data' => ['#markup' => $value]],
                    'basecurrency' => ['data' => ['#markup' => $evalue]],
                    'receipt' => ['data' => ['#markup' => $receipt]],
                );

                $options[$i]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => [],
                );
            } //while

            if ($i > 0) {
                $total = '<h4>' . number_format($total, 2) . " " . $baseCurrency . '</h4>';
                $i++;
                $options[$i] = array(
                    'id' => '',
                    'type' => t('Total'),
                    'reference' => '',
                    'company' => '',
                    'date' => '',
                    'value' => '',
                    'basecurrency' => array('data' => ['#markup' => $total]),
                    'receipt' => '',
                    'operations' => '',
                );
            }
        }
        
        $build['expenses_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'expenses_table'),
            '#empty' => $this->t('No expense available.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
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
     * @param string $param
     *  format: [action]-[table id]-[type]
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = FALSE, $param = NULL) {

        $param = explode('-', $param);
        $content = '';
        switch ($param[0]) {

            case 'upload':
                $id = $param[1] . '-' . $param[2];
                $content = $this->formBuilder->getForm('Drupal\ek_finance\Form\UploadForm', $id);
                break;
        }

        $response = new AjaxResponse();
        $title = $this->t($param[0]);
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $html));
        }
        return $response;
    }

    /**
     *  Edit existing expense entry
     * 
     *  @param int $id
     *      table entry id
     * 
     *  @return array
     *      form
     */
    public function editexpenses(Request $request, $id) {

        $build['edit_expense'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\RecordExpense', $id);

        return $build;
    }

    /* Export list into excel format
     * 
     * @param array $param
     * optional filters values
     * 'keyword','int coid','int account aid','int client id',
     * 'int supplier id',' string project pcode,
     * 'string date from',' string date to'
     * 
     * @return PhpExcel Object
     */

    public function excelexpenses(Request $request, $param = NULL) {

        $markup = array();
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
            $query = "SELECT id, name from {ek_company} ";
            $company_array = Database::getConnection('external_db', 'external_db')
                            ->query($query)->fetchAllKeyed();
            $param = unserialize($param);
            $chartList = Aidlist::chartList();
            $result = $this->pullExpensesData($param, 'asc', 'j.id', $settings);

            include_once drupal_get_path('module', 'ek_finance') . '/excel_expenses.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * Return expense voucher in pdf file.
     * @param int $type
     *  print type (1)
     * @param int|array $id
     *  expense id single or array
     * 
     */
    public function pdfvoucher($type, $id) {
        $markup = array();
        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }


    /*
     * Record expenses after payroll posting if hr module enabled
     * @param array $param
     *  serialized array coid => value
     * @return Object
     *  form
     * 
     */

    public function payrollrecord(Request $request, $param) {

        $build['new_expense'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\PayrollRecord', $param);
        return $build;
    }

    /**
     *  Delete an expense entry and journal ref.
     *  @param int $id
     *      expense id
     *  @return Object
     *      form
     */
    public function deleteexpenses(Request $request, $id) {

        $build['delete_expense'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\DeleteExpense', $id);

        return $build;
    }

}
