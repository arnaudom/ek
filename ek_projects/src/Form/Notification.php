<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\Notification.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to send notification.
 */
class Notification extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_send_notification';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $query = "SELECT pcode,owner from {ek_project} WHERE id=:id";
        $p = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
        $currentusername = \Drupal::currentUser()->getAccountName();

        $form['item'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Send a short notification regarding project @p .', array('@p' => $p->pcode)),
        );
        $form['pid'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );


        $form['email'] = array(
            '#type' => 'textarea',
            '#rows' => 2,
            '#id' => 'edit-email',
            '#attributes' => array('placeholder' => $this->t('enter user(s) separated by comma (autocomplete enabled).')),
            '#attached' => array(
                'library' => array(
                    'ek_projects/ek_projects_autocomplete',
                ),
            ),
        );


        $form['priority'] = array(
            '#type' => 'radios',
            '#options' => array('3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high')),
            '#title' => $this->t('priority'),
            '#default_value' => 2,
            '#attributes' => array('class' => array('container-inline')),
        );


        $form['message'] = array(
            '#type' => 'textarea',
            '#default_value' => '',
            '#attributes' => array('placeholder' => $this->t('your message')),
        );

        if ($form_state->get('alert') <> '') {
            $form['alert'] = array(
                '#markup' => "<div class='red'>" . $form_state->get('alert') . "</div>",
            );
            $form_state->set('error', '');
            $form_state->setRebuild();
        }


        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Send note'),
            '#attributes' => array('class' => array('use-ajax-submit')),
            '#attached' => array(
                'library' => array(
                    'core/jquery.form',
                ),
            ),
        );
        //, 'core/jquery.form', 'core/drupal.form', 'core/drupal.ajax'];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('email') == '') {
            $form_state->set('alert', $this->t('there is no receipient'));
            $form_state->setRebuild();
            //$form_state->setErrorByName('email', $this->t('there is no receipient'));
        } else {
            $users = explode(',', $form_state->getValue('email'));
            $error = '';
            $notify_who = '';
            foreach ($users as $u) {
                if (trim($u) != '') {
                    //check it is a registered user
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u', ['uid', 'mail']);
                    $query->condition('name', trim($u));
                    $id = $query->execute()->fetchObject();

                    //$query = "SELECT uid,mail from {users_field_data} WHERE name=:u";
                    //$result = db_query($query, array(':u' => trim($u)))->fetchObject();
                    if (!$id) {
                        $error .= $u . ',';
                    } else {
                        $notify_who .= $id->mail . ',';
                    }
                }
            }

            if ($error <> '') {
                //$form_state->setErrorByName("email", $this->t('Invalid user(s)') . ': ' . $error);
                $form_state->set('alert', $this->t('Invalid user(s)') . ': ' . rtrim($error, ','));
                $form_state->setRebuild();
            } else {
                $form_state->setValue('notify_who', rtrim($notify_who, ','));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $params = array();
        $query = "SELECT pcode,owner from {ek_project} WHERE id=:id";
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('pid')))
                ->fetchObject();
        //$query = "SELECT mail from {users_field_data} WHERE uid=:u";
        //$to = db_query($query, array(':u' => $p->owner))->fetchField();
        $acc = \Drupal\user\Entity\User::load($p->owner);
        $to = '';
        if ($acc) {
            $to = $acc->getEmail();
        }
        $params['text'] = Xss::filter($form_state->getValue('message'));
        $params['options']['pcode'] = $p->pcode;
        $params['options']['url'] = ProjectData::geturl($form_state->getValue('pid'), true);
        $params['options']['priority'] = $form_state->getValue('priority');
        $priority = array('3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high'));

        $code = explode("-", $p->pcode);
        $code = array_reverse($code);
        if ($form_state->getValue('priority') == 1) {
            $params['subject'] = '[' . $this->t('urgent') . '] ' . $this->t("Notification") . ": " . $code[0] . ' | ' . $p->pcode;
        } else {
            $params['subject'] = $this->t("Notification") . ": " . $code[0] . ' | ' . $p->pcode;
            ;
        }

        $acc2 = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        $from = '';
        if ($acc2) {
            $from = $acc2->getEmail();
            $params['from'] = $from;
        }
        $addresses = explode(',', $form_state->getValue('notify_who'));
        $error = '';

        //
        // System message record
        //
        if ($this->moduleHandler->moduleExists('ek_messaging')) {
            $inbox = ',';
            foreach ($addresses as $key => $email) {
                if (trim($email) != null) {
                    //convert emails address into uid
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u', ['uid']);
                    $query->condition('mail', trim($email));
                    $to = $query->execute()->fetchField();
                    $inbox .= $to . ',';
                }
            }

            $text = $params['text'] . '<br/>'
                    . $this->t('Project ref.') . ': '
                    . $params['options']['url'];

            ek_message_register(
                    array(
                        'uid' => \Drupal::currentUser()->id(),
                        'to' => $inbox,
                        'to_group' => 0,
                        'type' => 2,
                        'status' => '',
                        'inbox' => $inbox,
                        'archive' => '',
                        'subject' => $params['subject'],
                        'body' => serialize($text),
                        'priority' => $form_state->getValue('priority'),
                    )
            );
        }

        //
        // email sending record
        //
        
        $text = "<p>" . $params['text'] . ".</p>";
        $text .= "<p>" . $this->t('Project ref.') . ': ' . $code[0] . ' | ' . $p->pcode . ".</p>";
        
        $params['body'] = $text;
        $l = Url::fromRoute('ek_projects_view', ['id' => $form_state->getValue('pid')],['query' => []])->toString();
        $params['options']['url'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $l]])->toString();
        
        foreach ($addresses as $email) {
            if (trim($email) != null) {
                if ($target_user = user_load_by_mail($email)) {
                    $target_langcode = $target_user->getPreferredLangcode();
                } else {
                    $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                }
                $send = \Drupal::service('plugin.manager.mail')->mail(
                        'ek_projects', 'project_note', trim($email), $target_langcode, $params, $from, true
                );

                if ($send['result'] == false) {
                    $error .= $email . ' ';
                }
            }
        }

        if ($error <> '') {
            $form_state->set('alert', $this->t('Error sending to') . ': ' . rtrim($error, ','));
        } else {
            $form_state->set('alert', $this->t('Message sent'));
        }
        $form_state->setRebuild();
    }

}
