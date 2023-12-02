<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\BalanceTrialController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\Journal;

/**
 * Controller routines for ek module routes.
 */
class BalanceTrialController extends ControllerBase
{
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
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a BalanceTrialController object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  Finance trial balance by account and date
     *
     *  @return array
     *      rendered Html
     *
     */
    public function trialbalance(Request $request)
    {
        $items['filter_trial'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterTrial');
        $items['data'] = array();
        $journal = new Journal();

        if (isset($_SESSION['tfilter']['filter']) && $_SESSION['tfilter']['filter'] == 1) {
            $param = array(
                'coid' => $_SESSION['tfilter']['coid'],
                'year' => $_SESSION['tfilter']['year'],
                'month' => $_SESSION['tfilter']['month'],
                'active' => $_SESSION['tfilter']['active'],
                'null' => $_SESSION['tfilter']['null'],
            );

            $items['data'] = $journal->trial($param);

            $excel = Url::fromRoute('ek_finance.extract.excel-trial', array('param' => serialize($param)), array())->toString();

            $items['excel'] = "<a href='" . $excel . "' title='". $this->t('Excel download') . "'><span class='ico excel green'/></a>";
            
            if ($items['data']['total']['error1'] == '1') {
                //try to identify balances errors
                $start = $_SESSION['tfilter']['year'] . '-01-01';
                $dates = $journal->getFiscalDates($_SESSION['tfilter']['coid'], $_SESSION['tfilter']['year'], $_SESSION['tfilter']['month']);
                $items['error'] = $journal->traceError(['coid' => $_SESSION['tfilter']['coid'], 'from' => $start, 'to' => $dates['to']]);
            }
        } 
        return array(
            '#theme' => 'ek_finance_trial',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance', 'ek_finance/ek_finance.dialog','ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Trial balance in excel format
     *
     * @param array
     *  serialized array with keys
     *  coid (int), year (int), month (int), active (bool), null (bool)
     * @return Object
     *  PhpExcel object
     */
    public function exceltrial($param = null)
    {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            include_once drupal_get_path('module', 'ek_finance') . '/excel_trial.inc';
        }
        return ['#markup' => $markup];
    }
}
