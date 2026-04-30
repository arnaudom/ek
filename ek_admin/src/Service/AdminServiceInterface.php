<?php

namespace Drupal\ek_admin\Service;

/**
 * Interface for AdmintService.
 */
interface AdminServiceInterface {

 /**
  * Get company name by id
  * @param mix $id
  *  company id or null
  *
  * @return array 
  *  [id => name]
 */
  public function getCompany($id);

 /**
  * Get users and their access map.
  *
  * @param mixed $uid
  *   User id or null.
  *
  * @return array
  *   List of users with roles and company/country access.
  */
  public function getUser($uid = null);

}