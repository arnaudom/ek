<?php

/**
 * @file
 *  module . ek_admin
 *  implement date alerts for HR module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

try {

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_hr_workforce', 'w');
            $query->fields('w',['id','name','custom_id', 'birth','contract_expiration','administrator']);
            $query->condition('active','working');
            $e = $query->execute();
    
    $stamp = date('U');

    while ($row = $e->fetchObject()) {

        //alert can only be send if administrators are set
        if($row->administrator != 0){
            
            $line = array();
            $l = Url::fromRoute('ek_hr.employee.view', ['id' => $row->id],['query' => []])->toString();
            $line['link'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $l]])->toString();
            $line['name'] = $row->name;
            $line['id'] = ($row->custom_id != '') ? $row->custom_id : $row->id;
            $uids = explode(',',$row->administrator);
            
            //contract expiration filter
            if($row->contract_expiration != NULL) {
                $params = [];
                $params['type'] = 'hr_date';
                $delta = round((strtotime($row->contract_expiration)  - $stamp ) / (24*60*60), 0); 
            
                if($delta <= 0) {
                    $params['subject'] = t('HR Contract is expired');
                    $line['date'] = $row->contract_expiration;
                    $line['status'] = t('expired contract');
                    $line['color'] = '#DC3564';
                    $params['data'][] = $line;
                }
                if($delta > 0 && ($delta = 30 || $delta = 15  )) {
                    $params['subject'] = t('HR Contract is due');
                    $line['status'] = t('contract renewal');
                    $line['color'] = '#3498db';
                    $line['date'] = $row->contract_expiration;
                    $params['data'][] = $line;
                    
                }
                
                if(isset($params['subject'])) {
                    //send mail
                    $userData = \Drupal::service('user.data');
                    foreach($uids as $k => $id){
                        // insert option per user$params['uid'] = $id;
                        if($userData->get('ek_alert_subscriptions', $id, 'hr_date_subscription') == 1){
                            $params['uid'] = $id;
                            self::send_mail($params);
                        }
                        
                    }
                    
                }
                
            }
            
            //birthday expiration filter
            if($row->birth != NULL) {
                $next = date('Y') . '-' . date('m-d', strtotime($row->birth));
                $params = array();
                $params['type'] = 'hr_date';
                $delta = round((strtotime($next) - $stamp ) / (24*60*60), 0); 
                if($delta > 0 && ($delta = 15  || $delta = 7 || $delta = 1)) {
                    $params['subject'] = t('Employee birthday');
                    $line['status'] = t('birthday date');
                    $line['color'] = '#35DC5C';
                    $line['date'] = $next;
                    $params['data'][] = $line;
                    
                }
                
                if(isset($params['subject'])) {
                    //send mail
                    foreach($uids as $k => $id){
                        $params['uid'] = $id;
                        self::send_mail($params);
                    }
                    
                }
                
            }
            
        }
        
    }
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}