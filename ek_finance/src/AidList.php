<?php

namespace Drupal\ek_finance;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;

/**
 * generate accounts id arrays 
 *
 * 
 *  An associative array containing:
 *  - aid aname by coid, class
 *  - class + header list by company /  entity id
 *  
 */
 class AidList {


  /**
   * list accounts id from chart of accounts by class -> detail
   *
   * used in selection lists / forms
   * @param $coid = company / entity id
   * @param $type = header, class, detail
   * @param $status 1 or 0
  */ 
    public static function listaid($coid = NULL, $type = array(), $status = NULL ) { 

    if($status == '') {
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
        if ($i <> $len - 1) $condition.= " or ";
        $i++;
      }
      $condition.= ")";
    }        
        
     $query = "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:c and coid=:coid $status $condition ORDER BY aid";
     $a[':coid'] = $coid;
     $a[':c'] = 'class';
     
     $result = Database::getConnection('external_db', 'external_db')->query($query,$a);
     $options = array();
     $b[':d'] = 'detail';
     $b[':id'] = $coid; 
     if($status == '') {
      $status = '';
      } else {
      $status='and astatus=:astatus';
      $b[':astatus'] = 1;   
      }    
            
          foreach ($result as $r) {
            $class=substr($r->aid, 0, 2);
            $b[':c'] = $class.'%';
            
            $query2 =  "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:d and coid=:id $status and aid like :c  ORDER BY aid";
            $result2 = Database::getConnection('external_db', 'external_db')->query($query2,$b);
            $list = array(); 
              foreach ($result2 as $r2) {
                $list[$r2->aid] = $r2->aid . ' - ' . $r2->aname ;
              }
              
            $options[$r->aname] = $list;            
          }
    return $options;
    }
 
  /**
   * list accounts id from chart of acounts by header -> class 
   * @param $coid = company / entity id
   * @param $type = header, class, detail
   * @param $status 1 or 0   
  */  
    public static function listclass($coid = NULL, $type = array(), $status = NULL ) {
    
      if($status == '') {
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
          if ($i <> $len - 1) $condition.= " or ";
          $i++;
        }
        $condition.= ")";
      }        
          
       $query = "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:c and coid=:coid $status $condition ORDER BY aid";
       $a[':coid'] = $coid;
       $a[':c'] = 'header';
       
       $result = Database::getConnection('external_db', 'external_db')->query($query,$a);
       $options = array();
       $b[':d'] = 'class';
       $b[':id'] = $coid; 
       if($status == '') {
        $status = '';
        } else {
        $status='and astatus=:astatus';
        $b[':astatus'] = 1;   
        }    
              
            foreach ($result as $r) {
              $head=substr($r->aid, 0, 1);
              $b[':c'] = $head.'%';
              
              $query2 =  "SELECT DISTINCT aid,aname from {ek_accounts} where atype=:d and coid=:id $status and aid like :c  ORDER BY aid";
              $result2 = Database::getConnection('external_db', 'external_db')->query($query2,$b);
              $list = array(); 
                foreach ($result2 as $r2) {
                  $list[$r2->aid] = $r2->aid . ' - ' . $r2->aname ;
                }
                
              $options[$r->aname] = $list;            
            }
     
    return $options;
    
    
    }
    
  /**
   * Get aid name 
   * @param $coid = company / entity id
   * @param $aid = account
   * @Return string  
  */  
    public static function aname($coid = NULL, $aid = NULL ) {
        $query =  "SELECT aname from {ek_accounts} WHERE "
                . "aid=:d and coid=:id";
        return Database::getConnection('external_db', 'external_db')
                ->query($query,[':d' => $aid, ':id' => $coid])
                ->fetchField();
    }
    
  /**
   * List of all accounts 
   * @param coid
   * @return array [coid][aid][aname]  
  */  
    public static function chartList($coid = NULL) {
        
        $list = [];
        if($coid == NULL) {
            $query =  "SELECT * FROM {ek_accounts} ORDER by coid,aid";
            $data = Database::getConnection('external_db', 'external_db')
                ->query($query);
            
            
        } else {
           $query =  "SELECT * FROM {ek_accounts} WHERE coid=:c ORDER by coid,aid";
            $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':c' => $coid]);
            
             
        }
        While ($d = $data->fetchObject()) {
                $list[$d->coid][$d->aid] = $d->aname;
            }
            
            return $list;
        
    }    
 
 
 }//class
