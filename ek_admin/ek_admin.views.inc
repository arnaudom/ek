<?php

/**
 * @file
 * Views interface for ek_admin.
 */


/**
 * Implements hook_views_data().
 */
function ek_admin_views_data() {
    // This write hook_views_data() for the following
    // table:ek_company, ek_country

    $data['ek_company']['table']['group'] = t('ek admin');
    $data['ek_company']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek companies'),
        'help' => t('ek companies data.'),
        'weight' => -10,
        'database' => 'external_db',
    );

    //  ID table field.
    $data['ek_company']['id'] = array(
        'title' => t('Company id'),
        'help' => t('Company id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_company']['name'] = array(
        'title' => t('Company name'),
        'help' => t('Company name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['reg_number'] = array(
        'title' => t('Company registration'),
        'help' => t('Company administrative record'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['address1'] = array(
        'title' => t('Company address line 1'),
        'help' => t('Company address line 1'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['address2'] = array(
        'title' => t('Company address line 2'),
        'help' => t('Company address line 2'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['city'] = array(
        'title' => t('Company city'),
        'help' => t('Company address city'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['postcode'] = array(
        'title' => t('Company postcode'),
        'help' => t('Company address postcode'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['country'] = array(
        'title' => t('company country'),
        'help' => t('company country'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['telephone'] = array(
        'title' => t('Company telephone'),
        'help' => t('Company telephone'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['mobile'] = array(
        'title' => t('Company mobile phone No.'),
        'help' => t('Company mobile phone No.'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['fax'] = array(
        'title' => t('Company fax'),
        'help' => t('Company fax'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['email'] = array(
        'title' => t('Company email'),
        'help' => t('Company email address'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_company']['contact'] = array(
        'title' => t('Company contact'),
        'help' => t('Company contact name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    $data['ek_country']['table']['group'] = t('ek admin');
    $data['ek_country']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek countries'),
        'help' => t('ek countries list.'),
        'weight' => -10,
        'database' => 'external_db',
    );

    $data['ek_country']['id'] = array(
        'title' => t('country id'),
        'help' => t('country id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );

    $data['ek_country']['name'] = array(
        'title' => t('country name'),
        'help' => t('country name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    $data['ek_country']['code'] = array(
        'title' => t('country code'),
        'help' => t('country code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

    return $data;
}
