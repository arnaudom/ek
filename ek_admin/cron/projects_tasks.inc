<?php
/**
 * @file
 *  module . ek_admin
 *  implement tasks alerts for project module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
try {

  $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_project_tasks', 'pt');
            $query->fields('pt');
            $query->condition('notify',0, '>');
            $query->condition('completion_rate',100, '<');
            $data = $query->execute();
            
  
  $weekday = date('D');
  $stamp = date('U');
  
  $protocol = isset($_SERVER["https"]) ? 'https' : 'http';
  $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
  
   $log = 'start projects tasks checking at:' . date('Y-m-d h:i', $stamp);
   \Drupal::logger('ek_admin')->notice( $log ); 

while ($row = $data->fetchObject())
{
    if(is_numeric($row->end)){
        $delta = round( ( $row->end  - $stamp ) / (24*60*60), 0); 

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p');
                $query->fields('p',['id']);
                $query->condition('pcode',$row->pcode, '=');
        $pid =  $query->execute()->fetchField();   


        /*
         * notify weekly , notify = 1
         */

            if($row->notify == 1 && $weekday == 'Mon'){


                $users = explode(',', $row->notify_who);

                    foreach($users as $key => $user) {
                       $params = array();
                       $params['type'] = 'task';
                       $params['subject'] = t('Weekly task alert for project');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row['pcode'];
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
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
                       $params['type'] = 'task';
                       $params['subject'] = t('Daily task alert for project');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row->pcode;
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
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
                       $params['type'] = 'task';
                       $params['subject'] = t('5 days deadline project task alert');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row->pcode;
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
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
                       $params['type'] = 'task';
                       $params['subject'] = t('3 days deadline project task alert');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row->pcode;
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
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
                       $params['type'] = 'task';
                       $params = array();
                       $params['subject'] = t('1 day deadline project task alert');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row->pcode;
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
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
                       $params['type'] = 'task';
                       $params['subject'] = t('Expired project task alert');
                       $params['uid'] =  $user;
                       $params['assign'] = $row->uid;
                       $params['serial'] = $row->pcode;
                       $params['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $pid))->toString() . '?s2=true#ps2';
                       $params['id'] = $row->id;
                       $params['alert'] = $params['link'];
                       $params['end'] = date('Y-m-d', $row->end);
                       $params['task'] = $row->task;

                       self::send_mail($params);
                    }
            }//expired
    }    
}//while 
 
  
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}