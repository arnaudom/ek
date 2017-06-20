<?php

namespace Drupal\ek_finance\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;
/*
*/
/**
 * generate accounts id arrays 
 *
 * 
 *  An associative array containing:
 *  - currencies
 */
 class CurrencyList {
 
 
 public function listcurrency($type = NULL) { 
 
     if ($type == '1') {
      $condition1 = "where active=:param1";
      $a[':param1'] = 1;
    } 
    
    $query = "SELECT currency,name from {ek_currency} $condition1 order by currency";

    $options = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchAllKeyed();
    
    return $options;
 
 }
 
 
 
 }//class
