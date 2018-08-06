<?php

/**
 * @file
 *  module . ek_admin
 *  implement tasks alerts for project module
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

try {


    $query = "SELECT distinct owner from {ek_project} where status=:s or status=:t";
    $a = array(':s' => 'open', ':t' => 'awarded');
    $data = Database::getConnection('external_db', 'external_db')
            ->query($query, $a);

    $protocol = isset($_SERVER["https"]) ? 'https' : 'http';
    $domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
    $weekday = date('D');
    $stamp = date('U');
    $last = $stamp - (30 * 24 * 60 * 60); //30 days past

    while ($row = $data->fetchObject()) {

        $query = "SELECT id, last_modified ,pcode,pname FROM {ek_project} "
                . "where owner=:o "
                . "AND (status=:s or status=:t) "
                . "AND last_modified < :l";

        $a = array(':o' => $row->owner, ':s' => 'open', ':t' => 'awarded', ':l' => $last);
        $projects = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);


        $list = array();

        while ($p = $projects->fetchObject()) {
            if ($p->id > 0) {
                $line = array();
                $modif = explode('|', $p->last_modified);
                $line['link'] = $domain . Url::fromRoute('ek_projects_view', array('id' => $p->id))->toString();
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