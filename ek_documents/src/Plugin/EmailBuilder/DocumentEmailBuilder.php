<?php

namespace Drupal\ek_documents\Plugin\EmailBuilder;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for ek_documents module.
 *
 * @EmailBuilder(
 *   id = "ek_documents",
 *   sub_types = { "key_document_share" = @Translation("Documents sharing") },
 *   common_adjusters = {"email_theme", "mailer_inline_css", "mailer_default_headers"},
 *   import = @Translation("Share document"),
 * )
 */
class DocumentEmailBuilder extends EmailBuilderBase {

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
    $options = [
        'filename' => $message['params']['options']['filename'],
        'priority' => $message['params']['options']['priority'], 
        'link' => $message['params']['options']['link'], 
        'url' => $message['params']['options']['url'],
        'note' => $message['params']['options']['message']];
    return $factory->newTypedEmail($message['module'], $message['key'],$body,$sender,$recipient,$subject,$options);
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function build(EmailInterface $email) {
      
    // email object content get via   $email->getParam()
    // 'id' => ek_documents_ek_message
    // 'module' => ek_documents
    // 'key' => key_document_share
      
    $config = $this->helper()->config();
    $site_name = $config->get('system.site')->get('name');
    $theme = theme_get_setting('logo');
    $site_logo = \Drupal::request()->getSchemeAndHttpHost() . $theme['url'];
    $stamp = date('F j, Y, g:i a');
    $options = $email->getParam('options');
    $priority = ['3' => t('low'), '2' => t('normal'), '1' => t('high')];
    $color = ['3' => '#159F45', '2' => '#0D67A7' , '1' => '#A70D0D'];
    $message['priority'] = $priority[$options['priority']];
    $message['color'] = $color[$options['priority']];
    $message['link'] = $options['link'];
    $message['url'] = $options['url'];
    $message['note'] = $options['note'];
    
    $email->setVariable('site_name', $site_name)
      ->setVariable('site_logo', $site_logo)
      ->setVariable('stamp', $stamp)      
      ->setVariable('origin', \Drupal::currentUser()->getAccountName())
      ->setVariable('body', $email->getParam('body'))  
      ->setVariable('message', $message);
    $email->setReplyTo($email->getParam('from'));
    
    $email->setTo($email->getParam('to'));
    if($options['priority'] > 1) {
        $email->setSubject($email->getParam('subject'));
        $email->setPriority($options['priority']);
    } else {
        $s = "[" . t('Urgent'). "] " . $email->getParam('subject');
        $email->setSubject($s);
    }
  }


  /**
   * {@inheritdoc}
   */
  public function import() {
      
  }

}
