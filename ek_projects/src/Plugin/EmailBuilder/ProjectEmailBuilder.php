<?php

namespace Drupal\ek_projects\Plugin\EmailBuilder;


use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerHelperTrait;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for ek_projects module.
 *
 * @EmailBuilder(
 *   id = "ek_projects",
 *   sub_types = { "ek_projects_message" = @Translation("Project message") },
 *   common_adjusters = {"email_theme", "mailer_inline_css", "mailer_default_headers"},
 *   import = @Translation("Project message"),
 * )
 */
class ProjectEmailBuilder extends EmailBuilderBase {

  use MailerHelperTrait;
  
  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function createParams(EmailInterface $email,$body = NULL, $recipient = NULL, $subject = NULL, $options = NULL) {
    assert($recipient != NULL);
    $email->setParam('body', $body)
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
    $body = $message['params']['body'];
    $recipient = $message['to'];
    $subject = $message['params']['subject'];
    $options = [
        'pcode' => $message['params']['options']['pcode'], 
        'priority' => $message['params']['options']['priority'], 
        'url' => $message['params']['options']['url'],
        'sender' => $message['params']['options']['from'],
    ];
    return $factory->newTypedEmail($message['module'], $message['key'],$body,$recipient,$subject,$options);
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function build(EmailInterface $email) {
      
    // email object conent get via   $email->getParam()
    // 'id' => ek_projects_project_note
    // 'module' => ek_projects
    // 'key' => project_note , project_access
      
    $config = $this->helper()->config();
    $site_name = $config->get('system.site')->get('name');
    $theme = theme_get_setting('logo');
    $site_logo = \Drupal::request()->getSchemeAndHttpHost() . $theme['url'];
    $stamp = date('F j, Y, g:i a');
    $body = $email->getParam('body');
    $render = ['#markup' => $body];
    $body = \Drupal::service('renderer')->render($render);
    $email->setVariable('site_name', $site_name)
      ->setVariable('site_logo', $site_logo)
      ->setVariable('stamp', $stamp)
      ->setVariable('body', $body)  
      ->setVariable('message', $email->getParam('options'));
    $email->setTo($email->getParam('to'));
    $email->setSubject($email->getParam('subject'));
    $email->setReplyTo($email->getParam('options')['sender']);
    $email->setPriority($email->getParam('options')['priority']);
  }


  /**
   * {@inheritdoc}
   */
  public function import() {
  }

}
