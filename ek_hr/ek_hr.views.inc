<?php

/**
 * @file
 * Views interface for ek_hr.
 */


/**
 * Implements hook_views_data().
 */

function ek_hr_views_data() {

        /**********services***********/
    $data['ek_hr_service']['table']['group'] = t('ek HR workforce');
    $data['ek_hr_service']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek HR services'),
        'help' => t('HR services'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_hr_service']['sid'] = array(
        'title' => t('HR service record id'),
        'help' => t('HR service record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_service']['service_name'] = array(
        'title' => t('Service name'),
        'help' => t('Service name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

        /**********location***********/
    $data['ek_hr_location']['table']['group'] = t('ek HR workforce');
    $data['ek_hr_location']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek HR work locations'),
        'help' => t('HR work locations'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_hr_location']['id'] = array(
        'title' => t('HR service record id'),
        'help' => t('HR service record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_location']['coid'] = array(
        'title' => t('Company id'),
        'help' => t('Company id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'allocation', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'id',
            'label' => t('Company details'),
          ],
    );
    $data['ek_hr_location']['location'] = array(
        'title' => t('Location name'),
        'help' => t('Location name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
        
        /**********workforce***********/
    $data['ek_hr_workforce']['table']['group'] = t('ek HR workforce');
    $data['ek_hr_workforce']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek HR workforce records'),
        'help' => t('HR workforce record data'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_hr_workforce']['id'] = array(
        'title' => t('HR workforce record id'),
        'help' => t('HR workforce record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_workforce']['custom_id'] = array(
        'title' => t('Custom ID'),
        'help' => t('Custom ID'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['company_id'] = array(
        'title' => t('Attached company'),
        'help' => t('Attached company, id reference'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'allocation', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'id',
            'label' => t('Company details'),
          ],
    );
    $data['ek_hr_workforce']['origin'] = array(
        'title' => t('Category parameter'),
        'help' => t('Category parameter'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['name'] = array(
        'title' => t('HR name'),
        'help' => t('HR name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['email'] = array(
        'title' => t('HR enail'),
        'help' => t('HR email'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['address'] = array(
        'title' => t('HR address'),
        'help' => t('HR address'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['telephone'] = array(
        'title' => t('HR telephone'),
        'help' => t('HR telephone'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['sex'] = array(
        'title' => t('HR gender'),
        'help' => t('HR gender M/F'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['rank'] = array(
        'title' => t('HR position'),
        'help' => t('HR position / rank'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['ic_no'] = array(
        'title' => t('HR Identification No.'),
        'help' => t('HR Identification No.'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['ic_type'] = array(
        'title' => t('HR Identification No. type'),
        'help' => t('HR Identification No. type'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['birth'] = array(
        'title' => t('HR birth date'),
        'help' => t('HR birth date'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['epf_no'] = array(
        'title' => t('HR pension fund number'),
        'help' => t('HR pension fund number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['socso_no'] = array(
        'title' => t('HR social security fund number'),
        'help' => t('HR social security fund number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['itax_no'] = array(
        'title' => t('HR tax number'),
        'help' => t('HR tax number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['itax_c'] = array(
        'title' => t('HR tax category'),
        'help' => t('HR tax category'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['e_status'] = array(
        'title' => t('HR employment status'),
        'help' => t('HR employment status'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['location'] = array(
        'title' => t('HR work location'),
        'help' => t('HR work location'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_hr_location', // The name of the table to join with
            'field' => 'location', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'location',
            'label' => t('Location details'),
          ],
    );
    $data['ek_hr_workforce']['service'] = array(
        'title' => t('HR work service'),
        'help' => t('HR work service ID'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_hr_service', // The name of the table to join with
            'field' => 'service', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'sid',
            'label' => t('Services details'),
          ],
    );
    $data['ek_hr_workforce']['bank'] = array(
        'title' => t('HR bank name'),
        'help' => t('HR bank name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['bank_account'] = array(
        'title' => t('HR bank account'),
        'help' => t('HR bank account'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['bank_account_status'] = array(
        'title' => t('HR bank account ownership'),
        'help' => t('HR bank account ownership'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['active'] = array(
        'title' => t('HR work status'),
        'help' => t('HR work status'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['start'] = array(
        'title' => t('HR work start date'),
        'help' => t('HR work start date'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['start'] = array(
        'title' => t('HR work resign date'),
        'help' => t('HR work resign date'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['contract_expiration'] = array(
        'title' => t('HR contract expiration date'),
        'help' => t('HR contract expiration date'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['currency'] = array(
        'title' => t('HR payment currency'),
        'help' => t('HR payment currency'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_hr_workforce']['salary'] = array(
        'title' => t('HR base salary'),
        'help' => t('HR base salary'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_workforce']['th_salary'] = array(
        'title' => t('HR other salary'),
        'help' => t('HR other salary'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_workforce']['aleave'] = array(
        'title' => t('HR allocated leaves'),
        'help' => t('HR allocated leaves (days)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_workforce']['mcleave'] = array(
        'title' => t('HR allocated medical leaves'),
        'help' => t('HR allocated medical leaves (days)'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_hr_workforce']['note'] = array(
        'title' => t('HR note'),
        'help' => t('HR note'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    
    
    
    return $data;
}