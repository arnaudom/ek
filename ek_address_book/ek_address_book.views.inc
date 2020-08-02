<?php

/**
 * @file
 * Views interface for ek_address_book.
 */


/**
 * Implements hook_views_data().
 */
function ek_address_book_views_data() {
  
  $data['ek_address_book']['table']['group'] = t('ek address book');

  // Define this as a base table – a table that can be described in itself by
  // views (and not just being brought in as a relationship). 
  $data['ek_address_book']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek address book'),
    'help' => t('address book data, i.e. client or supplier'),
    'database' => 'external_db',
    'weight' => -10,
  );

  // This table references the {_contacts} table. The declaration below creates an
  // 'implicit' relationship to the _contacts table, so that when '_contacts' is the base
  // table, the fields are automatically available.
  $data['ek_address_book']['table']['join'] = array(
    // Index this array by the table name to which this table refers.
    // 'left_field' is the primary key in the referenced table.
    // 'field' is the foreign key in this table.
    'database' => 'external_db',
    'ek_address_book_contacts' => [
      'left_field' => 'abid',
      'field' => 'id',
      'database' => 'external_db',
    ],
      
  );

  $data['ek_address_book']['id'] = array(
    'title' => t('Address book id'),
    'help' => t('Book entry unique id'),
    'relationship' => [
      'base' => 'ek_address_book_contacts', // The name of the table to join with
      'field' => 'abid', // The name of the field to join with
      'id' => 'standard',
      'label' => t('Contact in this address book entry'),
    ],
      'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  );

  $data['ek_address_book']['name'] = array(
    'title' => t('Name'),
    'help' => t('Book entry name, i.e. company name'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book']['reg'] = array(
    'title' => t('Registration'),
    'help' => t('An administrative registration No.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );
  
  $data['ek_address_book']['shortname'] = array(
    'title' => t('Short name'),
    'help' => t('A short name or code used in reference Nos.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book']['address'] = array(
    'title' => t('Address line 1'),
    'help' => t('Address first line'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );  
  
  $data['ek_address_book']['address2'] = array(
    'title' => t('Address line 2'),
    'help' => t('Address second line'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );  

  $data['ek_address_book']['postcode'] = array(
    'title' => t('Postcode'),
    'help' => t('Address postcode'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );  

  $data['ek_address_book']['state'] = array(
    'title' => t('State'),
    'help' => t('Address state'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );  
  $data['ek_address_book']['city'] = array(
    'title' => t('City'),
    'help' => t('Cddress city'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'id' => 'string',
    ),
    'argument' => array(
      'id' => 'string',
    ),
  );   

  $data['ek_address_book']['country'] = array(
    'title' => t('Country'),
    'help' => t('Address country'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ); 

  $data['ek_address_book']['telephone'] = array(
    'title' => t('Telephone'),
    'help' => t('Main phone No.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ); 


  $data['ek_address_book']['fax'] = array(
    'title' => t('Fax'),
    'help' => t('Main fax No.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ); 

  $data['ek_address_book']['website'] = array(
    'title' => t('Website'),
    'help' => t('Website'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );
  
  $data['ek_address_book']['type'] = array(
    'title' => t('type'),
    'help' => t('type: 1 client, 2 supplier, 3 other'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  );   
      
      
  $data['ek_address_book']['category'] = array(
    'title' => t('Category'),
    'help' => t('1 = Head office 2 = Store 3 = Factory  4 = Other'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  );       

  $data['ek_address_book']['activity'] = array(
    'title' => t('Tags'),
    'help' => t('Tags, labels attached to this book entry'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );   

  $data['ek_address_book']['status'] = array(
    'title' => t('Status'),
    'help' => t('1 = active 0 = non active'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  );       

  //timestamp field.
  $data['ek_address_book']['stamp'] = array(
    'title' => t('Timestamp field'),
    'help' => t('Unix timestamp for last modification'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,      
    ],
    'sort' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
  );

    /**********Comments*************/
  
  $data['ek_address_book_comment']['table']['group'] = t('ek address book');
  $data['ek_address_book_comment']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek address book notes'),
    'help' => t('Notes for address book entry'),
    'weight' => -10,
    'database' => 'external_db',
  );

  $data['ek_address_book_comment']['table']['join'] = array(
    // Index this array by the table name to which this table refers.
    // 'left_field' is the primary key in the referenced table.
    // 'field' is the foreign key in this table.
    'ek_address_book' => array(
      'left_field' => 'id',
      'field' => 'abid',
      'database' => 'external_db',
    ),
  );

  //  ID table field.
  $data['ek_address_book_comment']['abid'] = array(
    'title' => t('Address book note main ID reference'),
    'help' => t('Address book note main ID reference'),
       'field' => [
       'id' => 'numeric',
       'click sortable' => TRUE,
    ],
       'sort' => [
       'id' => 'standard',
    ],
       'filter' => [
       'id' => 'numeric',
    ],
  );  

  $data['ek_address_book_comment']['comment'] = array(
    'title' => t('Address book note'),
    'help' => t('Address book note / comments'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );     
    /**********Name cards***********/
  
  $data['ek_address_book_contacts']['table']['group'] = t('ek address book');
  $data['ek_address_book_contacts']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek address book contacts'),
    'help' => t('Contacts for address book entry'),
    'weight' => -10,
    'database' => 'external_db',
  );

  $data['ek_address_book_contacts']['table']['join'] = array(
    // Index this array by the table name to which this table refers.
    // 'left_field' is the primary key in the referenced table.
    // 'field' is the foreign key in this table.
    'ek_address_book' => array(
      'left_field' => 'id',
      'field' => 'abid',
      'database' => 'external_db',
    ),
  );

  //  ID table field.
  $data['ek_address_book_contacts']['id'] = array(
    'title' => t('Contact card book id'),
    'help' => t('Contact book contact id'),
       'field' => [
       'id' => 'numeric',
       'click sortable' => TRUE,
    ],
       'sort' => [
       'id' => 'standard',
    ],
       'filter' => [
       'id' => 'numeric',
    ],
  );
  
  $data['ek_address_book_contacts']['abid'] = array(
    'title' => t('Main address book id'),
    'help' => t('Main address book id reference'),
    'relationship' => [
      'base' => 'ek_address_book', // The name of the table to join with
      'field' => 'id', // The name of the field to join with
      'id' => 'standard',
      'label' => t('Main address book id'),
    ],
       'field' => [
       'id' => 'numeric',
    ],
       'sort' => [
       'id' => 'standard',
    ],
       'filter' => [
       'id' => 'numeric',
    ],
  );

  $data['ek_address_book_contacts']['contact_name'] = array(
    'title' => t('Contact name'),
    'help' => t('Contact name'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['salutation'] = array(
    'title' => t('Salutation'),
    'help' => t('Contact salutation, i.e. Mr. Mrs.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['title'] = array(
    'title' => t('Title'),
    'help' => t('Contact job title'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['telephone'] = array(
    'title' => t('Telephone'),
    'help' => t('Contact telephone'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['mobilephone'] = array(
    'title' => t('Mobile No.'),
    'help' => t('Contact mobile No.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['email'] = array(
    'title' => t('Contact email'),
    'help' => t('Contact email'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['card'] = array(
    'title' => t('Contact name card image'),
    'help' => t('Contact name card image uri'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['department'] = array(
    'title' => t('Department'),
    'help' => t('Contact working department'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['link'] = array(
    'title' => t('Social media'),
    'help' => t('Contact social media link'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );

  $data['ek_address_book_contacts']['comment'] = array(
    'title' => t('Contact note'),
    'help' => t('Contact open note'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  );
  
  $data['ek_address_book_contacts']['stamp'] = array(
    'title' => t('Contact timestamp field'),
    'help' => t('Contact Unix timestamp for last modification'),
    'field' => array(
      'id' => 'date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'id' => 'date',
    ),
    'filter' => array(
      'id' => 'date',
    ),
  );

  return $data;
  
}