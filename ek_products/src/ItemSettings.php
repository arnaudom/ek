<?php

/**
 * @file
 * Contains \Drupal\ek_products\ItemSettings.
 *
 */

namespace Drupal\ek_products;

use Drupal\Core\Database\Database;

/**
 * Set and retrieve gloabal settings parameters
 */
class ItemSettings {

    public function __construct($uid = null) {
        $data = Database::getConnection('external_db', 'external_db')
                ->query("SELECT id,settings FROM {ek_item_settings} WHERE id=:id", [':id' => 0])
                ->fetchObject();

        $this->settings = unserialize($data->settings);
    }

    /**
     * Get a setting by setting name
     */
    public function get($name) {
        if (!empty($this->settings)) {
            return $this->settings[$name];
        }
    }

    /**
     * Set a setting value by setting name
     */
    public function set($name, $value) {
        $this->settings[$name] = $value;
    }

    /**
     * save settings
     */
    public function save() {
        $data = serialize($this->settings);
        if (Database::getConnection('external_db', 'external_db')
                        ->update('ek_item_settings')
                        ->condition('id', 0)
                        ->fields(array(
                            'settings' => $data,
                        ))
                        ->execute()) {
            return true;
        }
    }

}
