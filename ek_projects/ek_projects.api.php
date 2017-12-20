<?php

/**
 * @file
 * Hooks provided by the ek_projects module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the data available in project page display.
 * @param array $data
 *   The project data.
 * @param string $pcode
 *   The project reference.
 * @see \Drupal\ek_projects\Controller\ProjectController::project_view()
 * 
 */
function hook_project_view($data, $pcode) {
    
    $data['project'][0]->my_module =  'data';
    
    return $data;
  
}

/**
 * Alter the data available in project documents list in page display.
 * @param array $items
 *   The project items list.
 * 
 * @see \Drupal\ek_projects\Controller\ProjectController::periodicalupdater()
 * 
 */
function hook_project_doc_view($items) {
  foreach($items as $key => $doc) {
            if($key && $doc['pcode']){
                $items[$key]['module_info'] = 'info';
            }
        }
    return $items;
    
}
/**
 * @} End of "addtogroup hooks".
 */