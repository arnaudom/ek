<?php

/**
 * @file
 * Views interface for ek_assets.
 */


/**
 * Implements hook_views_data().
 */
function ek_assets_views_data() {
  
  $data['ek_assets']['table']['group'] = t('ek assets');

  // Define this as a base table – a table that can be described in itself by
  // views (and not just being brought in as a relationship). 
  $data['ek_assets']['table']['base'] = array(
    'field' => 'id', // This is the identifier field for the view.
    'title' => t('ek assets'),
    'help' => t('Assets data.'),
    'database' => 'external_db',
    'weight' => -10,
  );

  $data['ek_assets']['id'] = array(
    'title' => t('Asset id'),
    'help' => t('Asset unique id'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
    'relationship' => array(
      'base' => 'ek_assets_amortization', 
      'field' => 'asid', // The name of the field to join with
      'id' => 'standard',
      'label' => t('Amortization schedule'),
    ),
    
  );
  
  $data['ek_assets']['asset_name'] = array(
    'title' => t('Asset name'),
    'help' => t('Asset name'),
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  
  $data['ek_assets']['asset_brand'] = array(
    'title' => t('Asset brand'),
    'help' => t('Asset brand or name complement'),
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
   
  $data['ek_assets']['asset_ref'] = array(
    'title' => t('Asset reference'),
    'help' => t('Asset reference or article code'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  
  $data['ek_assets']['coid'] = array(
    'title' => t('Asset company id'),
    'help' => t('Id of company linked to asset'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
    'relationship' => array(
        'base' => 'ek_company', // The name of the table to join with
        'field' => 'id', // The name of the field to join with
        'id' => 'standard',
        'label' => t('Company ref'),
    ),     
  );
  
  $data['ek_assets']['unit'] = array(
    'title' => t('Asset unit'),
    'help' => t('Asset quantities'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  
  $data['ek_assets']['aid'] = array(
    'title' => t('Asset aid'),
    'help' => t('Asset journal account reference id'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
    'relationship' => array(
        'base' => 'ek_accounts', // The name of the table to join with
        'field' => 'aid', // The name of the field to join with
        'id' => 'standard',
        'label' => t('Chart of accounts'),
    ),        
  );

  $data['ek_assets']['comment'] = array(
    'title' => t('Assetcomment'),
    'help' => t('Asset comment'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );

  $data['ek_assets']['asset_doc'] = array(
    'title' => t('Assetasset attachment'),
    'help' => t('Asset attachment uri'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );

  $data['ek_assets']['asset_pic'] = array(
    'title' => t('Asset picture'),
    'help' => t('Asset picture uri'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  
  $data['ek_assets']['asset_value'] = array(
    'title' => t('Asset value'),
    'help' => t('Asset purchase value'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );

  $data['ek_assets']['currency'] = array(
    'title' => t('Asset currency'),
    'help' => t('Asset currency of purchase'),
    'field' => ['id' => 'standard','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );

  $data['ek_assets']['date_purchase'] = array(
    'title' => t('Asset date'),
    'help' => t('Asset date of purchase'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  
  
  $data['ek_assets_amortization']['table']['group'] = t('ek assets');
   
  $data['ek_assets_amortization']['table']['base'] = array(
    'field' => 'asid', // This is the identifier field for the view.
    'title' => t('ek assets amortization schedule'),
    'help' => t('ek assets amortization schedule contains amortization data.'),
    'weight' => -10,
    'database' => 'external_db',
  );

  $data['ek_assets_amortization']['asid'] = array(
    'title' => t('Asset id'),
    'help' => t('Asset main id'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  

  $data['ek_assets_amortization']['term_unit'] = array(
    'title' => t('Amortization term unit'),
    'help' => t('Y: year or M: month'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  
  $data['ek_assets_amortization']['method'] = array(
    'title' => t('Amortization method'),
    'help' => t('depreciation method Ex. 1: linear'),
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_assets_amortization']['term'] = array(
    'title' => t('Amortization term'),
    'help' => t('Amortization number of term units or periods'),
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_assets_amortization']['amort_rate'] = array(
    'title' => t('Amortization rate'),
    'help' => t('Amortization rate in %'),
    'field' => ['id' => 'numeric','click sortable' => TRUE,],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_assets_amortization']['amort_value'] = array(
    'title' => t('Amortization value'),
    'help' => t('Amortization cumulated value'),
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );    
  $data['ek_assets_amortization']['amort_salvage'] = array(
    'title' => t('Amortization salvage value'),
    'help' => t('Amortization residual value'),
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_assets_amortization']['amort_record'] = array(
    'title' => t('Amortization record'),
    'help' => t('Serialized array of amortization schedule and recorded in journal'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );
  $data['ek_assets_amortization']['amort_status'] = array(
    'title' => t('Amortization status'),
    'help' => t('0: not amortized 1: amortized'),
    'field' => ['id' => 'numeric',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'numeric',],
  );
  $data['ek_assets_amortization']['alert'] = array(
    'title' => t('Amortization alert'),
    'help' => t('Amortization user id'),
    'field' => ['id' => 'standard',],
    'sort' => ['id' => 'standard',],
    'filter' => ['id' => 'string',],
  );  

  return $data;
}