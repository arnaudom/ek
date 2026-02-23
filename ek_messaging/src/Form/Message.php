<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Form\Message.
 *
 * Changes from original:
 *  - Removed direct call to procedural ek_message_register().
 *  - Uses \Drupal::service('ek_messaging.message_registration')->register()
 *    instead, which handles encryption transparently.
 *  - ek_message_register() in the .module file is kept as a thin BC wrapper.
 */

namespace Drupal\ek_messaging\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\ek_messaging\Service\MessageRegistrationService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to send messages.
 */
class Message extends FormBase {

    /**
     * @var \Drupal\ek_messaging\Service\MessageRegistrationService
     */
    protected $messageRegistration;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        $instance = parent::create($container);
        $instance->messageRegistration = $container->get('ek_messaging.message_registration');
        return $instance;
    }

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
            // this is a reply / forward form
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

            // --- DECRYPTION: decrypt body before showing in reply quote ------
            $rawText = \Drupal::service('ek_messaging.encryption')->decrypt($data->text);
            // -----------------------------------------------------------------

            $account = User::load($data->from_uid);
            $to = '';
            if ($account) {
                $to = $account->getDisplayName();
            }
            $subject = $this->t('Re') . ': ' . $data->subject;
            $from = User::load($data->from_uid);
            $quote = $this->t('On @date, @user wrote', [
                '@date' => date('l jS \of F Y h:i:s A', $data->stamp),
                '@user' => $from->getAccountName(),
            ]);
            if ($data->format == 'restricted_html') {
                $text = "\r\n\r\n ------- " . $quote . " ------- \r\n" . $rawText;
            } else {
                $text = "<br><p> ------- " . $quote . " -------</p><p>" . $rawText . "</p>";
            }
        }

        if ($id != 'broadcast') {
            $form['users'] = [
                '#type'        => 'textarea',
                '#rows'        => 2,
                '#attributes'  => ['placeholder' => $this->t('enter recipients name separated by comma (autocomplete enabled).')],
                '#required'    => true,
                '#default_value' => isset($to) ? $to : null,
            ];
        } else {
            $form['info'] = [
                '#type'   => 'item',
                '#markup' => "<h1>" . $this->t('Message broadcast') . "</h1>",
            ];
            $form['users'] = [
                '#type'  => 'hidden',
                '#value' => 'broadcast',
            ];
        }

        $form['priority'] = [
            '#type'          => 'select',
            '#options'       => ['3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high')],
            '#title'         => $this->t('priority'),
            '#default_value' => isset($data->priority) ? $data->priority : null,
        ];

        $form['subject'] = [
            '#type'          => 'textfield',
            '#required'      => true,
            '#default_value' => isset($subject) ? $subject : null,
            '#attributes'    => ['placeholder' => $this->t('subject')],
        ];

        $form['message'] = [
            '#type'          => 'text_format',
            '#rows'          => 10,
            '#attributes'    => ['placeholder' => $this->t('your message')],
            '#default_value' => isset($text) ? $text : null,
            '#format'        => isset($data->format) ? $data->format : 'restricted_html',
        ];

        if ($id != 'broadcast') {
            $form['email'] = [
                '#type'  => 'checkbox',
                '#title' => $this->t('Send also via email (note: message copied via email is not encrypted.)'),
            ];
        }

        $form['actions'] = [
            '#type'       => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['submit'] = [
            '#type'  => 'submit',
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
        } elseif ($form_state->getValue('users') != 'broadcast') {
            $users    = explode(',', $form_state->getValue('users'));
            $error    = '';
            $list_ids = '';
            foreach ($users as $u) {
                if (trim($u) != null) {
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
                $form_state->setValue('list_ids', $list_ids);
            }
            if ($error != '') {
                $form_state->setErrorByName('users', $this->t('Invalid user(s)') . ': ' . $error);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $message  = $form_state->getValue('message');
        $priority = ['3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high')];

        if ($form_state->getValue('priority') == 1) {
            $subject = '[' . $this->t('urgent') . '] ' . Xss::filter($form_state->getValue('subject'));
        } else {
            $subject = Xss::filter($form_state->getValue('subject'));
        }

        $currentuserId   = \Drupal::currentUser()->id();
        $currentuserMail = \Drupal::currentUser()->getEmail();
        $error           = '';

        if ($form_state->getValue('users') == 'broadcast') {
            $inbox = ',';
            $n     = 0;
            foreach (\Drupal\ek_admin\Access\AccessCheck::listUsers() as $uid => $name) {
                if ($uid != $currentuserId) {
                    $inbox .= $uid . ',';
                    $n++;
                }
            }
        } else {
            $inbox = ',' . $form_state->getValue('list_ids');
        }

        // The service accepts the raw body string; it handles serialisation
        // detection and encryption internally.
        $m = $this->messageRegistration->register([
            'uid'      => $currentuserId,
            'to'       => $inbox,
            'to_group' => 0,
            'type'     => 2,
            'status'   => '',
            'inbox'    => $inbox,
            'archive'  => '',
            'subject'  => $subject,
            'body'     => $message['value'],  // pass raw value, not serialised
            'format'   => $message['format'],
            'priority' => $form_state->getValue('priority'),
        ]);

        // Parse body for CKEditor inline images.
        try {
            self::ckeditorSetFileUsage($message['value']);
        } catch (EntityStorageException $e) {
            $form_state->set('error', $this->t('An error occurred while saving inline image file.'));
        }

        if ($form_state->getValue('users') != 'broadcast') {
            $link = Url::fromRoute('ek_messaging_read', ['id' => $m], [])->toString();
            $url  = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $link]])->toString();
            $open = "<a href='" . $url . "'>" . $this->t('open') . "</a>";

            if ($form_state->getValue('email') == 1) {
                $params = [
                    'subject'  => $subject,
                    'body'     => $open . "<hr>" . $message['value'],
                    'from'     => $currentuserMail,
                    'priority' => $form_state->getValue('priority'),
                    'link'     => 0,
                    'url'      => $url,
                ];
            } else {
                $link = Url::fromRoute('ek_messaging_read', ['id' => $m], ['absolute' => true])->toString();
                $params = [
                    'subject'  => $this->t('You have a new message'),
                    'body'     => $open,
                    'from'     => $currentuserMail,
                    'priority' => $form_state->getValue('priority'),
                    'link'     => 1,
                    'url'      => $url,
                ];
            }

            $list_ids = explode(',', rtrim($form_state->getValue('list_ids'), ','));
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

    /**
     * Set Images / Files as Permanent.
     *
     * @param string $text
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public static function ckeditorSetFileUsage(string $text, $module = 'ckeditor') {
        $uuids = _editor_parse_file_uuids($text);
        foreach ($uuids as $uuid) {
            if ($file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $uuid)) {
                /** @var \Drupal\file\FileInterface $file */
                if ($file->isTemporary()) {
                    $file->setPermanent();
                    $file->save();
                }
                \Drupal::service('file.usage')->add($file, $module, 'file', $file->fid->value);
            }
        }
    }
}