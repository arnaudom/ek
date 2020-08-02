<?php

/**
 * @file
 * Views interface for ek_finance.
 */


/**
 * Implements hook_views_data().
 */

function ek_finance_views_data() {
    
        /**********Expenses***********/
    
    $data['ek_expenses']['table']['group'] = t('ek finance expense');
    $data['ek_expenses']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek expenses records'),
        'help' => t('Expenses records data'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_expenses']['id'] = array(
        'title' => t('Expense record id'),
        'help' => t('Expense record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['class'] = array(
        'title' => t('Expense class'),
        'help' => t('Expense class record - chart'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['type'] = array(
        'title' => t('Expense chart account'),
        'help' => t('Expense chart account'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_accounts', // The name of the table to join with
            'field' => 'aid', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Chart of accounts'),
          ],
    );
    $data['ek_expenses']['company'] = array(
        'title' => t('Expense company reference'),
        'help' => t('Expense company unique ID'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',], 
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'company', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
            'type' => 'INNER',
          ],   
    );
    $data['ek_expenses']['allocation'] = array(
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
    $data['ek_expenses']['localcurrency'] = array(
        'title' => t('Expense value in local currency'),
        'help' => t('Expense value in local currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['currency'] = array(
        'title' => t('Local currency'),
        'help' => t('Currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_expenses']['amount'] = array(
        'title' => t('Expense value in base currency'),
        'help' => t('Expense value converted in base currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['amount_paid'] = array(
        'title' => t('Expense value paid in base currency'),
        'help' => t('Expense value paid in base currency'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['rate'] = array(
        'title' => t('Expense base currency rate'),
        'help' => t('Expense base currency rate'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['tax'] = array(
        'title' => t('Tax value'),
        'help' => t('Expense absolute value to apply, i.e 5 => 5%'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_expenses']['pdate'] = array(
        'title' => t('Expense payment date'),
        'help' => t('Expense payment date'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_expenses']['clientname'] = array(
        'title' => t('Client'), 
        'help' => t('Client ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'clientname', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to expense'),
        ],     
  
    );
    $data['ek_expenses']['suppliername'] = array(
        'title' => t('Supplier'), 
        'help' => t('Supplier ID from address book'), 
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',], 
        'relationship' => [
            'base' => 'ek_address_book', // The name of the table to join with
            'field' => 'suppliertname', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Address book link to expense'),
        ],     
  
    );
    $data['ek_expenses']['comment'] = array(
        'title' => t('Expense open comments'),
        'help' => t('Expense open comments'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_expenses']['attachment'] = array(
        'title' => t('Attachment'),
        'help' => t('Internal link to attachment'),
        'field' => ['id' => 'standard',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );  
    if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {
        $data['ek_expenses']['pcode'] = array(
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
                'label' => t('Project details linked to this expense'),
            ],        
        );
    }
    
    
        /**********bank transactions***********/
    
    $data['ek_bank_transactions']['table']['group'] = t('ek finance bank');
    $data['ek_bank_transactions']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek bank transactions records'),
        'help' => t('Transaction records per bank account'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_bank_transactions']['id'] = array(
        'title' => t('Bank transaction record id'),
        'help' => t('Bank transaction record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_bank_transactions']['account_ref'] = array(
        'title' => t('Bank account number'),
        'help' => t('Bank account number'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_bank_accounts', // The name of the table to join with
            'field' => 'account_ref', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Bank acccount details'),
          ],
    );
    $data['ek_bank_transactions']['date_transaction'] = array(
        'title' => t('Bank transaction date'),
        'help' => t('Bank transaction date'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank_transactions']['type'] = array(
        'title' => t('Bank transaction type'),
        'help' => t('Bank transaction type'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank_transactions']['amount'] = array(
        'title' => t('Bank transaction amount'),
        'help' => t('Bank transaction amount'),
        'field' => ['id' => 'numeric',],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_bank_transactions']['currency'] = array(
        'title' => t('Bank transaction currency'),
        'help' => t('Bank transaction currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank_transactions']['description'] = array(
        'title' => t('Bank transaction description'),
        'help' => t('Bank transaction description'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );

        /**********bank***********/
    
    $data['ek_bank']['table']['group'] = t('ek finance bank');
    $data['ek_bank']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek bank records'),
        'help' => t('Banks'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_bank']['id'] = array(
        'title' => t('Bank id'),
        'help' => t('Bank unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_bank']['name'] = array(
        'title' => t('Bank name'),
        'help' => t('Bank name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank']['coid'] = array(
        'title' => t('Bank link to company'),
        'help' => t('Bank link to company'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_company', // The name of the table to join with
            'field' => 'coid', // The name of the field to join with
            'id' => 'standard',
            'label' => t('Company details'),
          ],
    );
    
        /**********bank accounts***********/
    
    $data['ek_bank_accounts']['table']['group'] = t('ek finance bank');
    $data['ek_bank_accounts']['table']['base'] = array(
        'field' => 'id', // This is the identifier field for the view.
        'title' => t('ek bank accounts records'),
        'help' => t('Bank accounts'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_bank_accounts']['id'] = array(
        'title' => t('Bank account record id'),
        'help' => t('Bank account record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_bank_accounts']['bid'] = array(
        'title' => t('Bank main id'),
        'help' => t('Bank id link'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
        'relationship' => [
            'base' => 'ek_bank', // The name of the table to join with
            'field' => 'bid', // The name of the field to join with
            'id' => 'standard',
            'base field' => 'id',
            'label' => t('Bank linked to this account'),
            ],        
    );
    $data['ek_bank_accounts']['currency'] = array(
        'title' => t('Bank account currency'),
        'help' => t('Bank account currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank_accounts']['aid'] = array(
        'title' => t('Bank account chart id'),
        'help' => t('Bank account chart id'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
        'relationship' => [
            'base' => 'ek_accounts', // The name of the table to join with
            'field' => 'aid', // The name of the field to join with
            'id' => 'standard',
            //'base field' => 'aid',
            'label' => t('Chart of accounts'),
          ],
    );
    $data['ek_bank_accounts']['beneficiary'] = array(
        'title' => t('Bank account beneficiary'),
        'help' => t('Bank account beneficiary'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_bank_accounts']['active'] = array(
        'title' => t('Bank account  status'),
        'help' => t('Bank account : 0 = inactive 1 = active'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );

    
        /**********chart accounts***********/
    
    $data['ek_accounts']['table']['group'] = t('ek finance chart');
    $data['ek_accounts']['table']['base'] = array(
        'field' => 'aid', // This is the identifier field for the view.
        'title' => t('ek chart of accounts'),
        'help' => t('Chart of accounts'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_accounts']['id'] = array(
        'title' => t('Chart id'),
        'help' => t('Chart unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_accounts']['aid'] = array(
        'title' => t('Chart account'),
        'help' => t('Chart 6 digits account'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_accounts']['aname'] = array(
        'title' => t('Chart account name'),
        'help' => t('Chart name of account'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_accounts']['status'] = array(
        'title' => t('Chart account status'),
        'help' => t('0 = inactive 1 = active'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_accounts']['coid'] = array(
        'title' => t('Chart account company'),
        'help' => t('Chart company link'),
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
    $data['ek_accounts']['balance'] = array(
        'title' => t('Chart account balance value'),
        'help' => t('Value in local currency'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_accounts']['balance_base'] = array(
        'title' => t('Chart account balance base value'),
        'help' => t('Value in base currency'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_accounts']['balance_date'] = array(
        'title' => t('Chart account balance date'),
        'help' => t('Value opening date'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );


        /**********currencies***********/
    
    $data['ek_currency']['table']['group'] = t('ek finance currencies');
    $data['ek_currency']['table']['base'] = array(
        'field' => 'currency', // This is the identifier field for the view.
        'title' => t('ek bank currencies records'),
        'help' => t('Currencies'),
        'weight' => 1,
        'database' => 'external_db'
    );
    //  ID table field.
    $data['ek_currency']['id'] = array(
        'title' => t('Currencies record id'),
        'help' => t('Currencies record unique id'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_currency']['currecny'] = array(
        'title' => t('Currency code'),
        'help' => t('Currency code'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_currency']['name'] = array(
        'title' => t('Currency name'),
        'help' => t('Currency name'),
        'field' => ['id' => 'standard','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );
    $data['ek_currency']['rate'] = array(
        'title' => t('Currency rate'),
        'help' => t('Currency rate'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_bank_accounts']['active'] = array(
        'title' => t('Currency status'),
        'help' => t('0 = inactive 1 = active'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'numeric',],
    );
    $data['ek_currency']['date'] = array(
        'title' => t('Currency edit date'),
        'help' => t('Currency edit date'),
        'field' => ['id' => 'numeric','click sortable' => TRUE,],
        'sort' => ['id' => 'standard',],
        'filter' => ['id' => 'string',],
        'argument' => ['id' => 'string',],
    );



    
    return $data;
}