<?php

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;

if ($request->query->get('q') == 'subscribe_alerts') {
    $markup = '<p>Subscribe all users to alerts by default';
    $notifs = ['edit_project_subscription', 'edit_sales_doc_subscription', 'hr_date_subscription', 'new_project_subscription', 'sales_payment_subscription'];
    $users = User::loadMultiple();
    $userData = \Drupal::service('user.data');
    foreach ($users as $user) {
        $id = $user->id();
        if ($id > 1) {
            $markup .= "<p>Name: " . $user->getAccountName() . "</p>";
            foreach ($notifs as $n) {
                $markup .= "<p>subscribe user $id for $n</p>";
                $userData->set('ek_alert_subscriptions', $id, $n, 1);
            }
        }
    }
}