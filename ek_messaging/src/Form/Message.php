<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Form\Message.
 */

namespace Drupal\ek_messaging\Form;

use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;

/**
 * Provides a form to send messages.
 */
class Message extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_messaging_send_message';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if ($id != null && $id != 'broadcast') {
            //this is a reply / forward form
            $form['id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_messaging', 'm');
            $query->fields('m');
            $query->innerJoin('ek_messaging_text', 't', 'm.id=t.id');
            $query->fields('t');
            $query->condition('t.id', $id);
            $data = $query->execute()->fetchObject();
            $account = \Drupal\user\Entity\User::load($data->from_uid);
            $to = '';
            if ($account) {
                $to = $account->getDisplayName();
            }
            $subject = $this->t('Re') . ': ' . $data->subject;
            $from = \Drupal\user\Entity\User::load($data->from_uid);
            $quote = $this->t('On @date, @user wrote', ['@date' => date('l jS \of F Y h:i:s A', $data->stamp), '@user' => $from->getAccountName()]);
            if ($data->format == 'restricted_html') {
                $text = "\r\n\r\n\r\n\r\n ------- " . $quote . " ------- \r\n\r\n" . $data->text;
            } else {
                $text = "<br><p> ------- " . $quote . " -------</p><p>" . $data->text . "</p>";
            }
        }
        
        if($id != 'broadcast') {
            $form['users'] = [
                '#type' => 'textarea',
                '#rows' => 2,
                '#attributes' => ['placeholder' => $this->t('enter recipients name separated by comma (autocomplete enabled).')],
                '#required' => true,
                '#default_value' => isset($to) ? $to : null,
            ];
        } else {
            $form['info'] = [
                '#type' => 'item',
                '#markup' => "<h1>" . $this->t('Message broadcast') . "</h1>",
            ];
            $form['users'] = [
                '#type' => 'hidden',
                '#value' => 'broadcast',
            ];
        }

        $form['priority'] = [
            '#type' => 'select',
            '#options' => ['3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high')],
            '#title' => $this->t('priority'),
            '#default_value' => isset($data->priority) ? $data->priority : null,
        ];

        $form['subject'] = [
            '#type' => 'textfield',
            '#default_value' => '',
            '#required' => true,
            '#default_value' => isset($subject) ? $subject : null,
            '#attributes' => ['placeholder' => $this->t('subject')],
        ];

        $form['message'] = [
            '#type' => 'text_format',
            '#rows' => 10,
            '#attributes' => array('placeholder' => $this->t('your message')),
            '#default_value' => isset($text) ? $text : null,
            '#format' => isset($data->format) ? $data->format : 'restricted_html',
        ];

        if($id != 'broadcast') {
            $form['email'] =[
                '#type' => 'checkbox',
                '#title' => $this->t('Send also via email (note: html formated text may not be fully displayed.)'),
            ];
        }

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => array('container-inline')],
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send message'),
        ];
        
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('users') != 'broadcast' && $form_state->getValue('users') == '') {
            $form_state->setErrorByName('users', $this->t('there is no recipient'));
        } elseif($form_state->getValue('users') != 'broadcast') {
            $users = explode(',', $form_state->getValue('users'));
            $error = '';
            $list_ids = '';
            foreach ($users as $u) {
                if (trim($u) != null) {
                    //check it is a registered user
                    $uname = trim($u);
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u', ['uid']);
                    $query->condition('name', $uname);
                    $id = $query->execute()->fetchField();
                    if (!$id) {
                        $error .= $uname . ' ';
                    } else {
                        $list_ids .= $id . ',';
                    }
                }
            }

            if (!empty($list_ids)) {
                //we save ids in array to use when sending emails
                $form_state->setValue('list_ids', $list_ids);
            }

            if ($error != '') {
                $form_state->setErrorByName("users", $this->t('Invalid user(s)') . ': ' . $error);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $message = $form_state->getValue('message');
        $priority = array('3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high'));
        if ($form_state->getValue('priority') == 1) {
            $subject = '[' . $this->t('urgent') . '] ' . Xss::filter($form_state->getValue('subject'));
        } else {
            $subject = Xss::filter($form_state->getValue('subject'));
        }

        $currentuserId = \Drupal::currentUser()->id();
        $currentuserName = \Drupal::currentUser()->getAccountName();
        $currentuserMail = \Drupal::currentUser()->getEmail();
        $error = '';

        /*
         * System message record
         */
        if($form_state->getValue('users') == 'broadcast') {
            $inbox = ',';
            $n = 0;
            foreach (\Drupal\ek_admin\Access\AccessCheck::listUsers() as $uid => $name) {
                if($uid != $currentuserId) {
                    $inbox .= $uid . ',';
                    $n++;
                }
            }
        } else {
            $inbox = ',' . $form_state->getValue('list_ids');
        }
        

        /* @TODO
         * check security implication
         * if Xss filter applied, images and links are filtered out
         * from messages
         * serialize(Xss::filter($message['value'])) or serialize($message['value'])?
         */
        $m = ek_message_register(
                array(
                    'uid' => $currentuserId,
                    'to' => $inbox,
                    'to_group' => 0,
                    'type' => 2,
                    'status' => '',
                    'inbox' => $inbox,
                    'archive' => '',
                    'subject' => $subject,
                    'body' => serialize($message['value']),
                    'format' => $message['format'],
                    'priority' => $form_state->getValue('priority'),
                )
        );

        if($form_state->getValue('users') != 'broadcast') {
            /*
             * email sending record
             * don't send email with broadcast
             */
            
            $link = Url::fromRoute('ek_messaging_read', ['id' => $m], [])->toString();
            $url = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $link]])->toString();
            $open = "<a href='" . $url . "'>" . $this->t('open') . "</a>";
            
            if ($form_state->getValue('email') == 1) {
                //send a full copy message to email address
                $params = [
                    'subject' => $subject,
                    'body' => $open . "<hr>" . $message['value'],
                    'from' => $currentuserMail,
                    'priority' => $form_state->getValue('priority'),
                    'link' => 0,
                    'url' => $url,
                ];
            } else {
                //send only a notification
                $link = Url::fromRoute('ek_messaging_read', ['id' => $m], ['absolute' => true])->toString();
                $params = [
                    'subject' => $this->t('You have a new message'),
                    'body' => $open,
                    'from' => $currentuserMail,
                    'priority' => $form_state->getValue('priority'),
                    'link' => 1,
                ];
            }
            
            $list_ids = explode(',', rtrim($form_state->getValue('list_ids'), ","));
            
            foreach (User::loadMultiple($list_ids) as $account) {
                if ($account->isActive()) {
                    $send = \Drupal::service('plugin.manager.mail')->mail(
                            'ek_messaging', 'ek_message',
                            $account->getEmail(),
                            $account->getPreferredLangcode(),
                            $params,
                            $currentuserMail,
                            true
                    );

                    if ($send['result'] == false) {
                        $error .= $account->getEmail() . ' ';
                    }
                }
            }

            if ($error != '') {
                \Drupal::messenger()->addError(t('Error sending email to @m', ['@m' => $error]));
            } else {
                \Drupal::messenger()->addStatus(t('Message @id sent', ['@id' => $m]));
            }
            
        } else {
                \Drupal::messenger()->addStatus(t('Broadcast message @id sent to @n users', ['@id' => $m, '@n' => $n]));
        }
        
        $form_state->setRedirect('ek_messaging_inbox');
    }

}
