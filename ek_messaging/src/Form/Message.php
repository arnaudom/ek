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
        if ($id != null) {
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
            $subject = t('Re') . ': ' . $data->subject;
            $from = \Drupal\user\Entity\User::load($data->from_uid);
            $quote = t('On @date, @user wrote', ['@date' => date('l jS \of F Y h:i:s A', $data->stamp), '@user' => $from->getAccountName()]);
            if ($data->format == 'restricted_html') {
                $text = "\r\n\r\n\r\n\r\n ------- " . $quote . " ------- \r\n\r\n" . $data->text;
            } else {
                $text = "<br><p> ------- " . $quote . " -------</p><p>" . $data->text . "</p>";
            }
        }
        $form['users'] = array(
            '#type' => 'textarea',
            '#rows' => 2,
            '#attributes' => array('placeholder' => t('enter recipients name separated by comma (autocomplete enabled).')),
            '#required' => true,
            '#default_value' => isset($to) ? $to : null,
        );

        $form['priority'] = array(
            '#type' => 'select',
            '#options' => array('3' => t('low'), '2' => t('normal'), '1' => t('high')),
            '#title' => t('priority'),
            '#default_value' => isset($data->priority) ? $data->priority : null,
        );

        $form['subject'] = array(
            '#type' => 'textfield',
            '#default_value' => '',
            '#required' => true,
            '#default_value' => isset($subject) ? $subject : null,
            '#attributes' => array('placeholder' => t('subject')),
        );

        $form['message'] = array(
            '#type' => 'text_format',
            '#rows' => 10,
            '#attributes' => array('placeholder' => t('your message')),
            '#default_value' => isset($text) ? $text : null,
            '#format' => isset($data->format) ? $data->format : 'restricted_html',
        );

        $form['email'] = array(
            '#type' => 'checkbox',
            '#title' => t('Send also via email (note: html formated text may not be fully displayed.)'),
        );


        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Send message'),
        );





        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('users') == '') {
            $form_state->setErrorByName('users', $this->t('there is no recipient'));
        } else {
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
                $form_state->setErrorByName("users", t('Invalid user(s)') . ': ' . $error);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $message = $form_state->getValue('message');
        $priority = array('3' => t('low'), '2' => t('normal'), '1' => t('high'));
        if ($form_state->getValue('priority') == 1) {
            $subject = '[' . t('urgent') . '] ' . Xss::filter($form_state->getValue('subject'));
        } else {
            $subject = Xss::filter($form_state->getValue('subject'));
        }

        $currentuserId = \Drupal::currentUser()->id();
        $currentuserName = \Drupal::currentUser()->getAccountName();
        $currentuserMail = \Drupal::currentUser()->getEmail();
        $users = explode(',', $form_state->getValue('users'));
        $error = '';

        /*
         * System message record
         */

        $inbox = ',' . $form_state->getValue('list_ids');

        /* TO DO
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

        /*
         * email sending record
         */
        if ($form_state->getValue('email') == 1) {
            //send a full copy message to email address
            $params = [
                'subject' => $subject,
                'body' => $message['value'],
                'from' => $currentuserMail,
                'priority' => $form_state->getValue('priority'),
                'link' => 0,
            ];
        } else {
            //send only a notification

            $link = Url::fromRoute('ek_messaging_read', array('id' => $m), ['absolute' => true])->toString();
            $params = [
                'subject' => t('You have a new message'),
                'body' => "<a href='" . $link . "'>" . t('read') . "</a>",
                'from' => $currentuserMail,
                'priority' => $form_state->getValue('priority'),
                'link' => 1,
            ];
        }


        $list_ids = explode(',', rtrim($form_state->getValue('list_ids'), ","));

        foreach (User::loadMultiple($list_ids) as $account) {
            if ($account->isActive()) {
                $send = \Drupal::service('plugin.manager.mail')->mail(
                        'ek_messaging', 'ek_message', $account->getEmail(), $account->getPreferredLangcode(), $params, $currentuserMail, true
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

        $form_state->setRedirect('ek_messaging_inbox');
    }

}
