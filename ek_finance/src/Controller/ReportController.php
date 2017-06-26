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
use Drupal\ek_admin\CompanySettings;

/**
 * Controller routines for ek module routes.
 */
class ReportController extends ControllerBase {

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
     * Constructs a  object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->settings = new FinanceSettings();
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
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');

        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterReporting');

        if (isset($_SESSION['repfilter']['filter']) && $_SESSION['repfilter']['filter'] == 1) {

            $coid = $_SESSION['repfilter']['coid'];
            $year = $_SESSION['repfilter']['year'];
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
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

            include_once drupal_get_path('module', 'ek_finance') . '/reporting.inc';

            $param = serialize(
                    array(
                        'coid' => $coid,
                        'year' => $year,
                        'baseCurrency' => $baseCurrency
                    )
            );
            $excel = Url::fromRoute('ek_finance_reporting_excel', array('param' => $param), array())->toString();
            $items['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
            );


            $items['purchases'] = $table_0;
            $items['expenses'] = $table_1;
            $items['income'] = $table_2;
            $items['total'] = $table_3;
        }


        return array(
            '#theme' => 'ek_finance_reporting',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.reporting'),
            ),
        );
    }

    /**
     *  Generate a monthly management report in excel format 
     *  filter by company and year
     * 
     * @return Object
     *  PhpExcel object download
     *  or markup if error
     *
     */
    public function excelreporting(Request $request, $param) {
        $markup = array();
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        include_once drupal_get_path('module', 'ek_finance') . '/excel_reporting.inc';
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
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterReporting');

        if ($_SESSION['repfilter']['filter'] == 1) {

            $coid = $_SESSION['repfilter']['coid'];
            $year = $_SESSION['repfilter']['year'];
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');

            if ($settings->get('budgetUnit') == 2) {
                $budgetUnit = "'000";
            } elseif ($settings->get('budgetUnit') == 3) {
                $budgetUnit = "'000,000";
            } else {
                $budgetUnit = '';
            }
            include_once drupal_get_path('module', 'ek_finance') . '/budgeting.inc';

            $param = serialize(
                    array(
                        'coid' => $coid,
                        'year' => $year,
                        'baseCurrency' => $baseCurrency
                    )
            );
            $excel = Url::fromRoute('ek_finance_budgeting_excel', array('param' => $param), array())->toString();
            $items['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
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
            return new JsonResponse(array('data' => TRUE));
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
        $markup = array();
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            //The chart structure is as follow
            // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
            // 'other_liabilities', 'other_income', 'other_expenses'
            $chart = $this->settings->get('chart');
            include_once drupal_get_path('module', 'ek_finance') . '/excel_budgeting.inc';
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

        $items = array();
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterBalance');

        if ($_SESSION['bsfilter']['filter'] == 1) {

            $coid = $_SESSION['bsfilter']['coid'];
            $year = $_SESSION['bsfilter']['year'];
            $month = $_SESSION['bsfilter']['month'];
            $summary = $_SESSION['bsfilter']['summary'];
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
            include_once drupal_get_path('module', 'ek_finance') . '/profitloss.inc';

            $param = serialize(
                    array(
                        'coid' => $coid,
                        'year' => $year,
                        'month' => $month,
                        'baseCurrency' => $baseCurrency,
                        'summary' => $summary,
                    )
            );

            $pdf = Url::fromRoute('ek_finance_extract.profit_loss_pdf', array('param' => $param), array())->toString();
            $items['pdf'] = array(
                '#markup' => "<a href='" . $pdf . "' target='_blank'>" . t('Export') . "</a>",
            );
            $post = Url::fromRoute('ek_finance.admin.new_year', array(), array())->toString();
            $items['post'] = array(
                '#markup' => "<a href='" . $post . "' >" . t('Start new year') . "</a>",
            );

            $items['table_1'] = $table_1; //revenue
            $items['table_2'] = $table_2; //cos
            $items['table_3'] = $table_3; //charges
            $items['table_4'] = $table_4; //result
        }


        return array(
            '#theme' => 'ek_profit_loss',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.reporting'),
            ),
        );
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
     *  Fpdf object download
     *
     */
    public function pdfprofitloss(Request $request, $param) {
        //output is controlled by pdf.inc where data are extracted
        //base on document generated      
        $type = 4;
        $markup = array();
        $params = unserialize($param);
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $settings = new CompanySettings($params['coid']);
        $fiscalYear = $settings->get('fiscal_year');
        $fiscalMonth = $settings->get('fiscal_month');

        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     * Generate a balance sheet report
     * 
     * @return array
     *  render Html
     */
    public function balancesheet(Request $request) {

        $items = array();
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterBalance');

        if ($_SESSION['bsfilter']['filter'] == 1) {

            $coid = $_SESSION['bsfilter']['coid'];
            $year = $_SESSION['bsfilter']['year'];
            $month = $_SESSION['bsfilter']['month'];
            $summary = $_SESSION['bsfilter']['summary'];
            //$settings = new FinanceSettings();
            $baseCurrency = $this->settings->get('baseCurrency');
            include_once drupal_get_path('module', 'ek_finance') . '/balancesheet.inc';

            $param = serialize(
                    array(
                        'coid' => $coid,
                        'year' => $year,
                        'month' => $month,
                        'baseCurrency' => $baseCurrency,
                        'summary' => $summary,
                    )
            );

            $pdf = Url::fromRoute('ek_finance_extract.balance_sheet_pdf', array('param' => $param), array())->toString();
            $items['pdf'] = array(
                '#markup' => "<a href='" . $pdf . "' target='_blank'>" . t('Export') . "</a>",
            );
            $post = Url::fromRoute('ek_finance.admin.new_year', array(), array())->toString();
            $items['post'] = array(
                '#markup' => "<a href='" . $post . "' >" . t('Start new year') . "</a>",
            );
            $items['table_1'] = $table_1;
            $items['table_2'] = $table_2;
            $items['table_3'] = $table_3;
            $items['table_4'] = $table_4;
            $items['table_5'] = $table_5;
        }


        return array(
            '#theme' => 'ek_balance_sheet',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.reporting'),
            ),
        );
    }

    /**
     * Generate a BS report in pdf format
     * @param array
     *  serialized array
     *  Keys: coid (int company id), year (string YYY)
     *  month (int), baseCurrency (string code)
     *  summary (bool)
     * @return Object
     *  Fpdf object download
     */
    public function pdfbalancesheet(Request $request, $param) {

        //output is controlled by pdf.inc where data are extracted
        //base on document generated
        $type = 5;
        $markup = array();
        $params = unserialize($param);
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $settings = new CompanySettings($params['coid']);
        $fiscalYear = $settings->get('fiscal_year');
        $fiscalMonth = $settings->get('fiscal_month');
        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     *  Generate a cash analysis report
     * 
     * @return array
     *  render Html
     *
     */
    public function cashflow() {

        $items = array();
        $amortization = NULL;
        //The chart structure is as follow
        // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 
        // 'other_liabilities', 'other_income', 'other_expenses'
        $chart = $this->settings->get('chart');
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterCashflow');
        if ($this->moduleHandler->moduleExists('ek_assets')) {
            $amortization = TRUE;
        }

        if ($_SESSION['cashflowfilter']['filter'] == 1) {

            $coid = $_SESSION['cashflowfilter']['coid'];
            $settings = new FinanceSettings();
            $items['baseCurrency'] = $settings->get('baseCurrency');

            include_once drupal_get_path('module', 'ek_finance') . '/cashflow_statement.inc';

            $param = serialize(
                    array(
                        'coid' => $coid,
                        'amortization' => $amortization,
                    )
            );

            $excel = Url::fromRoute('ek_finance.extract.cashflow_statement', array('param' => $param), array())->toString();
            $items['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
            );
        }


        return array(
            '#theme' => 'ek_finance_cashflow',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.cashflow'),
            ),
        );
    }

    /**
     * Generate aa cash analysis report in excel format
     * @param array $param
     *   serialized array
     *  Keys: coid (int company if), amortization (bool)
     * 
     * @return Object
     *  PhpExcel object download
     *  or markup if error 
     *  
     */
    public function excelcashflow($param) {
        $markup = array();
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $chart = $this->settings->get('chart');
            $settings = new FinanceSettings();
            $items['baseCurrency'] = $settings->get('baseCurrency');
            $extract = unserialize($param);
            $coid = $extract['coid'];
            include_once drupal_get_path('module', 'ek_finance') . '/excel_cash_statement.inc';
        }
        return ['#markup' => $markup];
    }

}
