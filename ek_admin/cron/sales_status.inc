<?php

/**
 * @file
 *  module . ek_admin
 *  implement sales status checks for invoice or purchases
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

/*
 * invoices
 */

$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_sales_invoice', 'i');
$query->fields('i');
$query->condition('status', 1, '<>');
$query->condition('alert', 1, '=');
$data = $query->execute();

$protocol = isset($_SERVER["https"]) ? 'https' : 'http';
$domain = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$weekday = date('N');
$now = date('U');
//compile settings per coid
$coids = AccessCheck::CompanyList();
$salesSettings = [];
foreach ($coids as $key => $val) {
    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_settings', 's');
    $query->fields('s', ['settings']);
    $query->condition('coid', $key);
    $s = unserialize($query->execute()->fetchField());
    $salesSettings[$key]['shortdue'] = $s['shortdue'] ? $s['shortdue'] : 7;
    $salesSettings[$key]['longdue'] = $s['longdue'] ? $s['longdue'] : 30;
    $salesSettings[$key]['body'] = $s['reminder_body'];
}

/*
 * build list 
 */
while ($row = $data->fetchObject()) {

    //calculate due date
    $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($row->date)) . "+" . $row->due . ' ' . t("days")));
    $due = strtotime($due);
    $doc = [];
    if ($now > $due) {
        $late = ($stamp - $due) / (60 * 60 * 24);
        $doc['late'] = $late;
        $doc['shortdue'] = $salesSettings[$row->head]['shortdue'];
        $doc['longdue'] = $salesSettings[$row->head]['longdue'];
        $doc['link'] = $domain . Url::fromRoute('ek_sales.invoices.print_html', ['id' => $row->id])->toString();
        $doc['due'] = round($late, 0) . ' ' . t('days');
        $doc['serial'] = $row->serial;
        $doc['value'] = round($row->amount + ($row->amount * $row->taxvalue / 100),2);
        $doc['currency'] = $row->currency;
        
    }
    /*
     * notify 
     */
    if (!empty($doc)) {
        $alerts = unserialize($row->alert_who);
        $users = explode(',', $alerts['users']);
        
        if(!empty($alerts['client'])) {
            // client reminder
            if($alerts['client']['frequency'] > 0) {
                if($alerts['client']['frequency'] == 8 || ($weekday == $alerts['client']['frequency'])) {
                    // send
                    $contact = \Drupal\ek_address_book\AddressBookData::getContactData($alerts['client']['id']);
                    $params = array();
                    $params['type'] = 'client';
                    $params['subject'] = t('Reminder @i', ['@i' => $row->serial]);
                    $params['contact_email'] = $contact['email'];
                    $params['copy'] = 1;
                    $doc['contact_name'] = $contact['salutation'] . " " . $contact['contact_name'];
                    $doc['object'] = t('Payment due');
                    $doc['from_company'] = $coids[$row->head];
                    $doc['body'] = $salesSettings[$row->head]['body'];
                    $params['data'] = $doc;
                    self::send_mail($params);
                    
                    //send a copy to company email
                    $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_company', 'c');
                    $query->fields('c', ['email']);
                    $query->condition('id', $row->head);
                    $cmail = $query->execute()->fetchField();
                    if (filter_var($cmail, FILTER_VALIDATE_EMAIL)) {
                        $params['subject'] = "[COPY] " . t('Reminder @i', ['@i' => $row->serial]);
                        $params['contact_email'] = $cmail;
                        $params['copy'] = 0;
                        self::send_mail($params);
                    }
                }
            }
        }
                
        foreach ($users as $u) {
            // internal alert
            if (trim($u) != '') {
                $params = array();
                $params['type'] = 'sales_alert';
                $params['subject'] = t('Past due invoice');
                $params['uid'] = $u;
                $params['assign'] = $u;
                $params['data'][] = $doc;
                self::send_mail($params);
            }
        }
    }
}
/*
 * Purchases
 */

$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_sales_purchase', 'p');
$query->fields('p');
$query->condition('status', 1, '<>');
$query->condition('alert', 1, '=');
$data = $query->execute();

$domain = 'https://' . $_SERVER['HTTP_HOST'] . '/';
$weekday = date('D');
$stamp = date('U');


while ($row = $data->fetchObject()) {

    //calculate due date
    $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($row->date)) . "+" . $row->due . ' ' . t("days")));
    $due = strtotime($due);
    $doc = [];

    if ($stamp > $due) {
        $late = ($stamp - $due) / (60 * 60 * 24);
        $doc['late'] = $late;
        $doc['shortdue'] = $salesSettings[$row->head]['shortdue'];
        $doc['longdue'] = $salesSettings[$row->head]['longdue'];
        $doc['link'] = $domain . Url::fromRoute('ek_sales.purchases.print_html', ['id' => $row->id])->toString();
        $doc['due'] = round($late, 0) . ' ' . t('days');
        $doc['serial'] = $row->serial;
    }


    /*
     * notify 
     */
    if (!empty($doc)) {
        $alerts = unserialize($row->alert_who);
        $users = explode(',', $alerts['users']);

        foreach ($users as $u) {
            if (trim($u) != '') {
                $params = array();
                $params['type'] = 'sales_alert';
                $params['subject'] = t('Past due purchase');
                $params['uid'] = $u;
                $params['assign'] = $u;
                $params['data'][] = $doc;
                self::send_mail($params);
            }
        }
    }
}