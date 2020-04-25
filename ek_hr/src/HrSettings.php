<?php

/**
 * @file
 * Contains \Drupal\ek_admin\CompanySettings.
 *
 */

namespace Drupal\ek_hr;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

/**
 * Set and retrieve settings parameters used in accounts
 */
  class HrSettings
  {


  /**
   * company id.
   *
   *
   */
      protected $coid;
      /**
       * The database connection to use.
       *
       * @var \Drupal\Core\Database\Connection
       */
      protected $connection;


      public function __construct($coid = null)
      {
          $this->coid = $coid;
          $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_settings', 's')
                ->fields('s')
                ->condition('coid', $this->coid, '=')
                ->execute();
          $data = $query->fetchObject();
          $this->HrAd = isset($data->ad) ? unserialize($data->ad) : null;
          $this->HrCat = isset($data->cat) ? unserialize($data->cat) : null;
          $this->HrParam = isset($data->param) ? unserialize($data->param) : null;
          $this->HrAccounts = isset($data->accounts) ? unserialize($data->accounts) : null;
          $this->HrRoster = isset($data->roster) ? unserialize($data->roster) : null;
      }
 
      /**
       * Get setting values by name
       *
       * @vparam setting = setting name
       * @param key key of array
       */
      public function get($setting, $key, $data = null)
      {
          switch ($setting) {
    
      case 'ad':
      if ($data == null) {
          return $this->HrAd[$this->coid][$key];
      } else {
          return $this->HrAd[$this->coid][$key][$data];
      }
      break;
      
      case 'cat':
      if ($data == null) {
          return $this->HrCat[$this->coid][$key];
      } else {
          return $this->HrCat[$this->coid][$key][$data];
      }
      break   ;

      case 'param':
      //i.e key = 'fund_1', data = ['name','description']
      //i.e key = 'fund_2', data = ['name','value']
      if ($data == null) {
          return $this->HrParam[$key];
      } else {
          return $this->HrParam[$key][$data[0]][$data[1]];
      }
      break ;

      case 'accounts':
      if ($data == null) {
          return $this->HrAccounts[$this->coid][$key];
      } else {
          return $this->HrAccounts[$this->coid][$key][$data];
      }
      break ;
      case 'roster':
      if ($data == null) {
          return $this->HrRoster[$this->coid][$key];
      } else {
          return $this->HrRoster[$this->coid][$key][$data];
      }
      break ;
    }
      }

      /**
       * Set setting values by name
       *
       * @param setting = setting name
       * @param key
       * @param array, string or int value
       */
      public function set($setting, $key, $value)
      {
          switch ($setting) {
    
      case 'ad':
        return  $this->HrAd[$this->coid][$key] = $value;
      break;
      
      case 'cat':
        return $this->HrCat[$this->coid][$key] = $value;
      break;

      case 'param':
        return $this->HrParam[$key][$value[0]]['value'] = $value[1];
      break  ;

      case 'accounts':
        return $this->HrAccounts[$this->coid][$key] = $value;
      break;
      case 'roster':
        return $this->HrRoster[$this->coid][$key] = $value;
      break;
    }
      }

      /**
       * save settings values
       *
       */
      public function save()
      {
          /**/
          $save = Database::getConnection('external_db', 'external_db')->update('ek_hr_workforce_settings')
      ->condition('coid', $this->coid)
      ->fields(array(
        'ad' => serialize($this->HrAd) ,
        'cat' => serialize($this->HrCat) ,
        'param' => serialize($this->HrParam) ,
        'accounts' => serialize($this->HrAccounts) ,
        'roster' => serialize($this->HrRoster) ,
      ))
      ->execute();
  
      
          if ($save) {
              return true;
          }
      }
  }
