<?php

namespace Drupal\ek_address_book\Service;

/**
 * Interface for AddressBookService.
 */
interface AddressBookServiceInterface {

/**
 * Get main address book data by request.
 *  all (no query)|id|name|tag|type|
 *
 * @param array $options
 *   The query options.
 *
 * @return array
 *   Main book data.
 */
  public function getMain(array $options = []);

/**
 * Get contact address book data by request.
 *  all (no query)|abid|name|id
 *
 * @param array $options
 *   The query options.
 *
 * @return array
 *   Contact data.
 */
  public function getContact(array $options = []);

/**
 * Record address book.
 * Route example:
 * - POST /api/v1/ab/record/main
 * - POST /api/v1/ab/record/contact
 * @param string $table
 *   main | contact
 * @param serialized $data
 * Optional legacy route param:
 * - /api/v1/ab/record/{table}/{data}
 * @return id
 */
  public function record(string $table, string $data);


/**
 * Update address book.
 *
 * @param string $table
 *   main | contact
 * @param serialized $data
 * @param $id : updating id
 * @return id
 */
  public function update(string $table, $id, string $data);

}