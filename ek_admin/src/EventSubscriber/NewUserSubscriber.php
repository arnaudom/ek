<?php
namespace Drupal\ek_admin\EventSubscriber;
use Drupal\Core\Database\Database;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Drupal\Core\Url;
use Drupal\ek_admin\GlobalSettings;

class NewUserSubscriber implements EventSubscriberInterface {

  public function checkForUser(GetResponseEvent $event) { 
    $e = $event->getRequest();
    $route = $e->get('_route');
  
    /*
     * Verify number of uid
     */
      if($route == 'user.admin_create' || $route == 'user.register') {
        $query = "SELECT count(uid) FROM {users} WHERE uid>:id";
        $users = db_query($query, array(':id' => 1))->fetchField();
        $settings = new GlobalSettings(0);
        
        if($settings->get('validation_url') != ''){
            
            if($settings->get('installation_id') == '') {
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
            
            $val = explode(',' , $file);
            if($val[0] == '') {
                $maxusers = 5;
            } else {
                $maxusers = $val[0];
            }
            
        } else {
            $maxusers = 5;
        }
       
          if ($users >= $maxusers) {
            drupal_set_message(t('Maximum quota of @u users has been reached. You may contact vendor for support.', array('@u' => $maxusers)), 'warning');
            $event->setResponse(new RedirectResponse('<front>'));
            
          } else {
            if(\Drupal::currentUser()->isAuthenticated()){
                drupal_set_message(t('Your maximum number of users is @u and you have currently @n users registered.', 
                        array('@u' => $maxusers, '@n' => $users)), 'status'); 
            }
          }
      
      }

    /*
     * Verify number of coid
     */
    if($route == 'ek_admin.company.new' ) {
        $query = "SELECT count(id) FROM {ek_company} ";
        $coids = Database::getConnection('external_db', 'external_db')
                ->query($query)
                ->fetchField();
        $settings = new GlobalSettings(0);
        
        if($settings->get('validation_url') != ''){
            
            if($settings->get('installation_id') == '') {
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
            
            $val = explode(',' , $file);
            if($val[0] == '') {
                $maxcoid = 1;
            } else {
                $maxcoid = $val[1];
            }
            
        } else {
            $maxcoid = 5;
        }
       
          if ($coids >= $maxcoid) {
            drupal_set_message(t('Maximum quota of @u companies has been reached. You may contact vendor for support.', array('@u' => $maxcoid)), 'warning');
            $goto = Url::fromRoute('ek_admin.company.list', [], [])->toString();
            $event->setResponse(new RedirectResponse($goto));
            
          } else {
            drupal_set_message(t('Your maximum number of companies is @u and you currently have @n registered.', 
                    array('@u' => $maxcoid, '@n' => $coids)), 'status'); 
          }
      
    }
  
      
      
   }
  
  

  
  
  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForUser');
    return $events;
  }

}
?>