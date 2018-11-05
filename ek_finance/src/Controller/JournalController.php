<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\JournalController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_finance\Journal;

/**
 * Controller routines for ek module routes.
 */
class JournalController extends ControllerBase {
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
     * Constructs a JournalController object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  Display journal entries filter by date and company
     * 
     * @return array
     *  render Html
     *
     */
    public function journal(Request $request) {

        $items = array();
        $items['filter_journal'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterJournal');
        $items['data'] = array();

        //todo filter by module
        $folders = array('general', 'expense', 'receipt', 'payroll', 'invoice', 'pos', 'purchase', 'payment');

        // todo , 'inventory'

        $journal = new Journal();


        if (isset($_SESSION['jfilter']) && $_SESSION['jfilter']['filter'] == 1) {
            
            if(isset( $_SESSION['jfilter']['jid']) &&  $_SESSION['jfilter']['jid'] != "") {
                //retrieve data by journal id
                $details = $journal->journalEntryDetails($_SESSION['jfilter']['jid']);
              
                if($details['id'] == '') {
                    $items['#markup'] = "<div class='messages messages--warning'>" 
                             . t('No data available')
                             . '</div>';
                     return $items;
                }
                
                $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
                 if(in_array($details['coid'], $access)) {
                     $items['data'] = $journal->data_by_jid($_SESSION['jfilter']['jid']);
                     
                     return array(
                        '#theme' => 'ek_finance_journal_by_id',
                        '#items' => $items,
                        '#attached' => array(
                            'library' => array('ek_finance/ek_finance', 'ek_finance/ek_finance.dialog'),
                        ),
                    );
                     
                 } else {
                     //no access
                     $query = "SELECT name from {ek_company} WHERE id=:id";
                     $name = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':id' => $details['coid']])
                        ->fetchField();
                    
                    $buil['type'] = 'access';
                    $build['message'] = ['#markup' => t('Denied access for @e to @p', ['@e' => $name, '@p' => \Drupal::currentUser()->getUsername()])];
                    $items['alert'] = [
                        '#items' => $build,
                        '#theme' => 'ek_admin_message',
                        '#attached' => array(
                            'library' => array('ek_admin/ek_admin_css'),
                        ),
                        '#cache' => ['max-age' => 0,],
                    ]; 
                     return $items;
                 }
     
                
            
            } else {
                foreach ($folders as $folder) {
                    $data = array();

                    $data[$folder] = $journal->display(array(
                        'date1' => $_SESSION['jfilter']['from'],
                        'date2' => $_SESSION['jfilter']['to'],
                        'company' => $_SESSION['jfilter']['coid'],
                        'edit' => 0,
                        'source' => $folder
                            )
                    );

                    $items['data'] += $data;
                }

                $param = serialize(
                        array(
                            'date1' => $_SESSION['jfilter']['from'],
                            'date2' => $_SESSION['jfilter']['to'],
                            'company' => $_SESSION['jfilter']['coid']
                        )
                );

                $excel = Url::fromRoute('ek_finance.extract.excel-journal', array('param' => $param), array())->toString();
                $items['excel'] = "<a href='" . $excel . "' >" . t('Excel') . "</a>";
            }
        }

        return array(
            '#theme' => 'ek_finance_journal',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance', 'ek_finance/ek_finance.dialog'),
            ),
        );
    }

    /**
     * Extract journal in excel format filter by date and company
     * 
     * @param array $param
     *  serialized array
     *  keys : date1 (string), date2 (string), company (int, company id) 
     * @return Object
     *  PhpExcel object download
     *
     */
    public function exceljournal($param = NULL) {
        $markup = array();    
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);
            $markup = array();
            include_once drupal_get_path('module', 'ek_finance') . '/excel_journal.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * Extract transaction history of given account and period
     * 
     * @param array $param
     *  aid = account id
     *  coid = company id
     *  from = from date
     *  to = to date
     * @return array
     *  render Html
     *
     */
    public function history($param) {

        $journal = new Journal();
        $history = $journal->history($param);
        return array(
            '#theme' => 'ek_journal_history',
            '#items' => unserialize($history),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
        );
    }

}

//class