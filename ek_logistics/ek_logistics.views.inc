<?php

/**
 * @file
 * Views interface for ek_logistics.
 */


/**
 * Implements hook_views_data().
 */

 function ek_logistics_views_data() {
    
    /**********Delivery***********/
     
    $data['ek_logi_delivery']['table']['group'] = t('ek logistics');
    $data['ek_logi_delivery']['table']['base'] = [
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek logistics delivery'),
        'help' => t('Logistics delivery data'),
        'database' => 'external_db',
        'weight' => -10,
    ];  

    $data['ek_logi_delivery']['id'] = array(
        'title' => t('Delivery unique id'),
        'help' => t('Delivery unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_delivery']['serial'] = array(
        'title' => t('Serial number'),
        'help' => t('Serial number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_delivery']['head'] = array(
        'title' => t('Header reference'),
        'help' => t('Header company unique ID'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'head', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
            'type' => 'INNER',
          ],   
    );
    $data['ek_logi_delivery']['allocation'] = array(
        'title' => t('Allocation id reference'),
        'help' => t('Data allocation to another company, id reference'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'allocation', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
          ],
    );
    $data['ek_logi_delivery']['date'] = array(
        'title' => t('Document date'),
        'help' => t('Document date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_delivery']['ddate'] = array(
        'title' => t('Document delivery date'),
        'help' => t('Document delivery date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_delivery']['title'] = array(
        'title' => t('Document title'),
        'help' => t('Document title'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_delivery']['po'] = array(
        'title' => t('Delivery order ref. number'),
        'help' => t('Delivery order ref. number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_logi_delivery']['pcode'] = array(
            'title' => t('Project code reference'),
            'help' => t('Project code reference'),
            'field' => ['id' => 'standard',],
            'sort' => ['id' => 'standard',],
            'filter' => ['id' => 'string',],
            'argument' => ['id' => 'string',],
            'relationship' => [
                'base' => 'ek_project', // The name of the table to join with
                'field' => 'pcode', // The name of the field to join with
                'id' => 'standard',
                'base field' => 'pcode',
                'label' => t('Project details linked to this delivery'),
            ],        
        );
    }
    $data['ek_logi_delivery']['client'] = array(
        'title' => t('Client'), 
        'help' => t('Client ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to delivery'),
        ],     
  
    );
    $data['ek_logi_delivery']['status'] = array(
        'title' => t('Delivery status'),
        'help' => t('0 = open, 1 = printed, 2 = invoiced, 3 = posted'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_delivery']['amount'] = array(
        'title' => t('Total value'),
        'help' => t('Total value'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_delivery']['ordered_quantity'] = array(
        'title' => t('Ordered quantity'),
        'help' => t('Ordered quantity indicator'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_delivery']['post'] = array(
        'title' => t('Post status'),
        'help' => t('Post to stock status'),
        'field' => ['id' => 'boolean','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'boolean',],
    );
    
   /**********Delivery details***********/
    $data['ek_logi_delivery_details']['table']['group'] = t('ek logistics');

    $data['ek_logi_delivery_details']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek delivered items details'),
        'help' => t('ek delivered items details'),
        'weight' => -11,
        'database' => 'external_db',
    );

    $data['ek_logi_delivery_details']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'ek_logi_delivery' => array(
          'left_field' => 'serial',
          'field' => 'serial',
          'database' => 'external_db',
        ),
    ); 
    
    $data['ek_logi_delivery_details']['itemcode'] = array(
        'title' => t('Item description or code'),
        'help' => t('Item description or item code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_items', // The name of the table to join with
            'field' => 'itemcode', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'itemcode',
            'label' => t('item details'),
          ],   
    );   
    $data['ek_logi_delivery_details']['quantity'] = array(
        'title' => t('Item quantity'),
        'help' => t('Quantity'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );     
    $data['ek_logi_delivery_details']['date'] = array(
        'title' => t('Date'),
        'help' => t('Date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );   
    $data['ek_logi_delivery_details']['amount'] = array(
        'title' => t('Line total value'),
        'help' => t('Line total value'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );    
    $data['ek_logi_delivery_details']['value'] = array(
        'title' => t('Item unit value'),
        'help' => t('Item unit value'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );  
    
    /**********Receiving & return***********/
    
    $data['ek_logi_receiving']['table']['group'] = t('ek logistics');
    $data['ek_logi_receiving']['table']['base'] = [
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek logistics receiving or returning'),
        'help' => t('Logistics receiving or returning data'),
        'database' => 'external_db',
        'weight' => -11,
    ];  

    $data['ek_logi_receiving']['id'] = array(
        'title' => t('Receipt unique id'),
        'help' => t('Receipt unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_receiving']['serial'] = array(
        'title' => t('Serial number'),
        'help' => t('Serial number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_receiving']['head'] = array(
        'title' => t('Header reference'),
        'help' => t('Header company unique ID'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'head', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
            'type' => 'INNER',
          ],   
    );
    $data['ek_logi_receiving']['allocation'] = array(
        'title' => t('Allocation id reference'),
        'help' => t('Data allocation to another company, id reference'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'allocation', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
          ],
    );
    $data['ek_logi_receiving']['date'] = array(
        'title' => t('Document date'),
        'help' => t('Document date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_receiving']['ddate'] = array(
        'title' => t('Document receipt date'),
        'help' => t('Document receipt date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_receiving']['title'] = array(
        'title' => t('Document title'),
        'help' => t('Document title'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_receiving']['do'] = array(
        'title' => t('Receipts delivery ref. number'),
        'help' => t('Receipt delivery order ref. number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_logi_receiving']['pcode'] = array(
            'title' => t('Project code reference'),
            'help' => t('Project code reference'),
            'field' => ['id' => 'standard',],
            'sort' => ['id' => 'standard',],
            'filter' => ['id' => 'string',],
            'argument' => ['id' => 'string',],
            'relationship' => [
                'base' => 'ek_project', // The name of the table to join with
                'field' => 'pcode', // The name of the field to join with
                'id' => 'standard',
                'base field' => 'pcode',
                'label' => t('Project details linked to this receipt'),
            ],        
        );
    }
    $data['ek_logi_receiving']['supplier'] = array(
        'title' => t('Supplier'), 
        'help' => t('Supplier ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to receipt'),
        ],     
  
    );
    $data['ek_logi_receiving']['status'] = array(
        'title' => t('Delivery status'),
        'help' => t('0 = open, 1 = printed, 2 = posted'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_receiving']['amount'] = array(
        'title' => t('Total value'),
        'help' => t('Total value'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_receiving']['type'] = array(
        'title' => t('Receipt type'),
        'help' => t('Type RR = receiving report, RT = returning report'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_logi_receiving']['logistic_cost'] = array(
        'title' => t('Cost indicator'),
        'help' => t('Cost indicator'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_logi_receiving']['post'] = array(
        'title' => t('Post status'),
        'help' => t('Post to stock status'),
        'field' => ['id' => 'boolean','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'boolean',],
    );
    
   /**********Returning details***********/
    $data['ek_logi_receiving_details']['table']['group'] = t('ek logistics');

    $data['ek_logi_receiving_details']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek delivered items details'),
        'help' => t('ek delivered items details'),
        'weight' => -11,
        'database' => 'external_db',
    );

    $data['ek_logi_receiving_details']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'ek_logi_receiving' => array(
          'left_field' => 'serial',
          'field' => 'serial',
          'database' => 'external_db',
        ),
    ); 
    
    $data['ek_logi_receiving_details']['itemcode'] = array(
        'title' => t('Item description or code'),
        'help' => t('Item description or item code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_items', // The name of the table to join with
            'field' => 'itemcode', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'itemcode',
            'label' => t('item details'),
          ],   
    );   
    $data['ek_logi_receiving_details']['quantity'] = array(
        'title' => t('Item quantity'),
        'help' => t('Quantity'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );     
    $data['ek_logi_receiving_details']['date'] = array(
        'title' => t('Date'),
        'help' => t('Date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );   
    $data['ek_logi_receiving_details']['amount'] = array(
        'title' => t('Line total value'),
        'help' => t('Line total value'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    ); 
    
    return $data;
 }
