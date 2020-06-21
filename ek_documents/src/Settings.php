<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Settings.
 *
 */

namespace Drupal\ek_documents;

use Drupal\Core\Database\Database;

/**
 * Set and retrieve global settings parameters
 */
class Settings {

    public function __construct($uid = null) {
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_document_settings', 'd')
                    ->fields('d',['id','settings'])
                    ->condition('id', 0)
                    ->execute();
        
        if($data = $query->fetchObject()) {
            $this->settings = unserialize($data->settings);
        } else {
            $this->settings = [];
        }
        
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
                        ->update('ek_document_settings')
                        ->condition('id', 0)
                        ->fields(array(
                            'settings' => $data,
                        ))
                        ->execute()) {
            return true;
        }
    }

}
