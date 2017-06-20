<?php

/**
 * @file
 * Contains Drupal\ek_messaging\EventSubscriber\InboxSubscriber.
 */

namespace Drupal\ek_messaging\EventSubscriber;

use Drupal\Core\Database\Database;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Url;

class InboxSubscriber implements EventSubscriberInterface {

    static function getSubscribedEvents() {

        $events[KernelEvents::REQUEST][] = array('inbox');
        return $events;
    }

    public function inbox($event) {

        if (\Drupal::currentUser()->hasPermission('send_message')) {
            $url = \Drupal::request()->getRequestUri();
            if (!preg_match("/\/admin\//i", $url) && !preg_match("/ajax/", $url)) {
                //hide message in admin panel or ajax calls

                $db = FALSE;
                try {
                    //verify that the database have been installed first to prevent error upon module install
                    $external = Database::getConnectionInfo('external_db');
                    if (!empty($external)) {
                        $db = TRUE;
                    }
                } catch (Exception $e) {
                    
                }

                if ($db == TRUE) {
                    if (\Drupal::currentUser()->isAuthenticated()) {
                        $query = "SHOW TABLES LIKE 'ek_messaging'";
                        $data = Database::getConnection('external_db', 'external_db')
                                ->query($query)
                                ->fetchField();
                        if ($data == 'ek_messaging') {
                             
                            $me = \Drupal::currentUser()->id();
                            $user = "%," . $me . ",%";
                            $query = "SELECT count(id) FROM {ek_messaging} WHERE inbox like :i AND status not like :s AND archive not like :a";
                            $a = [':i' => $user, ':s' => $user, ':a' => $user];
                            $data = Database::getConnection('external_db', 'external_db')
                                    ->query($query, $a)
                                    ->fetchField();

                            if ($data > 0) {
                                $h = Url::fromRoute('ek_messaging_inbox')->toString();
                                if ($data > 1) {
                                    drupal_set_message(t('There are @c unread messages in your <a href=@h>inbox</a>', array('@c' => $data, '@h' => $h)));
                                } else {
                                    drupal_set_message(t('There is 1 unread message in your <a href=@h>inbox</a>', array('@h' => $h)));
                                }
                            }
                        }
                    }
                }//true
            }//route
        }
    }

}
