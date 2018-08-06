<?php
/**
 * @file
 *  module . ek_admin
 *  implement tasks alerts for project module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
try {

  
  $query = "SELECT * FROM {ek_project_tasks} WHERE notify<>:n AND completion_rate < :r";
  $a = array(':n' => 0, ':r' => 100);
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query,$a);
  
  
  $weekday = date('D');
  $stamp = date('U');
  
  $protocol = isset($_SERVER["https"]) ? 'https' : 'http';
  $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
  
   $log = 'start projects tasks checking at:' . date('Y-m-d h:i', $stamp);
   \Drupal::logger('ek_admin')->notice( $log ); 

while ($row = $data->fetchObject())
{

    $delta = round( ( $row->end  - $stamp ) / (24*60*60), 0); 
    $query = "SELECT id from {ek_project} where pcode=:p";
    $pid =  Database::getConnection('external_db', 'external_db')
                ->query($query,array(':p' => $row->pcode))
                ->fetchField();
    
  
    /*
     * notify weekly , notify = 1
     */
     
        if($row->notify == 1 && $weekday == 'Mon'){
            
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('Weekly task alert for project');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row['pcode'];
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//weekly
        
        
    /*
     * notify daily , notify = 5
     */        
        
        if($row->notify == 5 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('Daily task alert for project');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->pcode;
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
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
                   $params['subject'] = t('5 days deadline project task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->pcode;
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
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
                   $params['subject'] = t('3 days deadline project task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->pcode;
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//3 days         
        
     /*
     * notify before deadline , notify = 4, 1 day
     */        
        
        if($row->notify == 4 && $delta == 1 ){
            
            $users = explode(',', $row->notify_who);
            
                foreach($users as $key => $user) {
                   $params = array();
                   $params['subject'] = t('1 day deadline project task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->pcode;
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
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
                   $params['subject'] = t('Expired project task alert');
                   $params['uid'] =  $user;
                   $params['assign'] = $row->uid;
                   $params['serial'] = $row->pcode;
                   $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString();
                   $params['id'] = $row->id;
                   $params['alert'] = $params['link'];
                   $params['end'] = date('Y-m-d', $row->end);
                   $params['task'] = $row->task;
                
                   self::send_mail($params);
                }
            
        
                    
        }//expired
        
}//while 
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}