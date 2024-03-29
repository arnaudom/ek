<?php
/**
 * @file
 *  module . ek_admin
 *  implement tasks alerts for sales module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
try {

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice_tasks', 't');
            $query->fields('t', ['id', 'serial','event','task','uid','start','end','completion_rate','notify','notify_who','notify_when']);
            $query->leftJoin('ek_sales_invoice', 'i', 't.serial=i.serial');
            $query->fields('i',['id']);
            $query->condition('notify',0,'<>');
            $query->condition('completion_rate',100,'<');
            $Obj = $query->execute();
  
  $weekday = date('D');
  $stamp = date('U');
  
   $log = 'start sales tasks checking at:' . date('Y-m-d h:i', $stamp);
   \Drupal::logger('ek_admin')->notice( $log ); 

while ($row = $Obj->fetchObject()) {

   $end = ($row->end == null) ? date('U') : $row->end;
   $delta = round(( $end - $stamp ) / (24 * 60 * 60), 0);
   $params = []; 
   $params['assign'] = $row->uid;
   $params['serial'] = $row->serial;
   $l = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $row->i_id],['query' => []])->toString();
   $params['link'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $l]])->toString();
   $l = Url::fromRoute('ek_sales.invoices.tasks_list', [])->toString();
   $params['alert'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $l]])->toString();
   $params['id'] = $row->i_id;
   $l = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $row->i_id],['query' => []])->toString();
   $params['end'] = date('Y-m-d', $row->end);
   $params['task'] = $row->task;
    
    /*
     * notify weekly , notify = 1
     */
     
        if($row->notify == 1 && $weekday == 'Mon'){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('Weekly task invoice alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//weekly
        
        
    /*
     * notify daily , notify = 5
     */        
        
        if($row->notify == 5 && $row->completion_rate < 100 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('Daily task invoice alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//daily       

    /*
     * notify before deadline , notify = 2, 5 days
     */        
        
        if($row->notify == 2 && $delta == 5 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('5 days deadline invoice task alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//5 days 

    /*
     * notify before deadline , notify = 3, 3 days
     */        
        
        if($row->notify == 3 && $delta == 3 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('3 days deadline invoice task alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//3 days         
        
     /*
     * notify before deadline , notify = 4, 1 days
     */        
        
        if($row->notify == 4 && $delta == 1 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('1 day deadline invoice task alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//3 days        
  
     /*
     * notify expired tasks
     */        
        
        if($row->end < $stamp ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params['type'] = 'task';
                   $params['subject'] = t('Expired task invoice alert');
                   $params['uid'] =  $user;
                   self::send_mail($params);
                }
        }//expired
        
}
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}