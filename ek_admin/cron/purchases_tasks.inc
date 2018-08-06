<?php
/**
 * @file
 *  module . ek_admin
 *  implement tasks alerts for sales module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
try {

  
  $query = "SELECT t.id,t.serial,t.event,t.uid,t.task,t.start,t.end,t.completion_rate,t.notify,t.notify_who,t.notify_when,p.id as pid "
          . "FROM {ek_sales_purchase_tasks} t "
          . "Left JOIN {ek_sales_purchase} p "
          . "ON t.serial=p.serial "
          . "WHERE t.notify<>:n "
          . "AND completion_rate<:c";
  $a = array(':n' => 0, ':c' => 100);
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query,$a);
  
  $weekday = date('D');
  $day = date('j');
  $stamp = date('U');
  $protocol = isset($_SERVER["https"]) ? 'https' : 'http';
  $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
  
   $log = 'start purchases tasks checking at:' . date('Y-m-d h:i', $stamp);
   \Drupal::logger('ek_admin')->notice( $log );

while ($row = $data->fetch())
{

    $delta = round( ( $row->end  - $stamp ) / (24*60*60), 0); 
    /*
     * notify weekly , notify = 1
     */
     
        if($row->notify == 1 && $weekday == 'Mon'){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('Weekly task purchase alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//weekly
        
        
    /*
     * notify daily , notify = 5
     */        
        
        if($row->notify == 5 && $row->completion_rate < 100 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('Daily task purchase alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//daily       

    /*
     * notify before deadline , notify = 2, 5 days
     */        
        
        if($row->notify == 2 && $delta == 5 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('5 days deadline purchase task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//5 days 

    /*
     * notify before deadline , notify = 3, 3 days
     */        
        
        if($row->notify == 3 && $delta == 3 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('3 days deadline purchase task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//3 days         
        
     /*
     * notify before deadline , notify = 4, 1 days
     */        
        
        if($row->notify == 4 && $delta == 1 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('1 day deadline purchase task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//3 days        
  
     /*
     * notify expired tasks
     */        
        
        if($row->end < $stamp ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('Expired task purchase alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->serial;
                   $params['link'] = $domain . Url::fromRoute('ek_sales.purchases.list')->toString();
                   $params['id'] = $row->pid;
                   $params['alert'] = $domain . Url::fromRoute('ek_sales.purchases.task', array('id' => $row->pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//expired
        
}//while 
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}