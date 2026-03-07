<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\ReportController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\Service\JournalService;
use Drupal\ek_finance\ReportingData;
use Drupal\ek_finance\PrintManager;

/**
 * Controller routines for ek module routes.
 */
class ReportController extends ControllerBase {

   
    protected $moduleHandler;
    protected $formBuilder;
    protected $journal;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('form_builder'), 
                $container->get('module_handler'),
                $container->get('ek_finance.journal')
        );
    }
    
    protected $settings;

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */

    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler,JournalService $journal) {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->settings = new FinanceSettings();
        $this->journal = $journal;
    }

    /**
     *  Generate a monthly management report filter by company and year
     *
     * @return array
     *  Render Html
     *
     */
    public function reporting(Request $request) {
        $items = array();
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');

        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterReporting', 'report');

        if (isset($_SESSION['repfilter']['filter']) && $_SESSION['repfilter']['filter'] == 1) {
            $coid = $_SESSION['repfilter']['coid'];
            $year = $_SESSION['repfilter']['year'];
            
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            if ($settings->get('budgetUnit') == 2) {
                $budgetUnit = "'000";
                $divide = 1000;
            } elseif ($settings->get('budgetUnit') == 3) {
                $budgetUnit = "'000,000";
                $divide = 1000000;
            } else {
                $budgetUnit = '';
                $divide = 1;
            }
            $items['year'] = $_SESSION['repfilter']['year'];
            $items['baseCurrency'] = $baseCurrency;
            $items['budgetUnit'] = $budgetUnit;
            $items['rounding'] = $rounding;
            if ($coid != 'all') {
                $viewS = 'allocation';
                $viewE = 'allocation';
                if ($_SESSION['repfilter']['view'] == '1') {
                    // actual data view selected
                    $viewS = 'head';
                    $viewE = 'company';
                } else {
                    // control error
                    // allocation view may be wrong if aid accounts from allocation source
                    // are not active in allocated destination
                    $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    // select all aid accounts that are used in journal from other companies
                    $or = $query->orConditionGroup();
                    $or->condition('aid', $chart['cos'] . '%', 'like');
                    $or->condition('aid', $chart['expenses'] . '%', 'like');
                    $or->condition('aid', $chart['other_expenses'] . '%', 'like');
                    $or->condition('aid', $chart['income'] . '%', 'like');
                    $or->condition('aid', $chart['other_income'] . '%', 'like');
                    $query->fields('j', ['aid'])
                            ->distinct()
                            ->condition('coid', $coid, '<>')
                            ->condition($or)
                            ->orderBy('aid');
                    $control = $query->execute();
                    $error = [];
                    while ($c = $control->fetchObject()) {
                        $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_accounts', 'a');
                        $query->fields('a', ['aid','astatus'])
                                ->condition('aid', $c->aid, '=')
                                ->condition('a.coid', $coid, '=');
                        $Obj = $query->execute()->fetchObject();
                        if ((!$Obj && $c->aid != 0) || $Obj->astatus == '0') {
                            $error[] =  $c->aid;
                        }
                    }
                    $items['error'] = $error;
                }

                $reportingData = new ReportingData($coid, $year, $baseCurrency, $rounding, $divide, $viewE, $viewS, $chart);
                $data = $reportingData->getData();

                $items = array_merge($items, $data);
                $param = serialize(
                    [
                            'coid' => $coid,
                            'year' => $year,
                            'baseCurrency' => $baseCurrency,
                            'rounding' => $rounding,
                            'divide' => $divide,
                            'view' => ['E' => $viewE, 'S' => $viewS]
                    ]
                );
                $excel = Url::fromRoute('ek_finance_reporting_excel', ['param' => $param], [])->toString();
                $items['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' title='". $this->t('Excel download') . "'><span class='ico excel green'/></a>",
                );
    
                $items['purchases'] = $data['purchases'];
                $items['expenses'] = $data['expenses'];
                $items['income'] = $data['income'];
                $items['internal_received'] = $data['internal_received'];
                $items['internal_paid'] = $data['internal_paid'];
                $items['balances'] = $data['balances'];

                return array(
                    '#theme' => 'ek_finance_reporting',
                    '#items' => $items,
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance.reporting','ek_admin/ek_admin_css'),
                    ),
                    '#cache' => [
                        'tags' => ['reporting'],
                    ],
                );
            } else {
                // display a compilation table
                // include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/reporting_compilation.inc';
                $reportingData = new ReportingData($coid, $year, $baseCurrency, $rounding, $divide, Null, Null, $chart);
                $data = $reportingData->getCompilation();
                $items['purchases'] = $data['purchases'];
                $items['expenses'] = $data['expenses'];
                $items['income'] = $data['income'];
                $items['balances'] = $data['balances'];
                $items['error'] = $data['error'];
      
                $query = "SELECT id,name from {ek_company} ORDER by id";
                $items['company'] = Database::getConnection('external_db', 'external_db')
                                ->query($query)
                                ->fetchAllKeyed();
                $p = serialize(
                    array(
                            'compilation' => $coid,
                            'year' => $year,
                            'baseCurrency' => $baseCurrency,
                            'rounding' => $rounding
                        )
                );
                $excel = Url::fromRoute('ek_finance_reporting_excel', array('param' => $p), array())->toString();
                $items['excel'] = array(
                    '#markup' => "<a href='" . $excel . "' title='". $this->t('Excel download') . "'><span class='ico excel green'/></a>",
                );
                return array(
                    '#theme' => 'ek_finance_reporting_compilation',
                    '#items' => $items,
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance.reporting','ek_admin/ek_admin_css'),
                    ),
                    '#cache' => [
                        'tags' => ['reporting'],
                    ],
                );
            }
        } else {
            return $items['form'];
        }
    }

    /**
     *  Generate a monthly management report in excel format
     *  filter by company and year
     * @param str
     *  serialized array
     * @return Object
     *  PhpExcel object download
     *  or markup if error
     *
     */
    public function excelreporting(Request $request, $param) {
        $markup = [];
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $p = unserialize($param);
        $year = $p['year'];
        $baseCurrency = $p['baseCurrency'];
        $rounding = $p['rounding'];
        $divide = 1;
        if (isset($p['coid'])) {
            $coid = $p['coid'];
            $viewE = $p['view']['E'];
            $viewS = $p['view']['S'];
            $reportingData = new ReportingData($coid, $year, $baseCurrency, $rounding, $divide, $viewE, $viewS, $chart);
            $data = $reportingData->getData();
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/templates/excel_reporting.inc';
        } else {
            
            $reportingData = new ReportingData(null, $year, $baseCurrency, $rounding, $divide, null, null, $chart);
            $data = $reportingData->getCompilation();
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/templates/excel_reporting_compilation.inc';
        }
        return $markup;
    }

    /**
     *  Manage budget data
     *  @return array
     *      Render Html
     *
     */
    public function budgeting(Request $request) {
        $items = array();
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterReporting');

        if (isset($_SESSION['repfilter']['filter']) && $_SESSION['repfilter']['filter'] == 1) {
            $coid = $_SESSION['repfilter']['coid'];
            $year = $_SESSION['repfilter']['year'];
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            if ($settings->get('budgetUnit') == 2) {
                $budgetUnit = "'000";
            } elseif ($settings->get('budgetUnit') == 3) {
                $budgetUnit = "'000,000";
            } else {
                $budgetUnit = '';
            }
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/budgeting.inc';

            $param = serialize(
                array(
                        'coid' => $coid,
                        'year' => $year,
                        'baseCurrency' => $baseCurrency,
                        'rounding' => $rounding
                    )
            );
            $excel = Url::fromRoute('ek_finance_budgeting_excel', array('param' => $param), array())->toString();
            $items['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . $this->t('Export') . "</a>",
            );


            $items['table_1'] = $table_1;
            $items['table_2'] = $table_2;
            $items['table_3'] = $table_3;
        }


        return array(
            '#theme' => 'ek_finance_budgeting',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.budgeting'),
            ),
        );
    }

    /**
     *  Callback function for budget editing
     *  param = array (reference, value)
     *
     *  @return json response
     *
     */
    public function updatebudget(Request $request) {
        $reference = $_POST['reference'];
        $value = $_POST['value'];
        str_replace(',', '', $value);

        if (is_numeric($value)) {
            $update = Database::getConnection('external_db', 'external_db')
                    ->merge('ek_yearly_budget')
                    ->key(array('reference' => $reference))
                    ->fields(array('reference' => $reference, 'value_base' => $value))
                    ->execute();
            return new JsonResponse(array('data' => true));
        }
    }

    /**
     *  file Generate a monthly management report in excel format
     *  @param array $param
     *      serialized array
     *      Keys: coid (int company id), year (string YYYY), baseCurrency (string code)
     *  @return Object
     *      PhpExcel object download
     *      or markup if error
     *
     */
    public function excelbudgeting($param) {
        $markup = [];
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            // The chart structure is as follow
            // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
            // 'other_liabilities', 'other_income', 'other_expenses'
            $chart = $this->settings->get('chart');
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/templates/excel_budgeting.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     *  profit & loss report for current year and company
     *
     * @return array
     *  render Html
     *
     */
    public function profitloss(Request $request) {
        $items = [];
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterBalance');

        if (isset($_SESSION['bsfilter']['filter']) && $_SESSION['bsfilter']['filter'] == 1) {
            $coid = $_SESSION['bsfilter']['coid'];
            $year = $_SESSION['bsfilter']['year'];
            $month = $_SESSION['bsfilter']['month'];
            $summary = $_SESSION['bsfilter']['summary'];
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
           
            $items += $this->journal->profitloss($coid, $year, $month, $summary);
            $param = serialize(
                [
                    'coid' => $coid,
                    'year' => $year,
                    'month' => $month,
                    'baseCurrency' => $baseCurrency,
                    'summary' => $summary,
                ]
            );

            $pdf = Url::fromRoute('ek_finance_extract.profit_loss_pdf', ['param' => $param], [])->toString();
            $items['pdf'] = [
                '#markup' => "<a href='" . $pdf . "' title='" . $this->t('Export to pdf') . "' target='_blank'><span class='ico pdf red'/></a>",
            ];
            $post = Url::fromRoute('ek_finance.admin.new_year', [], [])->toString();
            $items['post'] = [
                '#markup' => "<a href='" . $post . "' >" . $this->t('Start new year') . "</a>",
            ];
        }

        return [
            '#theme' => 'ek_profit_loss',
            '#items' => $items,
            '#attached' => [
                'library' => ['ek_finance/ek_finance.reporting', 'ek_admin/ek_admin_css'],
            ],
        ];
    }

    /**
     *  Generate a PL report in pdf format
     *
     * @param array
     *  serialized array
     *  Keys: coid (int company id), year (string YYY)
     *  month (int), baseCurrency (string code)
     *  summary (bool)
     * @return Object
     *  Pdf object download
     *
     */
    public function pdfprofitloss(Request $request, $param) {
        $print = new PrintManager();
        $print->makePdf(['pl' ,0, $param]);
        return new \Symfony\Component\HttpFoundation\Response('', 204);
    }

    /**
     * Generate a balance sheet report
     *
     * @return array
     *  render Html
     */
    public function balancesheet(Request $request) {
        $items = [];
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        // $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterBalance');

        if (isset($_SESSION['bsfilter']['filter']) && $_SESSION['bsfilter']['filter'] == 1) {
            $coid = $_SESSION['bsfilter']['coid'];
            $year = $_SESSION['bsfilter']['year'];
            $month = $_SESSION['bsfilter']['month'];
            $summary = $_SESSION['bsfilter']['summary'];
            //$settings = new FinanceSettings();
            $baseCurrency = $this->settings->get('baseCurrency');

            $items += $this->journal->balancesheet($coid, $year, $month, $summary);
            $param = serialize(
                [
                    'coid' => $coid,
                    'year' => $year,
                    'month' => $month,
                    'baseCurrency' => $baseCurrency,
                    'summary' => $summary,
                ]
            );

            $pdf = Url::fromRoute('ek_finance_extract.balance_sheet_pdf', array('param' => $param), array())->toString();
            $items['pdf'] = array(
                '#markup' => "<a href='" . $pdf . "' title='" . $this->t('Export to pdf') . "' target='_blank'><span class='ico pdf red'/></a>",
            );

            if (strtotime(date("Y-m-d")) > strtotime($items['dates']["fiscal_year"]) && $items['dates']['archive'] == false) {
                $post = Url::fromRoute('ek_finance.admin.new_year', array(), array())->toString();
                $items['post'] = array(
                    '#markup' => "<a href='" . $post . "' >" . $this->t('Start new year') . "</a>",
                );
            } else {
                $items['post'] = '';
            }
        }

        return [
            '#theme' => 'ek_balance_sheet',
            '#items' => $items,
            '#attached' => [
                'library' => ['ek_finance/ek_finance.reporting','ek_admin/ek_admin_css'],
            ],
        ];
    }

    /**
     * Generate a BS report in pdf format
     * @param array
     *  serialized array
     *  Keys: coid (int company id), year (string YYY)
     *  month (int), baseCurrency (string code)
     *  summary (bool)
     * @return Object
     *  Pdf object download
     */
    public function pdfbalancesheet(Request $request, $param) {

        $print = new PrintManager();
        $print->makePdf(['bs' ,0, $param]);
        return new \Symfony\Component\HttpFoundation\Response('', 204);
    
    }

    /**
     *  Generate a cash analysis report
     *
     * @return array
     *  render Html
     *
     */
    public function cashflow() {
        $items = [];
        $amortization = null;
        // The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses',
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterCashflow');
        if ($this->moduleHandler->moduleExists('ek_assets')) {
            $amortization = true;
        }

        if (isset($_SESSION['cashflowfilter']['filter']) && $_SESSION['cashflowfilter']['filter'] == 1) {
            $coid = $_SESSION['cashflowfilter']['coid'];
            $settings = new FinanceSettings();
            $items['baseCurrency'] = $settings->get('baseCurrency');
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            $items['rounding'] = $rounding;
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/cashflow_statement.inc';

            $param = serialize(
                [
                    'coid' => $coid,
                    'amortization' => $amortization,
                ]
            );

            $excel = Url::fromRoute('ek_finance.extract.cashflow_statement', ['param' => $param], [])->toString();
            $items['excel'] = [
                '#markup' => "<a href='" . $excel . "' title='". $this->t('Excel download') ."'><span class='ico excel green'/></a>",
            ];
        }

        return [
            '#theme' => 'ek_finance_cashflow',
            '#items' => $items,
            '#attached' => [
                'library' => ['ek_finance/ek_finance.cashflow'],
                'drupalSettings' => ['rounding' => $rounding],
            ],
        ];
    }

    /**
     * Generate aa cash analysis report in excel format
     * @param string $param
     *   serialized array
     *  Keys: coid (int company if), amortization (bool)
     *
     * @return Object
     *  PhpExcel object download
     *  or markup if error
     *
     */
    public function excelcashflow($param) {
        // @TODO excel link disables in ek_finance_cashflow
        $markup = [];
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $chart = $this->settings->get('chart');
            $settings = new FinanceSettings();
            $items['baseCurrency'] = $settings->get('baseCurrency');
            $items['rounding'] = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            $extract = unserialize($param);
            $coid = $extract['coid'];
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/templates/excel_cash_statement.inc';
        }
        return ['#markup' => $markup];
    }
}
