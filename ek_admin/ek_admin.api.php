<?php

/**
 * @file
 * Hooks provided by the ek_admin module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Check settings available per module
 * @param array $coids
 *   The companies ids list.
 * @see \Drupal\ek_admin\Controller\AdminController::Admin()
 * 
 */
function hook_ek_settings($coids) {
    
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('module_settings', 'm');
    $query->fields('m', ['id', 'settings']);
    $query->condition('id', '1');
    $data = $query->execute()->fetchObject();
    $settings = unserialize($data->settings);
    
    if(empty($settings)) {
        drupal_set_message('Missing settings for module', 'warning');
    }
    return new \Symfony\Component\HttpFoundation\Response('', 204);
}


/**
 * @} End of "addtogroup hooks".
 */

