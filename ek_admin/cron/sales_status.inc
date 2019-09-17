<?php
/**
 * @file
 *  module . ek_admin
 *  implement sales status checks for invoice or purchases
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

try {

  $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_invoice', 'i');
            $query->fields('i');
            $query->condition('status',1,'<>');
            $query->condition('alert',1,'=');
            $data = $query->execute();
            
  $protocol = isset($_SERVER["https"]) ? 'https' : 'http';
  $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
  $weekday = date('D');
  $stamp = date('U');
  $last = $stamp - (30*24*60*60); //30 days past

while ($row = $data->fetchObject())
{

  //calculate due date
  $due = date('Y-m-d',strtotime(date("Y-m-d", strtotime($row->date) ) . "+".$row->due . ' ' . t("days") )); 
  $due = strtotime($due); 
  
    $list = array();

        if($stamp > $due) {
          $gap = ($stamp - $due)/(60*60*24);
          $line = array();
          $line['link'] = $domain . Url::fromRoute('ek_sales.invoices.list')->toString(); ;
          $line['due'] = round($gap, 0) . ' ' . t('days');
          $line['serial'] = $row->serial;
          
          $list[] = $line;
        }

  
    /*
     * notify 
     */
    if(!empty($list)) {

        $users = explode(',', $row->alert_who);

           foreach ($users as $u) {
               if (trim($u) != '') {

                      $params = array();
                      $params['type'] = 'salert';
                      $params['subject'] = t('Past due invoice');
                      $params['uid'] =  $u;
                      $params['assign'] = $u;
                      $params['data'] = $list;

                      self::send_mail($params);

               }
           } 
     }
      
}//while 
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}

try {

  
  $query = "SELECT * from {ek_sales_purchase} WHERE status<>:s AND alert = :n";
  $a = array(':s' => 1, ':n' => 1);
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query,$a);
  
  $domain = 'https://' .  $_SERVER['HTTP_HOST'] . '/';
  $weekday = date('D');
  $stamp = date('U');
  $last = $stamp - (30*24*60*60); //30 days past

while ($row = $data->fetchObject())
{

  //calculate due date
  $due = date('Y-m-d',strtotime(date("Y-m-d", strtotime($row->date) ) . "+".$row->due . ' ' . t("days") )); 
  $due = strtotime($due); 
  
    $list = array();

        if($stamp > $due) {
          $gap = ($stamp - $due)/(60*60*24);
          $line = array();
          $line['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString(); ;
          $line['due'] = round($gap, 0) . ' ' . t('days');
          $line['serial'] = $row->serial;
          
          $list[] = $line;
        }

  
    /*
     * notify 
     */
    if(!empty($list)) {

        $users = explode(',', $row->alert_who);

           foreach ($users as $u) {
               if (trim($u) != '') {

                      $params = array();
                      $params['type'] = 'salert';
                      $params['subject'] = t('Past due purchase');
                      $params['uid'] =  $u;
                      $params['assign'] = $u;
                      $params['data'] = $list;

                      self::send_mail($params);

               }
           } 
     }
      
}//while 
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}