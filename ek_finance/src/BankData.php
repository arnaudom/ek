<?php

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/*
*/
/**
 * Interface for bank lists and data
 * used in forms lists
 *
 */
 class BankData
 {
 
 
   /**
   * Constructs a BankData.
   *
   *
   */
     public function __construct()
     {
     }
 
     /**
     * return an array of bank accounts sorted by their id ref.
     * @param $coid = company / entity id
     * @param $currency = currency code i.e. 'USD'
     *
     */
     public static function listbankaccountsbyaid($coid = null, $currency = null, $active = 1)
     {
         $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_bank_accounts', 'a');
         $query->leftJoin('ek_bank', 'b', 'a.bid = b.id');
         $query->fields('a', ['id','account_ref', 'currency']);
         $query->fields('b', ['coid', 'name']);
         $query->condition('coid', $coid, '=');
         $query->condition('aid', "", '<>');
         $query->condition('active', $active, '=');
 
         if ($currency != null) {
             $query->condition('currency', $currency, '=');
         }
    
         $data = $query->execute();
    
         $options = array();
         while ($r = $data->fetchObject()) {
             $company = Database::getConnection('external_db', 'external_db')
        ->query("SELECT name from {ek_company} WHERE id=:id", array(':id' => $r->coid))
        ->fetchField();
             $options[$r->id] = "[" . $r->currency . "], " . $company . " - " . $r->account_ref . " " . $r->name;
         }
   
         return $options;
     }
 

     /**
     * return an array of banks filtered by company / entity id access
     *
     * used in form lists
     */
     public static function listBank()
     {
  
    //get the access to companies / entity by the current user first
         $company = AccessCheck::GetCompanyByUser();
         $company = implode(',', $company);
    
         //filter database by access and build a options list
         $query = "SELECT b.id, b.name as bank, c.name as co FROM {ek_bank} b INNER JOIN {ek_company} c ON b.coid=c.id WHERE FIND_IN_SET(coid, :c ) ORDER by b.name";
         $list = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $company));
         $options = array();
         while ($l = $list->fetchObject()) {
             $options[$l->id] = $l->bank . ' - ' . $l->co;
         }

         return $options;
     }
 
     /**
        * return value of currency by aid for given coid
        * @param $coid = company / entity id
        * @param $aid = account id i.e. 12001
        * @return string or null
        */
     public static function currencyByaid($coid = null, $aid = null)
     {
         $query = "SELECT currency from {ek_bank_accounts} "
            . "INNER JOIN {ek_bank} ON ek_bank_accounts.bid = ek_bank.id "
            . "WHERE coid=:c and aid=:a";
         $a = array(':c'=> $coid, ':a' =>'', ':a' => $aid);
    
         $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
    
         return $data->fetchField();
     }
 }
