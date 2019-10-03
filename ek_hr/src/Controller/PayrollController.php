<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_hr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_hr\HrSettings;

/**
 * Controller routines for ek module routes.
 */
class PayrollController extends ControllerBase {
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
     * Return advance payroll form
     *
     */
    public function Advance(Request $request) {

        $build['payrolladvance'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\Advance');
        Return $build;
        
    }
    /**
     * Return payroll form
     *
     */
    public function payroll(Request $request) {
        $flag = 1;
        if(!null == $request->query->get('coid')) {
            $flag = (in_array($request->query->get('coid'),AccessCheck::GetCompanyByUser())) ? 1 : 0;
        }
        if(!null == $request->query->get('eid')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_hr_workforce', 'h');
                $query->fields('h', ['administrator'])
                        ->condition('id', $request->query->get('eid'), '=');
                $e = $query->execute()->fetchField();
                if($e != 0) {
                    $admins = explode(',',$e);
                    $flag = (in_array(\Drupal::currentUser()->id(),$admins)) ? 1 : 0;
                }
                
        }
        if($flag == 0){
            $url = Url::fromRoute('ek_hr.parameters', array(), array())->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => t('@document cannot be edited.', array('@document' => t('payroll')))];
            $items['description'] = ['#markup' => t('no access')];
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
        $build['payroll'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\PayrollRecord',$request->query->get('coid'),$request->query->get('eid'));
        Return $build;
    }
    
    /**
     * Callback for payroll form for tax/fund computation
     */
    public function readTable(Request $request) {

        $coid = $request->query->get('coid');
        $type = $request->query->get('type');
        $value = $request->query->get('value');
        $field1 = $request->query->get('field1');
        $field2 = (NULL != $request->query->get('field2')) ? $request->query->get('field2') : NULL;
               
        if ($type != 'income_tax') {
            //call for funds table
            $param = ['coid' => $coid,'type' => $type ,'value' => $value, 'field1' => $field1,'field2' => $field2];
            if($this->moduleHandler->invokeAll('payroll_fund',[$param])){
                $fund = $this->moduleHandler->invokeAll('payroll_fund',[$param]);
                    return new JsonResponse($fund);
            } else {
                return new JsonResponse(['amount1' => 0, 'amount2' => 0]);
            }
            
        } else {
            //call for tax table
            $param = ['coid' => $coid,'value' => $value, 'field1' => $field1];
            if($this->moduleHandler->invokeAll('payroll_tax', [$param])){
                $tax = $this->moduleHandler->invokeAll('payroll_tax', [$param]);
                    return new JsonResponse($tax);
            } else {
                return new JsonResponse(['amount1' => 0, 'amount2' => 0]);
            }
        }
        
        return [];

    }

    /**
     * Return current payroll list by company
     *
     */
    public function payrollcurrent(Request $request) {

        $build['payrollcurrent'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\FilterCompanyList');

        $header = array(
            'id' => array(
                'data' => $this->t('ID'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'sort' => 'asc',
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'field' => 'name',
                'sort' => 'asc',
            ),
            'month' => array(
                'data' => $this->t('Month'),
            ),
            'wstatus' => array(
                'data' => $this->t('Work status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'start' => array(
                'data' => $this->t('Joined'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'gross' => array(
                'data' => $this->t('Gross salary'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'deduc' => array(
                'data' => $this->t('Deductions'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'net' => array(
                'data' => $this->t('Net'),
            ),
            'operations' => '',
        );

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);



        $query = "SELECT * from {ek_hr_workforce_pay} INNER JOIN {ek_hr_workforce} "
                . "ON ek_hr_workforce_pay.id=ek_hr_workforce.id  "
                . "WHERE company_id=:coid AND FIND_IN_SET (company_id, :a) order by ek_hr_workforce.id";

        if (isset($_SESSION['hrlfilter']['filter']) && $_SESSION['hrlfilter']['filter'] == 1) {

            $a = array(
                ':coid' => $_SESSION['hrlfilter']['coid'],
                ':a' => $company
            );

            //$data = db_query($query, $a);
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

            while ($r = $data->fetchObject()) {
                $eid = ($r->custom_id != '') ? $r->custom_id : $r->id;
                $options[$r->id] = array(
                    'id' => ['data' => ['#markup' => "<span class='badge'>" . $eid . "</span>"]],
                    'name' => array('data' => $r->name, 'title' => $r->given_name),
                    'month' => $r->month,
                    'status' => $r->active,
                    'start' => $r->start,
                    'gross' => $r->gross,
                    'deduc' => $r->deduction,
                    'net' => number_format($r->nett, 2) . ' ' . $r->currency,
                    'operations' => array(
                        'data' => array(
                            '#type' => 'operations',
                            '#links' => array(
                                'edit' => array(
                                    'title' => $this->t('Edit pay'),
                                    'url' => Url::fromRoute('ek_hr.payroll',['coid' => $_SESSION['hrlfilter']['coid'], 'eid' => $r->id]),
                                ),
                                'change' => array(
                                    'title' => $this->t('Edit profile'),
                                    'url' => Url::fromRoute('ek_hr.employee.view', ['id' => $r->id]),
                                ),
                            ),
                        ),
                    ),
                );
            }

            $param = serialize(array('coid' => $_SESSION['hrlfilter']['coid']));
            $excel = Url::fromRoute('ek_hr.current-payroll-excel', array('param' => $param), array())->toString();
            $build['excel'] = array(
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
            );

            $build['hr_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'hr_current_pay'),
                '#empty' => $this->t('No data'),
                '#attached' => array(
                    'library' => array('ek_hr/ek_hr_css'),
                ),
            );
        } else {
            
        }



        Return $build;
    }

    /*
     * Render current payroll list to excel
     * @param array $param coid
     */

    public function extractcurrent($param = NULL) {
        $markup = array();    
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);

            $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            $opt = NEW HrSettings($param['coid']);

            $fund = [
                'fund1' => $opt->get('param', 'c', 'value'),
                'fund2' => $opt->get('param', 'h', 'value'),
                'fund3' => $opt->get('param', 'q', 'value'),
                'fund4' => $opt->get('param', 'v', 'value'),
                'fund5' => $opt->get('param', 'aa', 'value'),
            ];

            $query = "SELECT * from {ek_hr_workforce_pay} "
                    . "INNER JOIN {ek_hr_workforce} ON ek_hr_workforce_pay.id=ek_hr_workforce.id  "
                    . "WHERE company_id=:coid AND FIND_IN_SET (company_id, :a) order by ek_hr_workforce.id";
            $a = array(
                ':coid' => $param['coid'],
                ':a' => $company
            );

            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
            include_once drupal_get_path('module', 'ek_hr') . '/excel_current_payroll.inc';
        }
        
        return ['#markup' => $markup];
    }

    /**
     * call for for outputing payslips
     *
     */
    public function payslip(Request $request) {

        $build['payslip'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\Payslip');

        if (isset($_SESSION['printpayslip']['filter']) && $_SESSION['printpayslip']['filter'] == 1) {
            $_SESSION['printpayslip']['filter'] = 0;

            $param = serialize(
                    array(
                        $_SESSION['printpayslip']['coid'],
                        $_SESSION['printpayslip']['month'],
                        $_SESSION['printpayslip']['template'],
                        $_SESSION['printpayslip']['from'],
                        $_SESSION['printpayslip']['to'],
                    )
            );

            $path = $GLOBALS['base_url'] . "/human-resources/print/output/" . $param;

            $iframe = "<p>" . t('Month') . ': ' . $_SESSION['printpayslip']['month'] . "</p>"
                    . "<iframe src ='" . $path . "' width='100%' height='800px' id='view' name='view'></iframe>";
            $build['iframe'] = $iframe;
        }

        $_SESSION['printpayslip'] = array();

        return array(
            '#items' => $build,
            '#theme' => 'hriframe',
        );
    }

    /**
     * call for for outputing forms
     *
     */
    public function HrForms(Request $request) {

        $build['payslip'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\FormSelect');

        if ($_SESSION['printforms']['filter'] == 1) {
            $param = serialize(
                    array(
                        $_SESSION['printforms']['coid'],
                        $_SESSION['printforms']['month'],
                        $_SESSION['printforms']['template'],
                    )
            );

            if (strpos($_SESSION['printforms']['template'], '_xls') != FALSE) {
                $_SESSION['printforms'] = array();

                include_once drupal_get_path('module', 'ek_hr') . '/manage_output_form.inc';
                
            } elseif (strpos($_SESSION['printforms']['template'], '_pdf') != FALSE) {

                $_SESSION['printforms'] = array();
                $path = $GLOBALS['base_url'] . "/human-resources/print/output-form/" . $param;
                $build['iframe'] = array(
                    '#markup' => "<iframe src ='" . $path . "' width='100%' height='700px' id='view' name='view'></iframe>",
                );
            }
        }

        Return $build;
    }

    /**
     * manage output format of payslip selected
     *
     */
    public function OutputPayslip(Request $request, $param) {
        $markup = array();
        include_once drupal_get_path('module', 'ek_hr') . '/manage_output.inc';
        return ['#markup' => $markup];
    }

    /**
     * manage output form selected
     *
     */
    public function OutputForm(Request $request, $param) {
        $markup = array();
        include_once drupal_get_path('module', 'ek_hr') . '/manage_output_form.inc';
        return ['#markup' => $markup];
    }

    /**
     * post current data to archive
     *
     */
    public function post(Request $request) {

        $build['post_payroll'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\PostData');
        Return $build;
    }

}

//class