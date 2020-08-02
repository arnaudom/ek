<?php

/**
 * @file
 * Views interface for ek_products.
 */



/**
 * Implements hook_views_data().
 */

function ek_products_views_data() {

  /**********Items***********/
  $data['ek_items']['table']['group'] = t('ek items');
  $data['ek_items']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services'),
    'help' => t('ek products & services contains main items data.'),
    'weight' => -10,
    'database' => 'external_db',
  );

  $data['ek_items']['table']['join'] = array(
    // Index this array by the table name to which this table refers.
    // 'left_field' is the primary key in the referenced table.
    // 'field' is the foreign key in this table.
    'ek_item_prices' => [
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ],
    'ek_item_barcodes' => [
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ],    
    'ek_item_packing' => [
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ], 
    'ek_item_images' => [
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ],    
    'ek_item_price_history' => [
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ],      
  );

  //  ID table field.
  $data['ek_items']['id'] = array(
    'title' => t('Item id'), 
    'help' => t('item unique row id'), 
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_items']['coid'] = array(
    'title' => t('a company id ref'), 
    'help' => t('company id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
    'relationship' => array(
      'base' => 'ek_company', // The name of the table to join with
      'field' => 'id', // The name of the field to join with
      'id' => 'standard',
      'label' => t('Company ref'),
    ),       
  );
  $data['ek_items']['type'] = array(
    'title' => t('Item type'), 
    'help' => t('An item tag used for itemcode'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['itemcode'] = array(
    'title' => t('Item code'), 
    'help' => t('Item code = type + number'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['description1'] = array(
    'title' => t('Item description'), 
    'help' => t('Item plain description'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );

  $data['ek_items']['description2'] = array(
    'title' => t('item formatted description'), 
    'help' => t('item formatted description'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['supplier_code'] = array(
    'title' => t('Item code from supplier'), 
    'help' => t('Item code from supplier - option'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['active'] = array(
    'title' => t('Item status'),
    'help' => t('0 =  non active, 1 = active'),
    'field' => ['id' => 'boolean','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'boolean',],
  );
  $data['ek_items']['collection'] = array(
    'title' => t('Item collection'), 
    'help' => t('Item tag = collection'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['department'] = array(
    'title' => t('Item department'), 
    'help' => t('Item tag = department'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['family'] = array(
    'title' => t('Item family'), 
    'help' => t('Item tag  =  family'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['size'] = array(
    'title' => t('Item size'), 
    'help' => t('Item size description'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['color'] = array(
    'title' => t('Item color'), 
    'help' => t('Item color description'), 
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_items']['supplier'] = array(
    'title' => t('Item supplier ID'), 
    'help' => t('Item supplier ID address book'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',], 
    'relationship' => array(
      'base' => 'ek_address_book', // The name of the table to join with
      'field' => 'id', // The name of the field to join with
      'id' => 'standard',
      'label' => t('Supplier ref'),
    ),   
  );


  /**********Items packing***********/
  $data['ek_item_packing']['table']['group'] = t('ek items');
  $data['ek_item_packing']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services items packing'),
    'help' => t('contains items packing'),
    'weight' => -10,
    'database' => 'external_db',
  );
  $data['ek_item_packing']['table']['join'] = array(
    'ek_items' => array(
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ),
  );
  //  ID table field.
  $data['ek_item_packing']['id'] = array(
    'title' => t('Item packing id'), 
    'help' => t('Item packing id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_item_packing']['itemcode'] = array(
    'title' => t('Item main item code'), 
    'help' => t('Item main item code from packing'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],  
    'relationship' => array(
      'base' => 'ek_items', // The name of the table to join with
      'field' => 'itemcode', // The name of the field to join with
      'id' => 'standard',
      'label' => t('main item ref'),
    ),      
  );
  $data['ek_item_packing']['units'] = array(
    'title' => t('Item units'), 
    'help' => t('Item units description'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_packing']['unit_measure'] = array(
    'title' => t('Item units measure'), 
    'help' => t('Item units measure description'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_packing']['item_size'] = array(
    'title' => t('Item size'), 
    'help' => t('Item size description'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_packing']['pack_size'] = array(
    'title' => t('Item pack size'), 
    'help' => t('Item pack size description'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_packing']['qty_pack'] = array(
    'title' => t('Item quantity per pack '), 
    'help' => t('Item quantity per pack description'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );  
  $data['ek_item_packing']['c20'] = array(
    'title' => t('Items quantity per 20ft container '), 
    'help' => t('Items quantity per 20ft container'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );  

  $data['ek_item_packing']['c40'] = array(
    'title' => t('Items quantity per 40ft container '), 
    'help' => t('Items quantity per 40ft container'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  ); 
  $data['ek_item_packing']['min_order'] = array(
    'title' => t('Item min. order '), 
    'help' => t('Item quantity min. order'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  ); 

  /**********Items prices***********/
  $data['ek_item_prices']['table']['group'] = t('ek items');
  $data['ek_item_prices']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services items prices'),
    'help' => t('contains items prices'),
    'weight' => -10,
    'database' => 'external_db',

  );
  $data['ek_item_prices']['table']['join'] = array(
    'ek_items' => array(
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ),
  );
  //  ID table field.
  $data['ek_item_prices']['id'] = array(
    'title' => t('Unique price row id'), 
    'help' => t('Unique price row id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_item_prices']['itemcode'] = array(
    'title' => t('Item main item code'), 
    'help' => t('Item main item code from prices'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],  
    'relationship' => array(
      'base' => 'ek_items', // The name of the table to join with
      'field' => 'itemcode', // The name of the field to join with
      'id' => 'standard',
      'label' => t('main item ref'),
    ),      
  );
  $data['ek_item_prices']['purchase_price'] = array(
    'title' => t('Item purchase price'), 
    'help' => t('Item purchase price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['currency'] = array(
    'title' => t('Item price currency'), 
    'help' => t('Item price currency'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_prices']['date_purchase'] = array(
    'title' => t('Item date purchase'), 
    'help' => t('Item date purchase'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_prices']['selling_price'] = array(
    'title' => t('Item selling price'), 
    'help' => t('Item selling price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['promo_price'] = array(
    'title' => t('Item promotion price'), 
    'help' => t('Item promotion price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['discount_price'] = array(
    'title' => t('Item discount price'), 
    'help' => t('Item discount price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['exp_selling_price'] = array(
    'title' => t('Item export selling price'), 
    'help' => t('Item export selling price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['exp_promo_price'] = array(
    'title' => t('Item export promotion price'), 
    'help' => t('Item export promotion price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_prices']['exp_discount_price'] = array(
    'title' => t('Item export discount price'), 
    'help' => t('Item export discount price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );  
  $data['ek_item_prices']['loc_currency'] = array(
    'title' => t('Item local currency'), 
    'help' => t('Item local currency'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_prices']['exp_currency'] = array(
    'title' => t('Item export currency'), 
    'help' => t('Item export currency'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );  
  
  
  /**********Items barcodes***********/
  $data['ek_item_barcodes']['table']['group'] = t('ek items');
  $data['ek_item_barcodes']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services items barcodes'),
    'help' => t('contains items barcodes'),
    'weight' => -10,
    'database' => 'external_db',    
  );
  
  $data['ek_item_barcodes']['table']['join'] = array(
    'ek_items' => array(
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ),
  );
  //  ID table field.
  $data['ek_item_barcodes']['id'] = array(
    'title' => t('Item main barcode id'), 
    'help' => t('Item main barcode id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_item_barcodes']['itemcode'] = array(
    'title' => t('Item main item code'), 
    'help' => t('Item main item code from barcode'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',], 
    'relationship' => array(
      'base' => 'ek_items', // The name of the table to join with
      'field' => 'itemcode', // The name of the field to join with
      'id' => 'standard',
      'label' => t('main item ref'),
    ),      
  );
  $data['ek_item_barcodes']['barcode'] = array(
    'title' => t('Item barcode value'), 
    'help' => t('Item barcode value'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  
  $data['ek_item_barcodes']['encode'] = array(
    'title' => t('Item barcode encoding standard'), 
    'help' => t('Item barcode encoding standard'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );  


  /**********Items images***********/
  $data['ek_item_images']['table']['group'] = t('ek items ');
  $data['ek_item_images']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services items images'),
    'help' => t('Items images'),
    'weight' => -10,
    'database' => 'external_db',    
  );
  
  $data['ek_item_images']['table']['join'] = array(
    'ek_items' => array(
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ),
  );
  //  ID table field.
  $data['ek_item_images']['id'] = array(
    'title' => t('Image id'), 
    'help' => t('Image id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_item_images']['itemcode'] = array(
    'title' => t('Item main item code'), 
    'help' => t('Item main item code from images'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',], 
    'relationship' => array(
      'base' => 'ek_items', // The name of the table to join with
      'field' => 'itemcode', // The name of the field to join with
      'id' => 'standard',
      'label' => t('main item ref'),
    ),      
  );
  $data['ek_item_images']['image'] = array(
    'title' => t('Item image uri'), 
    'help' => t('Item image uri.'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
     
  );

  /**********Items price history***********/
  $data['ek_item_price_history']['table']['group'] = t('ek items');
  $data['ek_item_price_history']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek products & services items price history'),
    'help' => t('Items price history'),
    'weight' => -10,
    'database' => 'external_db',    
  );

  $data['ek_item_price_history']['table']['join'] = array(
    'ek_items' => array(
      'left_field' => 'itemcode',
      'field' => 'itemcode',
    ),
  );
  //  ID table field.
  $data['ek_item_price_history']['id'] = array(
    'title' => t('Price history unique id'), 
    'help' => t('Price history unique  id'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_item_price_history']['itemcode'] = array(
    'title' => t('Item main item code'), 
    'help' => t('Item main item code from history'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',], 
    'relationship' => array(
      'base' => 'ek_items', // The name of the table to join with
      'field' => 'itemcode', // The name of the field to join with
      'id' => 'standard',
      'label' => t('main item ref'),
    ),      
  );
  $data['ek_item_price_history']['date'] = array(
    'title' => t('Item price date history'),
    'help' => t('Item price date history'),
    'field' => ['id' => 'date',],
    'sort' => ['id' => 'date',],
    'filter' => ['id' => 'date',],
  );
  $data['ek_item_price_history']['price'] = array(
    'title' => t('Item history price'), 
    'help' => t('Item history price'), 
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_item_price_history']['currency'] = array(
    'title' => t('Item history currency code'), 
    'help' => t('Item history currency code'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );
  $data['ek_item_price_history']['type'] = array(
    'title' => t('Item history price type'), 
    'help' => t('Item history price type'), 
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
    'argument' => ['id' => 'string',],    
  );  
  


 return $data;
}

