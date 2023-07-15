<?php

namespace Drupal\ek_finance\Plugin\EmailBuilder;

use Drupal\Core\Site\Settings;;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for ek_admin module.
 *
 * @EmailBuilder(
 *   id = "ek_finance",
 *   sub_types = { "key_memo_note" = @Translation("memo notification message") },
 *   common_adjusters = {"email_theme", "mailer_inline_css", "mailer_default_headers"},
 *   import = @Translation("Notification finance email"),
 * )
 */
class FinanceEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  
  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function createParams(EmailInterface $email, $recipient = NULL, 
          $body = NULL, 
          $subject = NULL, 
          $options = NULL) {
    assert($recipient != NULL);
    $email->setParam('to', $recipient)
            ->setParam('body', $body)
            ->setParam('subject', $subject)
            ->setParam('options',$options);
  }
  
  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    // include the params from array-based mail interface for backward compatibility
    // i.e. $message['params']['body'], $message['params']['from'],$message['params']['priority']
         
    switch ($message['key']) {
                
        case 'key_memo_note':
            // $sender = $message['from'];
            $recipient = $message['to'];
            $subject = $message['params']['subject'];
            $body = isset($message['params']['body']) ? $message['params']['body'] : "";
            $options = $message['params']['options'];
            $options['from'] = \Drupal::config('system.site')->get('mail');
            if (empty($options['from'])) {
              $options['from'] = \Drupal::config('system.site')->get('mail_notification');
            }
            break;
        
    }
   
    return $factory->newTypedEmail($message['module'], $message['key'],$recipient,$body,$subject,$options);
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function build(EmailInterface $email) {
      
    // email object content get via   $email->getParam()
    // 'id' => key_memo_note
    // 'module' => ek_module
    // 'key' => key
      
    $config = $this->helper()->config();
    $site_name = $config->get('system.site')->get('name');
    $theme = theme_get_setting('logo');
    $site_logo = \Drupal::request()->getSchemeAndHttpHost() . $theme['url'];
    $stamp = date('F j, Y, g:i a');
    $body = $email->getParam('body');
    $render = ['#markup' => $body];
    $body = \Drupal::service('renderer')->render($render);
    $options = $email->getParam('options');
    $message['options'] = $options;
    $message['user'] = $options['user']  ?? [];
    $message['serial'] = $options['serial'] ?? [];
    $message['link'] = $options['link'] ?? [];
    $message['url'] = $options['url'] ?? [];
               
    $email->setVariable('site_name', $site_name)
      ->setVariable('site_logo', $site_logo)
      ->setVariable('body', $body)  
      ->setVariable('stamp', $stamp)  
      ->setVariable('message', $message);
    $email->setTo($email->getParam('to'));
    $email->setSubject($email->getParam('subject'));
    $email->setReplyTo($options['from']);
  }


  /**
   * {@inheritdoc}
   */
  public function import() {
      
  }

}

