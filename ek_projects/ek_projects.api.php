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
function hook_project_view($data, $pcode)
{
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
function hook_project_doc_view($items)
{
    foreach ($items as $folder => $docs) {
        foreach ($docs as $key => $doc) {
            if ($key && $doc['pcode']) {
                $items[$folder][$key]['module_info'] = 'info';
            }
        }
    }
    return $items;
}

/**
 * Update tables data references when a file is removed from project
 * @param array $items
 *   $items['pcode'] i.e. project code
 *   $items['id'] i.e file id.
 * @see \Drupal\ek_projects\Controller\ProjectController::deleteConfirmed()
 *
 */
function hook_project_doc_delete($items)
{
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_extranet_pages', 'e');
    $query->fields('e', ['content']);
    $query->condition('pcode', $items['pcode']);
    $data = $query->execute()->fetchObject();
       
    $content = unserialize($data->content);
    $new = [];
    foreach ($content['document'] as $docId => $status) {
        if ($docId != $items['id']) {
            $new['document'][$docId] = $status;
        }
    }
       
    $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_extranet_pages')
                ->condition('pcode', $items['pcode'])
                ->fields(['content' => serialize($new)])
                ->execute();
       
    // HTTP 204 is "No content", meaning "I did what you asked and we're done."
    return new Response('', 204);
}
/**
 * @} End of "addtogroup hooks".
 */
