<?php

namespace Drupal\ek_finance\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;

/*
TODO

*/
/**
 * generate accounts id arrays
 *
 *
 *  An associative array containing:
 *  - aid aname by coid, class
 */
 class AidList
 {
     public function listaid($coid = null, $type = array(), $status = null)
     {
         if ($status == '') {
             $status = '';
         } else {
             $status='and astatus=:astatus';
             $a[':astatus']= 1;
         }
    
         if (empty($type)) {
             $condition = "aid like :type";
             $a['type']='%';
         } else {
             $condition = 'AND (';
             $len = count($type);
             $i=0;
             foreach ($type as $t) {
                 $condition.= "aid like :type$t";
                 $a[":type$t"]="$t%";
                 if ($i <> $len - 1) {
                     $condition.= " or ";
                 }
                 $i++;
             }
             $condition.= ")";
         }
        
         $query = "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:c and coid=:coid $status $condition ORDER BY aid";
         $a[':coid'] = $coid;
         $a[':c'] = 'class';
     
         $result = Database::getConnection('external_db', 'external_db')->query($query, $a);
         $options = array();
         $b[':d'] = 'detail';
         $b[':id'] = $coid;
         if ($status == '') {
             $status = '';
         } else {
             $status='and astatus=:astatus';
             $b[':astatus'] = 1;
         }
            
         foreach ($result as $r) {
             $class=substr($r->aid, 0, 2);
             $b[':c'] = $class.'%';
            
             $query2 =  "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:d and coid=:id $status and aid like :c  ORDER BY aid";
             $result2 = Database::getConnection('external_db', 'external_db')->query($query2, $b);
             $list = array();
             foreach ($result2 as $r2) {
                 $list[$r2->aid] = $r2->aid . ' - ' . $r2->aname ;
             }
              
             $options[$r->aname] = $list;
         }
         // new JsonResponse()
         return $options;
     }
 }//class
