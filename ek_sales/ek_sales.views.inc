<?php

/**
 * @file
 * Views interface for ek_sales.
 */


/**
 * Implements hook_views_data().
 */
 function ek_sales_views_data() {
     
    $data['ek_sales_invoice']['table']['group'] = t('ek sales');
    $data['ek_sales_invoice']['table']['base'] = [
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek sales invoices'),
        'help' => t('Sales invoices data'),
        'database' => 'external_db',
        'weight' => -10,
    ];  

    $data['ek_sales_invoice']['id'] = array(
        'title' => t('Invoice unique id'),
        'help' => t('Invoice unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['serial'] = array(
        'title' => t('Serial number'),
        'help' => t('Serial number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['do_no'] = array(
        'title' => t('Delivery order ref. number'),
        'help' => t('Delivery order ref. number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['po_no'] = array(
        'title' => t('Purchase order ref. number'),
        'help' => t('Purchase order ref. number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['head'] = array(
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
    $data['ek_sales_invoice']['allocation'] = array(
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
    $data['ek_sales_invoice']['status'] = array(
        'title' => t('Payment status'),
        'help' => t('Payment status: 0 = unpaid, 1 = paid, 2 = partially paid'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['client'] = array(
        'title' => t('Client'), 
        'help' => t('Client ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to invoice'),
        ],     
  
    );
    if(\Drupal::moduleHandler()->moduleExists('ek_logistics')) {
        $data['ek_sales_invoice']['do_no'] = array(
            'title' => t('Delivery order ref. number'),
            'help' => t('Delivery order ref. number id any'),
            'field' => ['id' => 'standard',],
            'sort' => ['id' => 'standard',],
            'filter' => ['id' => 'string',],
            'argument' => ['id' => 'string',],
            'relationship' => [
                'base' => 'ek_logi_delivery', // The name of the table to join with
                'field' => 'do_no', // The name of the field to join with
                'id' => 'standard',
                'base field' => 'serial',
                'label' => t('Logisitics details per DO'),
            ],  
        );
    }
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_sales_invoice']['pcode'] = array(
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
                'label' => t('Project details linked to this invoice'),
            ],        
        );
    }
    $data['ek_sales_invoice']['type'] = array(
        'title' => t('Document type'),
        'help' => t('1 invoice, 2 commercial, 3 debit note 4 credit note 5 Proforma'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['amount'] = array(
        'title' => t('Total value in local currency'),
        'help' => t('Total value in local currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['currency'] = array(
        'title' => t('Local currency'),
        'help' => t('Currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['amountbase'] = array(
        'title' => t('Total value in base currency'),
        'help' => t('Local value converted in base currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['amountreceived'] = array(
        'title' => t('Total value paid'),
        'help' => t('Total value paid'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['tax'] = array(
        'title' => t('Tax name'),
        'help' => t('A short text'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['taxvalue'] = array(
        'title' => t('Tax value'),
        'help' => t('Tax absolute value to apply, i.e 5 => 5%'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['date'] = array(
        'title' => t('Document date'),
        'help' => t('Document date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['pay_date'] = array(
        'title' => t('Payment date'),
        'help' => t('Receipt date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['amountreceived'] = array(
        'title' => t('Total value paid'),
        'help' => t('Total value paid'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice']['due'] = array(
        'title' => t('Invoice due terms in days'),
        'help' => t('Invoice due terms in days'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['title'] = array(
        'title' => t('Document title'),
        'help' => t('Document title'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['comment'] = array(
        'title' => t('Document open comments'),
        'help' => t('Document open comments'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_invoice']['alert'] = array(
        'title' => t('Alert status'),
        'help' => t('1 on, 0 off'),
        'field' => ['id' => 'boolean','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'boolean',],
    );
    
    
    /*
     * Table invoice details
     */
    $data['ek_sales_invoice_details']['table']['group'] = t('ek sales');

    $data['ek_sales_invoice_details']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek invoiced items details'),
        'help' => t('ek invoiced items details'),
        'weight' => -11,
        'database' => 'external_db',
    );

    $data['ek_sales_invoice_details']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'ek_sales_invoice' => array(
          'left_field' => 'serial',
          'field' => 'serial',
          'database' => 'external_db',
        ),
    ); 
    
    $data['ek_sales_invoice_details']['item'] = array(
        'title' => t('Item description or code'),
        'help' => t('Item description or item code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
     $data['ek_sales_invoice_details']['itemdetail'] = array(
        'title' => t('Extended item description'),
        'help' => t('Extended item description'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );   
    $data['ek_sales_invoice_details']['value'] = array(
        'title' => t('Item unit value'),
        'help' => t('Price per unit'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );    
    $data['ek_sales_invoice_details']['quantity'] = array(
        'title' => t('Item quantity'),
        'help' => t('Quantity'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );   
    $data['ek_sales_invoice_details']['opt'] = array(
        'title' => t('Tax applied'),
        'help' => t('Select indicator to apply tax to item'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_invoice_details']['total'] = array(
        'title' => t('Item total value'),
        'help' => t('Item total invoiced value (unit value x quantity in local currency)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    ); 
    $data['ek_sales_invoice_details']['totalbase'] = array(
        'title' => t('Item total invoiced value (in base currency)'),
        'help' => t('Item total invoiced value (in base currency)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    
    /**********Purchases***********/
    
    $data['ek_sales_purchase']['table']['group'] = t('ek sales');
    $data['ek_sales_purchase']['table']['base'] = [
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek sales purchases'),
        'help' => t('Sales purchases data'),
        'database' => 'external_db',
        'weight' => -11,
    ];  

    $data['ek_sales_purchase']['id'] = array(
        'title' => t('Purchase unique id'),
        'help' => t('Purchase unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['serial'] = array(
        'title' => t('Serial number'),
        'help' => t('Serial number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['head'] = array(
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
    $data['ek_sales_purchase']['allocation'] = array(
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
    $data['ek_sales_purchase']['status'] = array(
        'title' => t('Payment status'),
        'help' => t('Payment status: 0 = unpaid/partial 1 = paid'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['client'] = array(
        'title' => t('Client'), 
        'help' => t('Client ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to purchase'),
        ],     
  
    );
    
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_sales_purchase']['pcode'] = array(
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
                'label' => t('Project details linked to this purchase'),
            ],        
        );
    }
    $data['ek_sales_purchase']['type'] = array(
        'title' => t('Document type'),
        'help' => t('1 purchase, 4 debit note'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['amount'] = array(
        'title' => t('Total value in local currency'),
        'help' => t('Total value in local currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['currency'] = array(
        'title' => t('Local currency'),
        'help' => t('Currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['amountbc'] = array(
        'title' => t('Total value in base currency'),
        'help' => t('Local value converted in base currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['amountpaid'] = array(
        'title' => t('Total value paid'),
        'help' => t('Total value paid'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['tax'] = array(
        'title' => t('Tax name'),
        'help' => t('A short text'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['taxvalue'] = array(
        'title' => t('Tax value'),
        'help' => t('absolute value to apply, i.e 5 => 5%'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['date'] = array(
        'title' => t('Document date'),
        'help' => t('Document date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['pdate'] = array(
        'title' => t('Payment date'),
        'help' => t('Payment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['amountpaid'] = array(
        'title' => t('Total value paid'),
        'help' => t('Total value paid'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase']['due'] = array(
        'title' => t('Purchase due terms in days'),
        'help' => t('Purchase due terms in days'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['title'] = array(
        'title' => t('Document title'),
        'help' => t('Document title'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['comment'] = array(
        'title' => t('Document open comments'),
        'help' => t('Document open comments'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_purchase']['uri'] = array(
        'title' => t('Attachment'),
        'help' => t('Internal link to attachment'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );  
    $data['ek_sales_purchase']['alert'] = array(
        'title' => t('Alert status'),
        'help' => t('1 on, 0 off'),
        'field' => ['id' => 'boolean','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'boolean',],
    );
    
    /*
     * Table purchase details
     */
    $data['ek_sales_purchase_details']['table']['group'] = t('ek sales');

    $data['ek_sales_purchase_details']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek purchased items details'),
        'help' => t('ek purchased items details'),
        'weight' => -10,
        'database' => 'external_db',
    );

    $data['ek_sales_purchase_details']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'ek_sales_purchase' => array(
          'left_field' => 'serial',
          'field' => 'serial',
          'database' => 'external_db',
        ),
    ); 
    
    $data['ek_sales_purchase_details']['item'] = array(
        'title' => t('Item description or code'),
        'help' => t('Item description or item code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
     $data['ek_sales_purchase_details']['itemdetail'] = array(
        'title' => t('Extended item description'),
        'help' => t('Extended item description'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );   
    $data['ek_sales_purchase_details']['value'] = array(
        'title' => t('Item unit value'),
        'help' => t('Price per unit'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );    
    $data['ek_sales_purchase_details']['quantity'] = array(
        'title' => t('Item quantity'),
        'help' => t('Quantity'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );   
    $data['ek_sales_purchase_details']['opt'] = array(
        'title' => t('Tax applied'),
        'help' => t('Select indicator to apply tax to item'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_purchase_details']['total'] = array(
        'title' => t('Purchased value'),
        'help' => t('Item total purchased value (unit value x quantity in local currency)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    ); 
    
    /**********Quotation***********/
    
    $data['ek_sales_quotation']['table']['group'] = t('ek sales');
    $data['ek_sales_quotation']['table']['base'] = [
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek sales quotations'),
        'help' => t('Sales quotations data'),
        'database' => 'external_db',
        'weight' => -11,
    ];  

    $data['ek_sales_quotation']['id'] = array(
        'title' => t('Purchase unique id'),
        'help' => t('Purchase unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_quotation']['serial'] = array(
        'title' => t('Serial number'),
        'help' => t('Serial number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['head'] = array(
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
    $data['ek_sales_quotation']['allocation'] = array(
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
    $data['ek_sales_quotation']['status'] = array(
        'title' => t('Payment status'),
        'help' => t('0 = Open, 1 = Printed, 2 = Invoiced'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_quotation']['client'] = array(
        'title' => t('Client'), 
        'help' => t('Client ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'client', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to quotation'),
        ],     
  
    );
    
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_sales_quotation']['pcode'] = array(
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
                'label' => t('Project details linked to this quotation'),
            ],        
        );
    }
    
    $data['ek_sales_quotation']['amount'] = array(
        'title' => t('Total value in local currency'),
        'help' => t('Total value in local currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_quotation']['currency'] = array(
        'title' => t('Local currency'),
        'help' => t('Currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['date'] = array(
        'title' => t('Document date'),
        'help' => t('Document date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['tax'] = array(
        'title' => t('Tax name and value'),
        'help' => t('I.e. VAT|5 => VAT 5%'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['incoterm'] = array(
        'title' => t('Incoterm name and value'),
        'help' => t('I.e. CIF|5 => CIF 5%'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['title'] = array(
        'title' => t('Document title'),
        'help' => t('Document title'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation']['comment'] = array(
        'title' => t('Document open comments'),
        'help' => t('Document open comments'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    
    /*
     * Table quotation details
     */
    $data['ek_sales_quotation_details']['table']['group'] = t('ek sales');

    $data['ek_sales_quotation_details']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek quoted items details'),
        'help' => t('ek quoted items details'),
        'weight' => -10,
        'database' => 'external_db',
    );

    $data['ek_sales_quotation_details']['table']['join'] = array(
        // Index this array by the table name to which this table refers.
        // 'left_field' is the primary key in the referenced table.
        // 'field' is the foreign key in this table.
        'ek_sales_quotation' => array(
          'left_field' => 'serial',
          'field' => 'serial',
          'database' => 'external_db',
        ),
    ); 
    
    $data['ek_sales_quotation_details']['itemid'] = array(
        'title' => t('Item description or code'),
        'help' => t('Item description or item code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation_details']['itemdetails'] = array(
        'title' => t('Extended item description'),
        'help' => t('Extended item description'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    ); 
    $data['ek_sales_quotation_details']['column_2'] = array(
        'title' => t('Custom column 2'),
        'help' => t('An optional and customisable data column'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation_details']['column_3'] = array(
        'title' => t('Custom column 3'),
        'help' => t('An optional and customisable data column'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_sales_quotation_details']['value'] = array(
        'title' => t('Item unit value'),
        'help' => t('Price per unit'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );    
    $data['ek_sales_quotation_details']['unit'] = array(
        'title' => t('Item quantity'),
        'help' => t('Quantity'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );   
    $data['ek_sales_quotation_details']['opt'] = array(
        'title' => t('Tax applied'),
        'help' => t('Select indicator to apply tax to item'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_sales_quotation_details']['total'] = array(
        'title' => t('Purchased value'),
        'help' => t('Item total quoted value (unit value x quantity in local currency)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    ); 
        
    $data['ek_sales_quotation_details']['revision'] = array(
        'title' => t('Revisiony'),
        'help' => t('Increment indicator for number of document revisions'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );   
    return $data;
 }