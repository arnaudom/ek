<?php

namespace Drupal\ek_admin\Plugin\EmailBuilder;

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
 *   id = "ek_admin",
 *   sub_types = { "ek_notification" = @Translation("notification message") },
 *   common_adjusters = {"email_theme", "mailer_inline_css", "mailer_default_headers"},
 *   import = @Translation("Notification admin email"),
 * )
 */
class AdminEmailBuilder extends EmailBuilderBase {

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
        case 'attachment':
            $render = [
                '#markup' => $message['params']['body'],
            ];
            $recipient = $message['to'];
            $subject = $message['params']['subject'];
            $body = \Drupal::service('renderer')->render($render);
            $pcode = $message['params']['options']['pcode'] ?? '';
            $receipt = $message['params']['options']['receipt'] ?? '';
            $options = [
                'filename' => $message['params']['options']['filename'],
                'user' => $message['params']['options']['user'],
                'origin' => $message['params']['options']['origin'],
                'pcode' => $pcode,
                'size' => $message['params']['options']['size'],
                'receipt' => $receipt,
                'attachments' => $message['params']['files'],
                'from' => $message['params']['options']['from'],
            ];
            if (empty($options['from'])) {
              $options['from'] = \Drupal::config('system.site')->get('mail_notification');
            }
            break;
        
        case 'client_reminder':
        case 'hr_date':
        case 'project_status':
        case 'receipt':
        case 'sales_alert':
        case 'tasks':
            // $sender = $message['from'];
            $recipient = $message['to'];
            $subject = $message['params']['options']['subject'];
            $body = isset($message['params']['body']) ? $message['params']['body'] : "";
            $options = $message['params']['options'];
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
    // 'id' => ek_admin_key
    // 'module' => ek_module
    // 'key' => key
      
    $config = $this->helper()->config();
    $site_name = $config->get('system.site')->get('name');
    $theme = theme_get_setting('logo');
    $site_logo = \Drupal::request()->getSchemeAndHttpHost() . $theme['url'];
    $stamp = date('F j, Y, g:i a');
    $options = $email->getParam('options');
    $message['options'] = $options;
    // attachments
    $message['filename'] = $options['filename']  ?? [];
    $message['user'] = $options['user'] ?? [];
    $message['size'] = $options['size'] ?? [];
    $message['pcode'] = $options['pcode'] ?? [];
    $message['receipt'] = $options['receipt'] ?? [];
    $attachments = $options['attachments'] ?? [];
        foreach ($attachments as $attachment) {
            $email->attachFromPath($attachment->uri, $attachment->filename ?? NULL, $attachment->filemime ?? NULL);
        }
               
    $email->setVariable('site_name', $site_name)
      ->setVariable('site_logo', $site_logo)
      ->setVariable('body', $email->getParam('body'))  
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

