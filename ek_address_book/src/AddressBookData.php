<?php

namespace Drupal\ek_address_book;

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Interface for address book data data
 * @addresslist : list by id  or name
 * @getname : get entry name from id
 * @getid : get entry id from name
 * @geturl : return an html format url
 */
class AddressBookData {

    /**
     * Constructs a AddressBookData
     *
     * 
     */
    public function __construct() {
        
    }

    /**
     * return an array of addresses by id, name  
     * @param type 1 = client, 2 = supplier, 3 = other
     * @param status 1 or 0
     * @param category (variable) ie 'head office'
     */
    public static function addresslist($type = NULL, $status = NULL, $category = NULL) {

        if ($type == NULL) {
            $type = '%';
        }
        if ($status == NULL) {
            $status = '%';
        }
        if ($category == NULL) {
            $category = '%';
        }

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'name']);
            $query->condition('type', $type, 'LIKE');
            $query->condition('category', $category, 'LIKE');
            $query->condition('status', $status, 'LIKE');
            $query->orderBy('name');
            $options = $query->execute()->fetchAllKeyed();
            
        return $options;
    }

    /**
     * @return 
     *    name from id
     * 
     */
    public static function getname($id = NULL) {

        if ($id != NULL) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['name']);
            $query->condition('id', $id);
            $ab = $query->execute()->fetchField();
            return $ab;
        } else {
            return NULL;
        }
    }

    /**
     * @return 
     *    id from name
     *
     * 
     */
    public static function getid($name = NULL) {

        if ($name != NULL) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id']);
            $query->condition('name', $name);
            $ab = $query->execute()->fetchField();
        } else {
            return NULL;
        }
    }

    /**
     * @param int id
     *  address book id
     * @param array option
     *  formatting options
     * i.e. ['short' => 8] for 8 characters display
     * @return html string
     *    linked name from id
     *    \Drupal\ek_address_book\AddressBookData::geturl($id, $option);
     * 
     */
    public static function geturl($id = NULL, $option = NULL) {

        $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_address_book', 'ab');
        $query->fields('ab',['name']);
        $query->condition('id', $id, '=');
        $name = $query->execute()->fetchField();
        $fullname = $name;
        if (isset($option['short'])) {
            $name = substr($name, 0, $option['short']);
        }

        $link = Url::fromRoute('ek_address_book.view', array('abid' => $id))->toString();


        $render = array(
            '#theme' => 'ek_address_book_tip',
            '#items' => ['type' => 'link', 'id' => $id, 'full' => $fullname, 'link' => $link, 'short' => $name],
            '#attached' => array(
                'library' => array('ek_address_book/ek_address_boook.tip'),
            ),
        );
        return \Drupal::service('renderer')->render($render);
    }

}

// class