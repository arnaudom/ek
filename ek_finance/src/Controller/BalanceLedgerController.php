<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\BalanceLedgerController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_finance\Journal;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Controller routines for ek module routes.
 */
class BalanceLedgerController extends ControllerBase {
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
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  Finance ledger by account and date
     * 
     *  @return array
     *      rendered Html
     *
     */
    public function ledgerbalance(Request $request) {

        $items['filter_ledger'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterLedger');
        $items['data'] = array();
        $journal = new Journal();


        if (isset($_SESSION['lfilter']['filter']) && $_SESSION['lfilter']['filter'] == 1) {

            $param = array(
                'coid' => $_SESSION['lfilter']['coid'],
                'aid1' => $_SESSION['lfilter']['account_from'],
                'aid2' => $_SESSION['lfilter']['account_to'],
                'date1' => $_SESSION['lfilter']['from'],
                'date2' => $_SESSION['lfilter']['to'],
                'type' => 'accounts'
            );

            $items['data'] = $journal->ledger($param);
            $excel = Url::fromRoute('ek_finance.extract.excel-ledger', array('param' => serialize($param)), array())->toString();

            $items['excel'] = "<a href='" . $excel . "' >" . t('Excel') . "</a>";
        }

        return array(
            '#theme' => 'ek_finance_ledger',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.ledger'),),
        );
    }

    /**
     * Finance ledger by account and date in excel format
     * 
     * @param array $param
     *  array of exctration filters
     *  for 'accounts'
     *  int coid, int aid1, int aid2, string date1, 
     *  string date2, string type (accounts)
     * 
     *  for 'sales'
     *  int coid, array references (array of client's id),
     *  int source1 ('purchase|invoice'), int source2 ('payment|receipt'), 
     *  string date1, string date2,string type ('sales')
     * 
     * @return Object
     *  PhpExcel object
     */
    public function excelledger($param = NULL) {

        $markup = array();
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $p = unserialize($param);
            $company = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT name from {ek_company} WHERE id=:id', array(':id' => $p['coid']))
                    ->fetchField();

            if ($p['type'] == 'accounts') {
                include_once drupal_get_path('module', 'ek_finance') . '/excel_ledger.inc';
            } elseif ($p['type'] == 'sales') {
                include_once drupal_get_path('module', 'ek_finance') . '/excel_sales_ledger.inc';
            }
        }
        return ['#markup' => $markup];
    }

    /**
     *  Sales and purchases report per client/vendor account
     *  Require the sales module
     *  @param string $id
     *      supplier|client
     *  @return array html display
     */
    public function sales(RouteMatchInterface $route_match, Request $request) {

        $path = $route_match->getRouteObject()->getPath();

        $path = explode('/', $path);
        $id = $path[3];
        $items = [];

        if (isset($_SESSION['salesledger']['route']) && $_SESSION['salesledger']['route'] != $id) {
            $_SESSION['salesledger']['client'] = NULL;
            $_SESSION['salesledger']['filter'] = 0;
        }

        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterSales', $id);

        if (isset($_SESSION['salesledger']['filter']) && $_SESSION['salesledger']['filter'] == 1) {

            $journal = new Journal();

            if ($id == 'purchase') {
                $entity = 'purchase';
                $book = 2;
                $source2 = 'payment';
                $source3 = 'purchase dn';
                $link = 'ek_sales.purchases.print_html';

                if ($_SESSION['salesledger']['client'] == '%') {
                    $clients = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT DISTINCT ab.id FROM {ek_address_book} ab "
                                    . "INNER JOIN {ek_sales_purchase} p ON p.client = ab.id order by name")
                            ->fetchCol();
                } else {
                    $clients = [0 => $_SESSION['salesledger']['client']];
                }
            } else {
                $entity = 'invoice';
                $book = 1;
                $source2 = 'receipt';
                $source3 = 'invoice cn';
                $link = 'ek_sales.invoices.print_html';

                if ($_SESSION['salesledger']['client'] == '%') {
                    $clients = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT DISTINCT ab.id FROM {ek_address_book} ab "
                                    . "INNER JOIN {ek_sales_invoice} i ON i.client = ab.id order by name")
                            ->fetchCol();
                } else {
                    $clients = [0 => $_SESSION['salesledger']['client']];
                }
            }
            $items['book'] = \Drupal\ek_address_book\AddressBookData::addresslist($book);
            $items['data'] = [];
            
            foreach ($clients as  $clientId) {
                //select the documents ids related to the selected client
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_' .$entity, 's');

                $result = $query->fields('s', array('id'))
                        ->condition('s.client', $clientId, '=')
                        ->condition('head', $_SESSION['salesledger']['coid'], '=')
                        ->execute();

                $ids = $result->fetchCol();
                $row = [];
            
                $row['client_id'] = $clientId;
                $row['client_name'] = $items['book'][$clientId];

                if (!empty($ids)) {
 
                    $param = [
                        'coid' => $_SESSION['salesledger']['coid'],
                        'references' => $ids,
                        'source1' => $entity,
                        'source2' => $source2,
                        'source3' => $source3,
                        'date1' => $_SESSION['salesledger']['from'],
                        'date2' => $_SESSION['salesledger']['to'],
                    ];


                    $row['journal'] = $journal->salesledger($param);
                    
                    $items['data'][] = $row;
                
                }
                
                
            }

            $param['type'] = 'sales';
            $param['client'] = $_SESSION['salesledger']['client'];
            $param['references'] = '';

            $excel = Url::fromRoute('ek_finance.extract.excel-ledger', array('param' => serialize($param)), array())->toString();
            $items['excel'] = "<a href='" . $excel . "' >" . t('Excel') . "</a>";


            return array(
                '#theme' => 'ek_finance_sales_ledger',
                '#items' => $items,
                '#attached' => array(
                    'library' => array('ek_finance/ek_finance.ledger'),),
            );
        } else {
            return $items['form'];
        }
    }

}
