<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
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
use Drupal\ek_admin\src\Access\AccessCheck;
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
     * Constructs a  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $data_builder
     *   The form builder service.
     */
    public function __construct(Connection $database, FormBuilderInterface $data_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $data_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  do reconciliation
     *
     */
    public function reconciliation(Request $request) {

        $build['reconciliation'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ReconciliationForm');

        return $build;
    }

    /**
     *  display list of reports
     *
     */
    public function reportsreconciliation(Request $request) {

        $build['reconciliation_report'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterRecoReports');


        if (isset($_SESSION['recofilter']['filter']) && $_SESSION['recofilter']['filter'] == 1) {
            $header = array(
                'id' => array(
                    'data' => $this->t('Id'),
                    'specifier' => 'id',
                    'field' => 'id',
                ),
                'company' => array(
                    'data' => $this->t('Company'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'date' => array(
                    'data' => $this->t('Date'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'aid' => array(
                    'data' => $this->t('Account'),
                    'field' => 'aid',
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'attachment' => array(
                    'data' => $this->t('Attachment'),
                    'field' => 'aid',
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),                
            );



            if ($_SESSION['recofilter']['coid'] == '0')
                $_SESSION['recofilter']['coid'] = '%';
            $a = array(
                ':coid' => $_SESSION['recofilter']['coid'],
                ':date1' => $_SESSION['recofilter']['from'],
                ':date2' => $_SESSION['recofilter']['to'],
                ':t' => '1',
            );

            $query = "SELECT * FROM {ek_journal_reco_history} "
                    . "WHERE coid like :coid AND date >= :date1 AND date <= :date2 AND type=:t";
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
            $options = array();
            while ($r = $data->fetchObject()) {
                $query = "SELECT aname from {ek_accounts} WHERE aid=:a AND coid=:c";
                $aname = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':a' => $r->aid, ':c' => $r->coid))
                        ->fetchField();
                $query = "SELECT name from {ek_company} WHERE id=:id";
                $company_name = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r->coid))->fetchField();
                $url = Url::fromRoute('ek_finance_reconciliation.pdf', ['id' => $r->id])->toString();

                $report = '<a href="' . $url . '" target="_blank"  title="' . t('report') . '">' . $r->id . '</a>';

                if ($r->uri <> '') {
                    $attachment = "<a class='blue' href='" . file_create_url($r->uri) . "' target='_blank'>" . t('Attachment') . "</a>";
                } else {
                    $attachment = 'upload';
                    $param = 'upload-' . $r->id . '-statement';
                    $modal_route = Url::fromRoute('ek_finance.manage.modal_expense', ['param' => $param])->toString();
                    $attachment = t('<a href="@url" class="@c"  data-accepts=@a  >upload</a>', 
                                array('@url' => $modal_route, '@c' => 'use-ajax red', '@a' => "application/vnd.drupal-modal",));
                }
                
                $options[$r->id] = array(
                    'id' => ['data' => ['#markup' => $report]],
                    'company' => $company_name,
                    'date' => $r->date,
                    'aid' => array('data' => $r->aid . " " . $aname),
                    'attachment' => array('data' => ['#markup' => $attachment]),
                );
            }


            $build['reco_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'reco_table'),
                '#empty' => $this->t('No report available.'),
                '#attached' => array(
                    'library' => array('ek_finance/ek_finance'),
                ),
            );
        }

        return $build;
    }

    /**
     *  @return file pdf report
     *  @param id
     */
    public function pdfreconciliation(Request $request, $id) {
        $type = 3;
        $markup = array();
        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     *  @return file extract in excel format reconciliation table
     *  @param array coid, account, date
     */
    public function excelreco($param) {
        
        $markup = array();    
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
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

              $result = Database::getConnection('external_db', 'external_db')->query($query,$a);    


              $query = "SELECT * from {ek_accounts} WHERE aid=:account and coid=:coid";
              $a = array(':account' => $param['account'],':coid' => $param['coid']);
              $account = Database::getConnection('external_db', 'external_db')
                      ->query($query,$a)->fetchObject();


              if ($account->balance_date == '') $account->balance_date = 0; //todo input alert for opening balance         
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
                    ':reco'=> 1 , 
                    ':reco2' => date('Y')
                    );

                  $credit = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchField();
                  $a = array(
                    ':exc' => 0,
                    ':type' => 'debit',
                    ':aid' => $param['account'],
                    ':coid' => $param['coid'],
                    ':dateopen' => $account->balance_date,
                    ':reco'=> 1 , 
                    ':reco2' => date('Y')
                    );
                  $query = "SELECT sum(value) FROM {ek_journal} "
                          . "WHERE exchange=:exc and type=:type AND aid=:aid "
                          . "AND coid=:coid and ( (date>=:dateopen and reconcile=:reco) or reconcile=:reco2 )";

                  $debit = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchField();

                  if ($debit == NULL) $debit = 0;
                  if ($credit == NULL) $credit = 0;
                  $balance = $account->balance + $credit - $debit;
                  if ($balance < 0 ) {$ab='dt';} else {$ab='ct';}  

                  $data['openchart'] = $account->balance;
                  $data['opencredit'] = round($credit,2);
                  $data['opendebit'] = round($debit,2);            
                  $data['openbalance'] = $balance;


                // top bar displaying the total
                  $data["debits"] = round($debit,2);
                  $data["credits"] = round($credit,2);             
                  $data['balance'] = abs(round($balance,2)) . " (" . $ab . ")";
                  $data["statement"] = abs(round($balance,2)); 
                  $data['rows'] = array();
                  $i=0;
                while($r = $result->fetchObject()) {

                      $j = Journal::journalEntryDetails($r->id);
                        $row = array();
                        $row['id'] = $r->id;
                        $row['journal_id'] = $r->id;
                        $row['type'] = $j['type'];
                        $row['date'] = $j['date'];
                        $row['comment'] = $j['reference'] . " - " . $j['comment'];
                        $row['value'] = $j['value'];

                        $data['rows'][$i] = $row;
                     $i++;  

                    }//while         

            include_once drupal_get_path('module', 'ek_finance') . '/excel_reconciliation.inc';
        }
        return ['#markup' => $markup];
    }
    
}

//class