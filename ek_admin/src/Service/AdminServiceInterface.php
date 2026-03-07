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

}