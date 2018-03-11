<?php

/**
* @file
* Contains \Drupal\ek_finance\Controller\CashController.
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
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;

/**
* Controller routines for ek module routes.
*/
class CashController extends ControllerBase {

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
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('module_handler')
    );
  }

  /**
   * Constructs a CashController object.
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
 *  manage currencies, set active and default exchange rate
 *
 *  @return array
 *      form  
*/
  public function currencies(Request $request) {
  
    //clear currency session 
    unset($_SESSION['activeCurrencies']);
    $build['currency'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\Currencies'); 
    return $build;
  }

/**
 *  extract balance per account type and company
 *  
 *  @return array
 *      render Html
*/
  public function cashbalance(Request $request) {
  
    $items['filter_cash'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterCash'); 
    
    if(isset($_SESSION['cfilter']['filter'])) {
        $items['settings'] = array();
        $aid = NULL;
        $settings = new FinanceSettings(); 
        if($_SESSION['cfilter']['type'] == '0'){
          //account is company
            $companysettings = new CompanySettings($_SESSION['cfilter']['account']);
            $aid = $companysettings->get('cash_account', $_SESSION['cfilter']['currency'] );
            $aid2 = $companysettings->get('cash2_account', $_SESSION['cfilter']['currency'] );
        } else {
            $aid = db_query('SELECT name from {users_field_data} WHERE uid=:u', 
                    array(':u' => $_SESSION['cfilter']['account']))
                            ->fetchfield();
            $aid2 = '';
        }
        
        $items['settings']['baseCurrency'] = $settings->get('baseCurrency');
        $items['from'] = $_SESSION['cfilter']['from'];
        $items['to'] = $_SESSION['cfilter']['to'];
        $items['aid'] = $aid;
        $items['currency'] = $_SESSION['cfilter']['currency'];
        
        $filter = ['type' => $_SESSION['cfilter']['type'], 
                    'account' => $_SESSION['cfilter']['account'],
                    'from' => $_SESSION['cfilter']['from'],
                    'to' => $_SESSION['cfilter']['to'],
                    'currency' => $_SESSION['cfilter']['currency'],
                    'baseCurrency' => $items['settings']['baseCurrency'],
                    'aid' => $aid,
                    'aid2' => $aid2,
                    ];

        $data = $this->extract($filter);
        $items['filter'] = $data['filter'];
        $items['data'] = $data['data'];
        $items['total'] = $data['total'];

        $param = urlencode(serialize($filter));
        $items['excel'] = Url::fromRoute('ek_finance.extract.excel-cash', ['param' => $param] )->toString();
    }
    
    return array(
      '#theme' => 'ek_finance_cash',
      '#items' => $items,
      '#attached' => array(
          'library' => array('ek_finance/ek_finance'),
      ),
    );

  
  }

/**
 * export cash balance in excel format 
 * 
 * @param array $param
 *  serialized array of keys
 *  type (bool, 0 company, 1 user), account (coid or uid), from (date string), 
 *  to (date string),currency (code string), baseCurrency (code string),
 *  aid (chart accounts account int value)
 * @return Object
 *  Fpdf download object
 *
*/
  public function excelcash($param) {
  
    $markup = array();    
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            //$settings = new FinanceSettings(); 
            //$data['baseCurrency'] = $settings->get('baseCurrency');
            $parameters = unserialize(urldecode($param));
            $extract = $this->extract($parameters);
            
            $data['from'] = $_SESSION['cfilter']['from'];
            $data['to'] = $_SESSION['cfilter']['to'];
            $data['filter'] = $extract['filter']; 
            $data['data'] = $extract['data'];
            $data['total'] = $extract['total'];
            include_once drupal_get_path('module', 'ek_finance') . '/excel_cash.inc';
        }
    return ['#markup' => $markup];
  }

/**
 * extract cash data filtered
 * 
 * @param array $filter
 * array of keys
 *  type (bool, 0 company, 1 user), account (coid or uid),from (date string), to (date string),
 *  currency (code string), baseCurrency (code string),
 *  aid, aid2 (chart accounts account int value)
 * 
 * @return array
 *  extracted data
*/
  private function extract($filter) {
   
      
        if($filter['type'] == '0') {
        //company cash transactions    
            $account1 = 0;
            $account2 = 'n/a';
            $company = $filter['account'];
            $items['filter']['type'] = 0;
            $items['filter']['username'] = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT name FROM {ek_company} WHERE id=:id', [':id' => $company])
                    ->fetchField();
            $account_list = AidList::chartList($company);
            
        } else {
        //user cash transactions
            $account_list = [];
            $account1 = $filter['account'];
            $account2 = db_query('SELECT name from {users_field_data} WHERE uid=:u', array(':u' => $filter['account']))
                            ->fetchfield();
            $company = '%';    
            $items['filter']['type'] = 1;
            $items['filter']['username'] = $account2;
        }
            
            /*
             * Cash movements in cash table
             */
            $query = "SELECT * from {ek_cash} WHERE type=:t AND coid like :c "
                    . "AND currency = :cu AND uid like :u "
                    . "AND pay_date >= :p1 AND pay_date<= :p2";
              $a = array(
                ':t' => 'Credit',
                ':c' => $company,
                ':cu' => $filter['currency'],
                ':u' => $account1,
                ':p1' => $filter['from'],
                ':p2' => $filter['to'],
              );   
           
              $data1 = Database::getConnection('external_db', 'external_db')->query($query, $a);

              $a = array(
                ':t' => 'Debit',
                ':c' => $company,
                ':cu' => $filter['currency'],
                ':u' => $account1,
                ':p1' => $filter['from'],
                ':p2' => $filter['to'],
              );    

              $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);

            /*
             * Cash movements from expenses
             */
            $query = "SELECT id,company,type,localcurrency,currency,amount,pdate,comment FROM {ek_expenses} "
                    . "WHERE company like :c AND cash=:y AND status=:s AND currency = :cu "
                    . "AND employee=:e AND pdate >= :p1 AND pdate <= :p2";

              $a = array(
                ':c' => $company , 
                ':y' => 'Y',
                ':s' => 'paid',
                ':cu' => $filter['currency'],
                ':e' => $account2,
                ':p1' => $filter['from'],
                ':p2' => $filter['to'],
              );
              $data3 = Database::getConnection('external_db', 'external_db')->query($query, $a);

            /*
             * Cash movements from journal general entries
            */
            if($filter['type'] == '0') {
                $or = db_or();
                $or->condition('j.aid', $filter['aid'], '=');
                $or->condition('j.aid', $filter['aid2'], '=');
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->fields('j');
                $query->condition('j.coid', $company,'=')
                        ->condition('j.source','general', '=')
                        ->condition('j.currency', $filter['currency'], '=')
                        ->condition($or)
                        ->condition('j.date', $filter['from'], '>=')
                        ->condition('j.date', $filter['to'], '<=')
                        ->condition('j.type', 'debit', '=')
                        ->condition('j.exchange', 0);
         
                $data4 = $query->execute();

                $or = db_or();
                $or->condition('j.aid', $filter['aid'], '=');
                $or->condition('j.aid', $filter['aid2'], '=');
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->fields('j');
                $query->condition('j.coid', $company,'=')
                        ->condition('j.source','general', '=')
                        ->condition('j.currency', $filter['currency'], '=')
                        ->condition($or)
                        ->condition('j.date', $filter['from'], '>=')
                        ->condition('j.date', $filter['to'], '<=')
                        ->condition('j.type', 'credit', '=')
                        ->condition('j.exchange', 0);
         
                $data5 = $query->execute();

            } 
              
          $data = array();
          $thisrow = array();
          $total_debit_amount=0;
          $total_debit_base=0;
          $total_credit_amount=0;
          $total_credit_base=0;  

              while ($row = $data1->fetchAssoc()) {
                  //process cash table
                $comment = str_replace("&","and",$row['comment']);
                $comment = str_replace("'","",$comment);
                $month = explode("-", $row['pay_date']);
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 2, 'id' => $row['id']] )->toString();

                $voucher = '<a href="'. $url .'" target="_blank"  title="'. t('voucher') .'">'. $row['id'] .'</a>';
                $total_credit_amount += $row['amount'];
                $total_credit_base += $row['cashamount'];    

                    $thisrow['voucher'] = $voucher;  
                    $thisrow['class'] = "cash credit $month[1]";    
                    $thisrow['id'] = $row['id'];
                    $thisrow['op'] = 'credit';        
                    $thisrow['type'] = 'Cash credit';        
                    $thisrow['amount'] = $row['amount'];
                    $thisrow['currency'] = $row['currency'];        
                    $thisrow['basecurrency'] = $row['cashamount'];
                    $thisrow['date'] = $row['pay_date'];
                    $thisrow['comment'] = $comment;
                    $data[] = $thisrow;


              }

              while ($row = $data2->fetchAssoc() ) {
                  //process cash table
                $comment = str_replace("&","and",$row['comment']);
                $comment = str_replace("'","",$comment);
                $month = explode("-", $row['pay_date']); 
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 2, 'id' => $row['id']] )->toString();

                $voucher = '<a href="'. $url .'" target="_blank"  title="'. t('voucher') .'">'. $row['id'] .'</a>';
                $total_debit_amount += $row['amount'];
                $total_debit_base += $row['cashamount'];  

                    $thisrow['voucher'] = $voucher;  
                    $thisrow['class'] = "cash debit $month[1]";         
                    $thisrow['id'] = $row['id'];
                    $thisrow['op'] = 'debit';        
                    $thisrow['type'] = 'Cash debit';         
                    $thisrow['amount'] = $row['amount'];
                    $thisrow['currency'] = $row['currency'];                
                    $thisrow['basecurrency'] = $row['cashamount'];
                    $thisrow['date'] = $row['pay_date'];
                    $thisrow['comment'] = $comment;
                    $data[] = $thisrow;


              }     

              while ($row = $data3->fetchAssoc() ) {
                //process expenses
                //$query = "SELECT aname from {ek_accounts} WHERE aid=:a and coid=:c";
                //$a = array(':a' => $row['type'], ':c' => $row['company']);
                //$type = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

                $comment = str_replace("&","and",$row['comment']);
                $comment = str_replace("'","",$comment); 
                $month = explode("-", $row['pdate']);  
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 1, 'id' => $row['id']] )->toString();

                $voucher = '<a href="'. $url .'" target="_blank"  title="'. t('voucher') .'">'. $row['id'] .'</a>';
                $total_debit_amount += $row['localcurrency'];
                $total_debit_base += $row['amount']; 

                    $thisrow['voucher'] = $voucher; 
                    $thisrow['class'] = "expense debit $month[1]";         
                    $thisrow['id'] = $row['id'];
                    $thisrow['op'] = 'debit';        
                    $thisrow['type'] = $account_list[$row['company']][$row['type']];         
                    $thisrow['amount'] = $row['localcurrency'];
                    $thisrow['currency'] = $row['currency'];                
                    $thisrow['basecurrency'] = $row['amount'];
                    $thisrow['date'] = $row['pdate'];
                    $thisrow['comment'] = $comment;
                    $data[] = $thisrow;


              }
            if($filter['type'] == '0') {  
              while ($row = $data4->fetchAssoc() ) {
                  //process general journal (debit accounting = credit user)
                $comment = str_replace("&","and",$row['comment']);
                $comment = str_replace("'","",$comment); 
                $exchange = 0;
               
                if($filter['baseCurrency'] != $row['currency']) {
                    //get exchange value
                    $query = "SELECT value FROM {ek_journal} "
                    . "WHERE coid like :c AND source = :s AND aid = :aid AND reference = :r "
                    . "AND type = :t AND date = :p1 AND exchange = :ex";

                    $a = array(
                      ':c' => $row['coid'] , 
                      ':s' => 'general',
                      ':aid' => $row['aid'],
                      ':r' => $row['reference'],
                      ':t' => 'debit',
                      ':p1' => $row['date'],
                      ':ex' => 1,
                    );
                    $exchange = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)
                            ->fetchField();

                }
                    $total_credit_amount += $row['value'];
                    $total_credit_base += $row['value'] + $exchange; 

                    $thisrow['voucher'] = $row['id']; 
                    $thisrow['class'] = "general credit $month[1]";         
                    $thisrow['id'] = $row['id'];
                    $thisrow['op'] = 'credit';        
                    $thisrow['type'] = $account_list[$row['coid']][$row['aid']];         
                    $thisrow['amount'] = $row['value'];
                    $thisrow['currency'] = $row['currency'];                
                    $thisrow['basecurrency'] = $row['value'] + $exchange;
                    $thisrow['date'] = $row['date'];
                    $thisrow['comment'] = $comment;
                    $data[] = $thisrow;                  
              }
              
              while ($row = $data5->fetchAssoc() ) {
                  //process general journal (credit)
                $comment = str_replace("&","and",$row['comment']);
                $comment = str_replace("'","",$comment); 
                $month = explode("-", $row['date']); 
                $exchange = 0;
               
                if($filter['baseCurrency'] != $row['currency']) {
                    //get exchange value
                    $query = "SELECT value FROM {ek_journal} "
                    . "WHERE coid like :c AND source = :s AND aid = :aid AND reference = :r "
                    . "AND type = :t AND date = :p1 AND exchange = :ex";

                    $a = array(
                      ':c' => $row['coid'] , 
                      ':s' => 'general',
                      ':aid' => $row['aid'],
                      ':r' => $row['reference'],
                      ':t' => 'credit',
                      ':p1' => $row['date'],
                      ':ex' => 1,
                    );
                    $exchange = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)
                            ->fetchField();

                }
                    $total_debit_amount += $row['value'];
                    $total_debit_base += $row['value'] + $exchange; 

                    $thisrow['voucher'] = $row['id']; 
                    $thisrow['class'] = "general debit $month[1]";         
                    $thisrow['id'] = $row['id'];
                    $thisrow['op'] = 'debit';        
                    $thisrow['type'] = $account_list[$row['coid']][$row['aid']];         
                    $thisrow['amount'] = $row['value'];
                    $thisrow['currency'] = $row['currency'];                
                    $thisrow['basecurrency'] = $row['value'] + $exchange;
                    $thisrow['date'] = $row['date'];
                    $thisrow['comment'] = $comment;
                    $data[] = $thisrow;                   
              }
            }  
            
            //compile balance from 1st day of the year
            //for each source
           
            //cash table
            $year = explode('-', $filter['from']);
             $query = "SELECT sum(amount) as a, sum(cashamount) as b FROM {ek_cash} "
                     . "WHERE type=:t and coid=:c and uid=:u AND currency = :cu "
                     . "AND pay_date < :p1 AND pay_date >= :p2";
              $a = array(
                ':t' => 'Credit',
                ':c' => $filter['account'],
                ':u' => $account1,
                ':cu' => $filter['currency'],
                ':p1' => $filter['from'],
                ':p2' => $year[0].'-01-01',
              );
              
              $result = Database::getConnection('external_db', 'external_db')
                      ->query($query, $a)
                      ->fetchObject(); 
              $open_credit = $result->a;
              $open_credit_base = $result->b;

            // $query = "SELECT sum(amount) as a, sum(cashamount) as b FROM {ek_cash} "
            //         . "WHERE type=:t and coid=:c and uid=:u and pay_date < :p1 AND pay_date >= :p2";
              $a = array(
                ':t' => 'Debit',
                ':c' => $filter['account'],
                ':u' => $account1,
                ':cu' => $filter['currency'],
                ':p1' => $filter['from'],
                ':p2' => $year[0].'-01-01',
              );
     
             $result = Database::getConnection('external_db', 'external_db')
                     ->query($query, $a)
                     ->fetchObject();
             $open_debit = $result->a;
             $open_debit_base = $result->b;

            //expenses table
            $query = "SELECT sum(localcurrency) as a, sum(amount) as b FROM {ek_expenses} "
                    . "WHERE company=:c AND cash=:y AND status=:s AND currency = :cu "
                    . "AND employee=:e AND pdate < :p1 AND pdate >= :p2";

              $a = array(
                ':c' => $filter['account'],
                ':y' => 'Y',
                ':s' => 'paid',
                ':cu' => $filter['currency'],
                ':e' => $account2,
                ':p1' => $filter['from'],
                ':p2' => $year[0].'-01-01',
              );
             $result = Database::getConnection('external_db', 'external_db')
                     ->query($query, $a)->fetchObject();
             $open_debit += $result->a;
             $open_debit_base += $result->b;
            
             //journal 
             //Warning: debit and credit are alternate for user point of view
             //filter only general record to avoid double values
             $journal = new Journal();
             $history = unserialize( $journal->history(serialize(
                    ['aid' => $filter['aid'],
                     'source' => 'general',
                    'coid' => $company,
                    'from' => $year[0].'-01-01',
                    'to' => date('Y-m-d', strtotime('-1 day', strtotime($filter['from'])))
                     ]
                     )));
            
             $open_credit += $history['total_debit'];
             $open_credit_base += $history['total_debit_exchange'];
             $open_debit += $history['total_credit'];
             $open_debit_base += $history['total_credit_exchange'];


            $items['total'] = array();
            $items['total']['year'] = $year[0];
            $items['total']['credit_open'] = $open_credit;
            $items['total']['debit_open'] = $open_debit;
            $items['total']['credit_open_base'] = $open_credit_base;
            $items['total']['debit_ope_base'] = $open_debit_base;        
            $items['total']['credit'] = $total_credit_amount;
            $items['total']['debit'] = $total_debit_amount;
            $items['total']['base'] = $total_credit_base - $total_debit_base;
            $items['total']['balance'] = $total_credit_amount + $open_credit - $total_debit_amount - $open_debit;
            $items['total']['balance_base'] = $total_credit_base +$open_credit_base - $total_debit_base - $open_debit_base;
            
            //group and sort data by date for display
            $items['data'] = array();
            $b = [];
            foreach($data as $k => $v) {
              $b[$k] = strtolower($v['date']);
            }
            asort($b);
            foreach($b as $key => $val) {
              $items['data'][] = $data[$key];
              
            }
            
            return $items;
    
  }
  
/**
 *  debit or credit an account type
 * 
 *  @return array
 *      form
 *
*/
  public function cashmanage(Request $request) {
  
  $build['manage_cash'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ManageCash'); 
  return $build;
  
  }



}