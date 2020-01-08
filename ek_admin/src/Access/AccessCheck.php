<?php

namespace Drupal\ek_admin\Access;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Database;

/**
 * Provide access control
 */
 class AccessCheck {
 

  /**
   * Constructs a CustomAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface
   *   The user account to check access for.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }


/**
 * make list of users
 * 
 * @param int $active status
 * @return
 *      array of users id , name
 */
 
 public static function listUsers($active = 1) {
     
    $list = [];
    $users = User::loadMultiple();
     
    foreach($users as $u){
        if($u->id() > 0) {
           if($active == 1 && $u->isActive()){
                $list[$u->id()] = $u->getDisplayName();
           } elseif($active == 0) {
                $list[$u->id()] = $u->getDisplayName();
           }
        }            
    }
    
    // Let other modules add or remove data to the list.
    if($invoke = \Drupal::moduleHandler()->invokeAll('list_users', [$list])){
       $list = $invoke['data'];
    }
     
    return $list;
     
 }

/**
 * access of users by company / entity
 * @param int $coid company id
 * @return
 *      array of users id
 */
 
 public static function GetCompanyAccess($coid = NULL) {
 
  $access = array();

  if (isset($coid) && !$coid == NULL) {
  //get array for single coid
  
  $query = "SELECT access from {ek_company} where id=:c";
  $result = Database::getConnection('external_db','external_db')->query($query, array(':c' => $coid))->fetchField();
  
    $access[$coid] = explode(",", unserialize($result));
  
  } else {
  // get full companies accesses
  
  $query = "SELECT access from {ek_company}";
  $result = Database::getConnection('external_db','external_db')->query($query);
  
    while($r = $result->fetchAssoc()) {
    
       $access[$r['coid']] = explode(",", unserialize($r['access']));

    }
  
  }
  
  return $access;
 
 }

/**
 * 
 * access of users by country
 * @param int $cid country id
 * @return
 *      array of users id 
 *
 */
 public static function GetCountryAccess($cid = NULL) {
 
  $access = array();

  if (isset($cid) && !$cid == NULL) {
  //get array for single coid
  
  $query = "SELECT access from {ek_country} where id=:c";
  $result = Database::getConnection('external_db','external_db')->query($query, array(':c' => $cid))->fetchField();
  
    $access[$cid] = explode(",", unserialize($result));

  } else {
  // get full companies accesses
  
  $query = "SELECT access from {ek_country}";
  $result = Database::getConnection('external_db','external_db')->query($query);
  
    while($r = $result->fetchAssoc()) {
    
       $access[$r['cid']] = explode(",", unserialize($r['access']));

    }
  
  }
  
  return $access;
 
 }
 
 /**
 * access to countries by current user or selected user
 * @return
 *      array of country id
 */
 
 public static function GetCountryByUser($uid = NULL) {
 
 if($uid == NULL) {
    $account = \Drupal::currentUser();
    $uid = $account->id();
 }
 
 $access = array();
 
 $data = Database::getConnection('external_db','external_db')->query("SELECT id,access from {ek_country}");
 
  while($r = $data->fetchObject()) {
    
    $list = explode(',', unserialize($r->access));
      
      if(in_array($uid, $list) ) {
        array_push($access, $r->id);
      }
  }
 
 return $access;
 }

 /**
 * access companies by current user or selected user
 * @return
 *      array of company / business entities id
 */
 
 public static function GetCompanyByUser($uid = NULL) {
 
 if($uid == NULL) {
    $account = \Drupal::currentUser();
    $uid = $account->id();
 }
 
 //add default 0 id access to avoid error when query DB with no coid access defined
 // for an user. (ie expenses list in finance)
 $access = array(0);
 
 $data = Database::getConnection('external_db','external_db')->query("SELECT id,access from {ek_company}");
 
  while($r = $data->fetchObject()) {
  
    $list = explode(',', unserialize($r->access));
      
      if(in_array($uid, $list) ) {
        array_push($access, $r->id);
      }
  }
 
 return $access; 
 
 
 }
 
 /**
 * List companies
 * @param int active
 * @return
 *      Return an array of id,name of all companies 
 *
 */
 public static function CompanyList($active = NULL) {
 
     if($active == NULL) {
        $query = "SELECT id,name from {ek_company} ORDER by name";
        $a = array();         
     } else {
        $query = "SELECT id,name from {ek_company} WHERE active=:t ORDER by name";
        $a = array(':t' => $active);
     }

  
  return Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAllKeyed();
 
 } 
 
  /**
 * List countries
 * @param int status
 * @return
 *      Return an array of id,name of all countries 
 *
 */
 public static function CountryList($status = NULL) {
 
     if($status == NULL) {
        $query = "SELECT id,name from {ek_country} ORDER by name";
        $a = array();         
     } else {
        $query = "SELECT id,name from {ek_country} WHERE status=:s ORDER by name";
        $a = array(':s' => $status);
     }

  
  return Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAllKeyed();
 
 } 
 
 /**
 * access countries by current user or selected user
 * @return
 *      Return an array of id,name of companies accessible per user
 *
 */
 public static function CompanyListByUid($uid = NULL) {
 
 $access = self::GetCompanyByUser($uid);
 
  $company = implode(',',$access);
  $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
  $a = array(':t' => 1, ':c' => $company);
  
  return Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAllKeyed();
 
 } 

 /**
 * access contries by current user or selected user
 * @return
 *      Return an array of id,name of countries accessible per user
 *
 */
 public static function CountryListByUid($uid = NULL) {
 
 $access = self::GetCountryByUser($uid);
 
  $country = implode(',',$access);
  $query = "SELECT id,name from {ek_country} where status=:t AND FIND_IN_SET (id, :c ) order by name";
  $a = array(':t' => 1, ':c' => $country);
  
  return Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAllKeyed();
 
 } 

//end class 
}