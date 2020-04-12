<?php

/**
 * @file
 * Hooks provided by the ek_address_book module.
 */
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Data per module
 * @param int $abid
 *   The id of address book.
 * @see \Drupal\ek_address_book\Controller\AddressBookController::viewaddressbook()
 * 
 */
function hook_ek_address_book_data($abid) {

    //bank details per address book
    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_bank', 'b');
    $query->fields('b');
    $query->condition('abid', $abid);
    $data = $query->execute();
    
    return $data;

    
}



/**
 * @} End of "addtogroup hooks".
 */

