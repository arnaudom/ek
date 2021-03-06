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
function hook_ek_settings($coids)
{
    $query = Database::getConnection('external_db', 'external_db')
            ->select('module_settings', 'm');
    $query->fields('m', ['id', 'settings']);
    $query->condition('id', '1');
    $data = $query->execute()->fetchObject();
    $settings = unserialize($data->settings);

    if (empty($settings)) {
        \Drupal::messenger()->addWarning('Missing settings for module');
    }
    return new \Symfony\Component\HttpFoundation\Response('', 204);
}

/**
 * Return content for default home page
 * @see \Drupal\ek_admin\Controller\AdminController::isDefault()
 * display short message in home page; return array with
 * name: should be unique for module
 * module: module name delivering the feature
 * stamp : date or expiration
 * type : 'info' or 'new'
 * content : text to display
 * footer : footer content
 *
 */
function hook_ek_home()
{
    $build = [];
    $build['ek']['name'] = 'feature_1';
    $build['ek']['module'] = 'ek_module';
    $build['ek']['stamp'] = 1582446000;
    $build['ek']['type'] = "info";
    $build['ek']['content'] = t('New feature');
    $build['ek']['footer'] = date('Y-m-d');
    return $build;
}

/**
 * alter users list permodule
 * @param array $list
 *   array list ids,names.
 * @see \Drupal\ek_admin\Controller\AccessCheck::listUsers()
 *
 */
function hook_list_users($list)
{
    $new_list = [];
    foreach ($list as $id => $name) {
        $user = \Drupal\user\Entity\User::loadMultiple([$id]);
        $new_list[$id] = $name . " (" . implode(",", $user->getRoles()) . ")";
    }

    return ['data' => $new_list];
}

/**
 * @} End of "addtogroup hooks".
 */
