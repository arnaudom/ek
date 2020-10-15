<?php

/**
 * @file
 * Views interface for ek_projects.
 */

/**
 * Implements hook_views_data().
 */
function ek_projects_views_data() {

    /*     * ********Project main********** */
    $data['ek_project']['table']['group'] = t('ek projects');
    $data['ek_project']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek projects main table'),
        'help' => t('Projects main table'),
        'database' => 'external_db',
        'weight' => -11,
    );
    $data['ek_project']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'database' => 'external_db',
        'ek_project_description' => array(
            'left_field' => 'pcode',
            'field' => 'pcode',
            'database' => 'external_db',
        ),
        'ek_project_finance' => array(
            'left_field' => 'pcode',
            'field' => 'pcode',
            'database' => 'external_db',
        ),
    );
    $data['ek_project']['id'] = array(
        'title' => t('Main project unique id'),
        'help' => t('Main project unique id'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['pname'] = array(
        'title' => t('Name'),
        'help' => t('Project given name'),
        'field' => ['id' => 'standard', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['client_id'] = array(
        'title' => t('Client id reference'),
        'help' => t('Reference from address book table'),
        'relationship' => array(
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client_id', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book ref'),
        ),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['cid'] = array(
        'title' => t('Country id'),
        'help' => t('Reference from country table'),
        'relationship' => array(
            'base' => 'ek_country', // The name of the table to join with            
            'id' => 'standard',
            'label' => t('Countries table'),
            'handler' => 'views_handler_relationship',
            'field' => 'cid',
            'base field' => 'id',
        ),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['date'] = array(
        'title' => t('Creation date'),
        'help' => t('Creation date'),
        'field' => ['id' => 'date',],
        'sort' => ['id' => 'date',],
        'filter' => ['id' => 'date',],
    );
    $data['ek_project']['category'] = array(
        'title' => t('Project category'),
        'help' => t('Reference from project categories table'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => array(
            'base' => 'ek_project_type', // The name of the table to join with            
            'id' => 'standard',
            'label' => t('Categories table'),
            'handler' => 'views_handler_relationship',
            'field' => 'category',
            'base field' => 'id',
        ),
    );
    $data['ek_project']['pcode'] = array(
        'title' => t('Project code'),
        'help' => t('Project description'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['status'] = array(
        'title' => t('Status'),
        'help' => t('Open, awarded, completed, closed'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['level'] = array(
        'title' => t('Level'),
        'help' => t('Main or sub project'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['subcount'] = array(
        'title' => t('Number of sub projects'),
        'help' => t('Number of sub projects'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['priority'] = array(
        'title' => t('Priority level'),
        'help' => t('0 to 3'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['owner'] = array(
        'title' => t('Project owner'),
        'help' => t('User id from users table'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
            /* relation to user should be limited to users_field_data.name only
             * @TODO

              'relationship' => array(
              'base' => 'users_field_data', // The name of the table to join with
              'field' => 'owner', // The name of the field to join with
              'base field' => 'uid',
              'id' => 'standard',
              'label' => t('Authenticated user'),
              ), */
    );
    $data['ek_project']['last_modified'] = array(
        'title' => t('Last edition stamp'),
        'help' => t('Unix timestamp'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project']['share'] = array(
        'title' => t('List of users with share privileges'),
        'help' => t('Comma separated list of user id from users table'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['deny'] = array(
        'title' => t('List of users with blocked access'),
        'help' => t('Comma separated list of user id from users table'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['notify'] = array(
        'title' => t('List of users with update request'),
        'help' => t('Comma separated list of user id from users table'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project']['archive'] = array(
        'title' => t('Archive status'),
        'help' => t('0 or 1'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    
    /*     * ********Project description********** */
    $data['ek_project_description']['table']['group'] = t('ek projects');

    $data['ek_project_description']['table']['base'] = array(
        'field' => 'pcode', // This is the identifier field for the view.
        'title' => t('ek projects description'),
        'help' => t('ek projects description data.'),
        'weight' => -10,
        'database' => 'external_db',
    );
    $data['ek_project_description']['pcode'] = array(
        'title' => t('Project code in description'),
        'help' => t('Project code in description'),
        'relationship' => array(
            'base' => 'ek_project', // The name of the table to join with
            'field' => 'pcode', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Description data of project'),
        ),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['submission'] = array(
        'title' => t('Submission date'),
        'help' => t('i.e. date of proposal'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['deadline'] = array(
        'title' => t('Deadline date'),
        'help' => t('i.e. completion deadline date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['start_date'] = array(
        'title' => t('Start date'),
        'help' => t('i.e. start date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['validation'] = array(
        'title' => t('validation date'),
        'help' => t('i.e. validation date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['completion'] = array(
        'title' => t('Completion date'),
        'help' => t('i.e. completion date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['project_description'] = array(
        'title' => t('Description'),
        'help' => t('Description text'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['project_comment'] = array(
        'title' => t('Comments'),
        'help' => t('Comments text'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_description']['supplier_offer'] = array(
        'title' => t('Suppliers list'),
        'help' => t('List of suppliers ids'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );



    /*     * ********Project logistics********** */
    $data['ek_project_description']['table']['group'] = t('ek projects');

    $data['ek_project_shipment']['table']['base'] = array(
        'field' => 'pcode', // This is the identifier field for the view.
        'title' => t('ek projects logistics'),
        'help' => t('ek projects logistics data.'),
        'weight' => -10,
        'database' => 'external_db',
    );
    $data['ek_project_description']['pcode'] = array(
        'title' => t('Project code in logistics'),
        'help' => t('Project code in logistics'),
        'relationship' => array(
            'base' => 'ek_project', // The name of the table to join with
            'field' => 'pcode', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Description data of project'),
        ),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['first_ship'] = array(
        'title' => t('1st shipment'),
        'help' => t('1st shipment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['second_ship'] = array(
        'title' => t('2nd shipment'),
        'help' => t('2nd shipment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['third_ship'] = array(
        'title' => t('3rd shipment'),
        'help' => t('3rd shipment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['four_ship'] = array(
        'title' => t('4th shipment'),
        'help' => t('4th shipment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );    
    $data['ek_project_finance']['ship_status'] = array(
        'title' => t('Shipment status'),
        'help' => t('Shipment status'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );    
    $data['ek_project_finance']['last_delivery'] = array(
        'title' => t('Last shipment'),
        'help' => t('Last shipment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );



    /*     * ********Project finance********** */
    $data['ek_project_finance']['table']['group'] = t('ek projects');

    $data['ek_project_finance']['table']['base'] = array(
        'field' => 'pcode', // This is the identifier field for the view.
        'title' => t('ek projects finance'),
        'help' => t('ek projects finance data'),
        'weight' => -10,
        'database' => 'external_db',
    );
    $data['ek_project_finance']['pcode'] = array(
        'title' => t('Project code in finance'),
        'help' => t('Project description in finance'),
        'relationship' => array(
            'base' => 'ek_project', // The name of the table to join with
            'field' => 'pcode', // The name of the field to join with
            'id' => 'standard',
            'label' => t('finance data of project'),
        ),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    $data['ek_project_finance']['payment_terms'] = array(
        'title' => t('Payment terms'),
        'help' => t('Payment terms'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['currency'] = array(
        'title' => t('Currency'),
        'help' => t('Currency'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    $data['ek_project_finance']['purchase_value'] = array(
        'title' => t('Purchase value'),
        'help' => t('Purchase value'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project_finance']['tender_offer'] = array(
        'title' => t('Proposed value'),
        'help' => t('Proposed value'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project_finance']['project_amount'] = array(
        'title' => t('Agreed value'),
        'help' => t('Agreed value'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project_finance']['discount_offer'] = array(
        'title' => t('Discount value'),
        'help' => t('Discount value'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project_finance']['down_payment'] = array(
        'title' => t('Downpayment value'),
        'help' => t('Downpayment value'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_project_finance']['payment'] = array(
        'title' => t('Payment'),
        'help' => t('Payment'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_project_finance']['offer_validity'] = array(
        'title' => t('Offer validity date'),
        'help' => t('Offer validity'),
        'field' => ['id' => 'date',],
        'sort' => ['id' => 'date',],
        'filter' => ['id' => 'date',],
    );
    $data['ek_project_finance']['offer_deadline'] = array(
        'title' => t('Offer deadline date'),
        'help' => t('Offer validity'),
        'field' => ['id' => 'date',],
        'sort' => ['id' => 'date',],
        'filter' => ['id' => 'date',],
    );

    /*     * ********Project type********** */
    $data['ek_project_type']['table']['group'] = t('ek projects');

    $data['ek_project_type']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek projects types'),
        'help' => t('ek projects type description'),
        'weight' => -10,
        'database' => 'external_db',
    );


    $data['ek_project_type']['id'] = array(
        'title' => t('Category unique id'),
        'help' => t('Category unique id'),
        'field' => ['id' => 'numeric', 'click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => array(
            'base' => 'ek_project', // The name of the table to join with
            'field' => 'category', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Main project'),
        ),
    );

    $data['ek_project_type']['type'] = array(
        'title' => t('Category value'),
        'help' => t('Category value'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
    );


    $data['ek_project_type']['short'] = array(
        'title' => t('Category short name'),
        'help' => t('Category short name'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
    );

    $data['ek_project_type']['comment'] = array(
        'title' => t('Category comment'),
        'help' => t('Category comment'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
    );

    $data['ek_project_type']['gp'] = array(
        'title' => t('Category group'),
        'help' => t('Category group'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
    );


    return $data;
}
