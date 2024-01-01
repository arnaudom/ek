<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\ReconciliationController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_finance\Journal;

/**
 * Controller routines for ek module routes.
 */
class ReconciliationController extends ControllerBase {
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
    protected $dataBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a ReconciliationController object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $data_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(Connection $database, FormBuilderInterface $data_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $data_builder;
        $this->moduleHandler = $module_handler;
        $this->financeSettings = new \Drupal\ek_finance\FinanceSettings();
    }

    /**
     *  do reconciliation between internal account and external data
     *
     * @return array
     *  Form
     *
     */
    public function reconciliation(Request $request) {
        $build['reconciliation'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ReconciliationForm');
        return $build;
    }

    /**
     *  do reconciliation reset by id
     *
     * @return array
     *  Form
     *
     */
    public function reset($id) {
        $build['reconciliation'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ResetReconciliation', $id);
        return $build;
    }
    
    /**
     *  display list of reconciliationreports
     *
     * @return array
     *  render Html
     *
     */
    public function reportsreconciliation(Request $request) {
        $build['reconciliation_report'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterRecoReports');


        if (isset($_SESSION['recofilter']['filter']) && $_SESSION['recofilter']['filter'] == 1) {
            $header = [
                'id' => [
                    'data' => $this->t('Id'),
                    'specifier' => 'id',
                    'field' => 'id',
                ],
                'company' => [
                    'data' => $this->t('Company'),
                    'class' => [RESPONSIVE_PRIORITY_LOW],
                ],
                'date' => [
                    'data' => $this->t('Date'),
                    'class' => [RESPONSIVE_PRIORITY_LOW],
                ],
                'aid' => [
                    'data' => $this->t('Account'),
                    'field' => 'aid',
                    'class' => [RESPONSIVE_PRIORITY_LOW],
                ],
                'attachment' => [
                    'data' => $this->t('Attachment'),
                    'field' => 'aid',
                    'class' => [RESPONSIVE_PRIORITY_LOW],
                ],
                'reset' => [
                    'data' => $this->t('Reset'),
                    'field' => 'reset',
                    'class' => [RESPONSIVE_PRIORITY_LOW],
                ],
            ];



            if ($_SESSION['recofilter']['coid'] == '0') {
                $_SESSION['recofilter']['coid'] = '%';
            }
            $a = [
                ':coid' => $_SESSION['recofilter']['coid'],
                ':date1' => $_SESSION['recofilter']['from'],
                ':date2' => $_SESSION['recofilter']['to'],
                ':t' => '1',
            ];

            /*$query = "SELECT * FROM {ek_journal_reco_history} "
                    . "WHERE coid like :coid AND date >= :date1 AND date <= :date2 AND type=:t";
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);*/
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal_reco_history', 'h');
            $query->fields('h');
            $query->condition('coid', $_SESSION['recofilter']['coid'], 'like');
            $query->condition('date', $_SESSION['recofilter']['from'], '>=');
            $query->condition('date', $_SESSION['recofilter']['to'], '<=');
            $query->condition('type', 1, '=');
            
            $options = [];
            $rows = $query->countQuery()->execute()->fetchField();
            $data = $query->execute();
            $i = 0;
            while ($r = $data->fetchObject()) {
                $i++;
                $query = "SELECT aname from {ek_accounts} WHERE aid=:a AND coid=:c";
                $aname = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':a' => $r->aid, ':c' => $r->coid])
                        ->fetchField();
                $query = "SELECT name from {ek_company} WHERE id=:id";
                $company_name = Database::getConnection('external_db', 'external_db')->query($query, [':id' => $r->coid])->fetchField();
                $url = Url::fromRoute('ek_finance_reconciliation.pdf', ['id' => $r->id])->toString();

                $report = '<a href="' . $url . '" target="_blank"  title="' . $this->t('report') . '">' . $r->id . '</a>';

                if ($r->uri <> '') {
                    $attachment = "<a class='blue' href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($r->uri) . "' target='_blank'>" . $this->t('Attachment') . "</a>";
                } else {
                    $attachment = 'upload';
                    $param = 'upload-' . $r->id . '-statement';
                    $modal_route = Url::fromRoute('ek_finance.manage.modal_expense', ['param' => $param])->toString();
                    $attachment = $this->t('<a href="@url" class="@c"  data-accepts=@a  >upload</a>', ['@url' => $modal_route, '@c' => 'use-ajax red', '@a' => "application/vnd.drupal-modal",]);
                }
                
                if($i == $rows) {
                    $reset_lk =  Url::fromRoute('ek_finance.manage.reconciliation_reset', ['id' => $r->id])->toString();
                    $reset = "<a href=$reset_lk  title='" . $this->t('reset') . "' class='red fa fa-times' ></a>";
                }
                $options[$r->id] = [
                    'id' => ['data' => ['#markup' => $report]],
                    'company' => $company_name,
                    'date' => $r->date,
                    'aid' => ['data' => $r->aid . " " . $aname],
                    'attachment' => ['data' => ['#markup' => $attachment]],
                    'reset' => ['data' => ['#markup' => $reset]],
                ];
            }


            $build['reco_table'] = [
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => ['id' => 'reco_table'],
                '#empty' => $this->t('No report available.'),
                '#attached' => [
                    'library' => ['ek_finance/ek_finance'],
                ],
            ];
        }

        return $build;
    }

    /**
     * Extract reconciliation report in pdf format
     *
     * @param int id
     *      id of document record
     * @return Object or markup
     *      Pdf object downaload
     *      or markup if error
     *
     */
    public function pdfreconciliation(Request $request, $id) {
        $type = 3;
        $markup = [];
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     * Extract in excel format reconciliation table
     * @param array $param
     *  serialized array
     *  keys: coid, account, date
     * @return Object
     *  PhpExcel object download
     *  or markup if error
     *
     */
    public function excelreco($param) {
        $markup = array();
        $rounding = (!null == $this->financeSettings->get('rounding')) ? $this->financeSettings->get('rounding'):2;
        
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $data = array();
            $markup = array();
            $param = unserialize($param);
            //extract needed data
            $data['date'] = $param['date'];
            $data['company'] = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT name FROM {ek_company} WHERE id=:id', [':id' => $param['coid']])
                    ->fetchField();

            $query = "SELECT id,date from {ek_journal} WHERE aid=:aid and coid=:coid "
                    . "AND date<=:date2 and reconcile=0 and exchange=0 order by date";
            $a = array(
                ':aid' => $param['account'],
                ':coid' => $param['coid'],
                ':date2' => $param['date']
            );

            $result = Database::getConnection('external_db', 'external_db')->query($query, $a);


            $query = "SELECT * from {ek_accounts} WHERE aid=:account and coid=:coid";
            $a = array(':account' => $param['account'], ':coid' => $param['coid']);
            $account = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchObject();


            if ($account->balance_date == '') {
                $account->balance_date = 0;
            } //todo input alert for opening balance
            $data['aname'] = $account->aname;
            $data['aid'] = $account->aid;

            // sum transaction currency
            $query = "SELECT sum(value) from {ek_journal} "
                    . "WHERE exchange=:exc and type=:type "
                    . "AND aid=:aid and coid=:coid "
                    . "AND ( (date>=:dateopen and reconcile=:reco) OR reconcile=:reco2 )";
            $a = array(
                ':exc' => 0,
                ':type' => 'credit',
                ':aid' => $param['account'],
                ':coid' => $param['coid'],
                ':dateopen' => $account->balance_date,
                ':reco' => 1,
                ':reco2' => date('Y')
            );

            $credit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
            $a = array(
                ':exc' => 0,
                ':type' => 'debit',
                ':aid' => $param['account'],
                ':coid' => $param['coid'],
                ':dateopen' => $account->balance_date,
                ':reco' => 1,
                ':reco2' => date('Y')
            );
            $query = "SELECT sum(value) FROM {ek_journal} "
                    . "WHERE exchange=:exc and type=:type AND aid=:aid "
                    . "AND coid=:coid and ( (date>=:dateopen and reconcile=:reco) or reconcile=:reco2 )";

            $debit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            if ($debit == null) {
                $debit = 0;
            }
            if ($credit == null) {
                $credit = 0;
            }
            $balance = $account->balance + $credit - $debit;
            if ($balance < 0) {
                $ab = 'dt';
            } else {
                $ab = 'ct';
            }

            $data['openchart'] = $account->balance;
            $data['opencredit'] = round($credit, $rounding);
            $data['opendebit'] = round($debit, $rounding);
            $data['openbalance'] = $balance;


            // top bar displaying the total
            $data["debits"] = round($debit, $rounding);
            $data["credits"] = round($credit, $rounding);
            $data['balance'] = abs(round($balance, $rounding)) . " (" . $ab . ")";
            $data["statement"] = abs(round($balance, $rounding));
            $data['rows'] = array();
            $i = 0;
            while ($r = $result->fetchObject()) {
                $j = Journal::journalEntryDetails($r->id);
                if (is_array($j['comment'])) {
                    //remove the hyperlink tag for excel
                    preg_match("'>(.*?)</a>'si", $j['comment']['#markup'], $match);
                    $comment = $j['reference'] . " - " . $match[1];
                } else {
                    $comment = $j['reference'] . " - " . $j['comment'];
                }
                $row = array();
                $row['id'] = $r->id;
                $row['journal_id'] = $r->id;
                $row['type'] = $j['type'];
                $row['date'] = $j['date'];
                $row['comment'] = $comment;
                $row['value'] = $j['value'];

                $data['rows'][$i] = $row;
                $i++;
            }//while

            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/excel_reconciliation.inc';
        }
        return ['#markup' => $markup];
    }
}
