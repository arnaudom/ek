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
            ->select('ek_project', 'p');
            $query->fields('p',['owner']);
            $query->condition('status',0, '>');
    $condition = $query->orConditionGroup()
        ->condition('status', 'open')
        ->condition('status', 'awarded');        
     $query->condition($condition);
     $query->distinct();
     $data = $query->execute();
    
    $weekday = date('D');
    $stamp = date('U');
    $last = $stamp - (30 * 24 * 60 * 60); //30 days past

    while ($row = $data->fetchObject()) {

        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_project', 'p');
            $query->fields('p',['id','last_modified','pcode','pname']);
            $query->condition('owner',$row->owner);
            $query->condition('last_modified',$last, '<');
            $condition = $query->orConditionGroup()
                ->condition('status', 'open')
                ->condition('status', 'awarded');        
        $query->condition($condition);
        $projects = $query->execute();
        

        $list = array();

        while ($p = $projects->fetchObject()) {
            if ($p->id > 0) {
                $line = array();
                $modif = explode('|', $p->last_modified);
                $l = Url::fromRoute('ek_projects_view', ['id' => $p->id],['query' => []])->toString();
                $line['link'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $l]])->toString();
                $line['modified'] = date('Y-m-d', $modif[0]);
                $line['pcode'] = $p->pcode;

                $list[] = $line;
            }
        }

        /*
         * notify 
         */
        if (!empty($list)) {
            $params = array();
            $params['type'] = 'status';
            $params['subject'] = t('Project(s) not updated for @f days', ['@f' => 30]);
            $params['uid'] = $row->owner;
            $params['assign'] = $row->owner;
            $params['data'] = $list;

            self::send_mail($params);
        }
    }//while 
} catch (Exception $ex) {
    echo '<pre>' . $ex . '</pre>';
}