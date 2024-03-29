<?php
namespace Drupal\ek_admin\EventSubscriber;

use Drupal\Core\Database\Database;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Url;
use Drupal\ek_admin\GlobalSettings;

class NewUserSubscriber implements EventSubscriberInterface
{
    public function checkForUser(RequestEvent $event)
    {
        $e = $event->getRequest();
        $route = $e->get('_route');
  
        /*
         * Verify number of uid
         */
        if ($route == 'user.admin_create' || $route == 'user.register') {
            $query = Database::getConnection()->select('users', 'u');
            $query->addExpression('Count(uid)', 'count');
            $query->condition('uid', 1, '>');
            $Obj = $query->execute();
            $users = $Obj->fetchObject()->count;
            $settings = new GlobalSettings(0);
        
            if ($settings->get('validation_url') != '') {
                if ($settings->get('installation_id') == '') {
                    $validation_id = 'ek_default_validation';
                } else {
                    $validation_id = $settings->get('installation_id');
                }
                $url = $settings->get('validation_url') . '/' . $validation_id;
                $file = file_get_contents($url);
                //file structure is string of value separated by comma.
                //first value is max number of users
                //second value is max number of companies
                //third value is bolean for support
            
                $val = explode(',', $file);
                if ($val[0] == '') {
                    $maxusers = 5;
                } else {
                    $maxusers = $val[0];
                }
            } else {
                $maxusers = 5;
            }
       
            if ($users >= $maxusers) {
                \Drupal::messenger()->addWarning(t('Maximum quota of @u users has been reached. You may contact vendor for support.', ['@u' => $maxusers]));
                $event->setResponse(new RedirectResponse('<front>'));
            } else {
                if (\Drupal::currentUser()->isAuthenticated()) {
                    \Drupal::messenger()->addStatus(t('Your maximum number of users is @u and you have currently @n users registered.', ['@u' => $maxusers, '@n' => $users]));
                }
            }
        }

        /*
         * Verify number of coid
         */
        if ($route == 'ek_admin.company.new') {
            $query = "SELECT count(id) FROM {ek_company} ";
            $coids = Database::getConnection('external_db', 'external_db')
                ->query($query)
                ->fetchField();
            $settings = new GlobalSettings(0);
        
            if ($settings->get('validation_url') != '') {
                if ($settings->get('installation_id') == '') {
                    $validation_id = 'ek_default_validation';
                } else {
                    $validation_id = $settings->get('installation_id');
                }
                $url = $settings->get('validation_url') . '/' . $validation_id;
                $file = file_get_contents($url);
                //file structure is string of value separated by comma.
                //first value is max number of users
                //second value is max number of companies
                //third value is bolean for support
            
                $val = explode(',', $file);
                if ($val[0] == '') {
                    $maxcoid = 1;
                } else {
                    $maxcoid = $val[1];
                }
            } else {
                $maxcoid = 5;
            }
       
            if ($coids >= $maxcoid) {
                \Drupal::messenger()->addWarning(t('Maximum quota of @u companies has been reached. You may contact vendor for support.', ['@u' => $maxcoid]));
                $goto = Url::fromRoute('ek_admin.company.list', [], [])->toString();
                $event->setResponse(new RedirectResponse($goto));
            } else {
                \Drupal::messenger()->addStatus(t('Your maximum number of companies is @u and you currently have @n registered.', ['@u' => $maxcoid, '@n' => $coids]));
            }
        }
    }
  
  

  
  
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events[KernelEvents::REQUEST][] = array('checkForUser');
        return $events;
    }
}
