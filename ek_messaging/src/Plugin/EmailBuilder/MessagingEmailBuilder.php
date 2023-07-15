<?php

namespace Drupal\ek_messaging\Plugin\EmailBuilder;


use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for ek_messaging module.
 *
 * @EmailBuilder(
 *   id = "ek_messaging",
 *   sub_types = { "ek_message" = @Translation("Internal message") },
 *   common_adjusters = {"email_theme", "mailer_inline_css", "mailer_default_headers"},
 *   import = @Translation("Update messaging"),
 * )
 */
class MessagingEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  
  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function createParams(EmailInterface $email,$body = NULL, $sender = NULL, $recipient = NULL, $subject = NULL, $options = NULL) {
    assert($recipient != NULL);
    $email->setParam('body', $body)
            ->setParam('from', $sender)
            ->setParam('to', $recipient)
            ->setParam('subject', $subject)
            ->setParam('options',$options);
  }
  
  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    // include the params from array-based mail interface for backward compatibility
    // i.e. $message['params']['body'], $message['params']['from'],$message['params']['priority']
    $sender = $message['params']['from'];  
    $body = $message['params']['body'];
    $recipient = $message['to'];
    $subject = $message['params']['subject'];
    $options = ['priority' => $message['params']['priority'], 'link' => $message['params']['link'], 'url' => $message['params']['url']];
    return $factory->newTypedEmail($message['module'], $message['key'],$body,$sender,$recipient,$subject,$options);
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function build(EmailInterface $email) {
      
    // email object conent get via   $email->getParam()
    // 'id' => ek_messaging_ek_message
    // 'module' => ek_messaging
    // 'key' => ek_message
      
    $config = $this->helper()->config();
    $site_name = $config->get('system.site')->get('name');
    $theme = theme_get_setting('logo');
    $site_logo = \Drupal::request()->getSchemeAndHttpHost() . $theme['url'];
    $stamp = date('F j, Y, g:i a');
    $options = $email->getParam('options');
    $priority = ['3' => t('low'), '2' => t('normal'), '1' => t('high')];
    $color = ['3' => '#159F45', '2' => '#0D67A7' , '1' => '#A70D0D'];
    $messages['priority'] = $priority[$options['priority']];
    $messages['color'] = $color[$options['priority']];
    $messages['link'] = $options['link'];
    $messages['url'] = $options['url'];
    
    // email can be send with 2 options : full body or standard alert
    // if full body is selected, filter the content for html rendering
    $body = '';
    if($options['link'] == '0') {
        // filter images / links
        $body = $email->getParam('body');
            if(preg_match("/src=\"([^\"]+)\"/", $body, $txt)){
                $body = str_replace('src="/', 'src="' . \Drupal::request()->getSchemeAndHttpHost(), $body);
            }
        $render = ['#markup' => $body];
        $body = \Drupal::service('renderer')->render($render);
    }
           
    $email->setVariable('site_name', $site_name)
      ->setVariable('site_logo', $site_logo)
      ->setVariable('stamp', $stamp)      
      ->setVariable('origin', \Drupal::currentUser()->getAccountName())
      ->setVariable('body', $body)  
      ->setVariable('messages', $messages);
    $email->setTo($email->getParam('to'));
    $email->setSubject($email->getParam('subject'));
    $email->setReplyTo($email->getParam('from'));
  }


  /**
   * {@inheritdoc}
   */
  public function import() {
  }

}
