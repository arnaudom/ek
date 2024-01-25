<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\Notification.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
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

        $form['item'] = [
            '#type' => 'item',
            '#markup' => $this->t('Send a short notification regarding project @p .', array('@p' => $p->pcode)),
        ];
        
        $form['pid'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];
        
        $form['email'] = [
            '#type' => 'textarea',
            '#rows' => 2,
            '#id' => 'edit-email',
            '#attributes' => ['placeholder' => $this->t('enter user(s) separated by comma (autocomplete enabled).')],
            '#attached' => ['library' => ['ek_projects/ek_projects_autocomplete',],],
        ];


        $form['priority'] = [
            '#type' => 'radios',
            '#options' => ['3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high')],
            '#title' => $this->t('priority'),
            '#default_value' => 2,
            '#attributes' => ['class' => array('container-inline')],
        ];


        $form['message'] = [
            '#type' => 'textarea',
            '#default_value' => '',
            '#attributes' => ['placeholder' => $this->t('your message')],
        ];

        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div class='alert'>",
            '#suffix' => '</div>',
        ];

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['save'] = [
            '#id' => 'savebutton',
            '#type' => 'submit',
            '#value' => $this->t('Send'),
            '#ajax' => [
                'callback' => [$this, 'formCallback'],
                'wrapper' => 'alert',
                'method' => 'replace',
                'effect' => 'fade',
            ],
        ];
        
        $form['actions']['close'] = [
            '#id' => 'closebutton',
            '#type' => 'submit',
            '#value' => $this->t('Close'),
            '#ajax' => [
                'callback' => [$this, 'dialogClose'],
                'effect' => 'fade',
                
            ],
        ];

        return $form;
    }

    public function formCallback(array &$form, FormStateInterface $form_state) {
        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);
        if ($errors = $form_state->getErrors()) {
            $a_error =[];
            foreach ($errors as $key => $error) { ;
                $a_error[] = '#edit-' . $key;
                $r = is_array($error) ? $error->render() : $error;
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $r . "</div>"));
            }
            $error_field = new InvokeCommand(implode(',', $a_error), 'css', ['background-color', 'pink']);
            $response->addCommand($error_field);
            return $response;
        } else {
            $params = [];
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p')
                    ->fields('p',['pcode','owner'])
                    ->condition('id', $form_state->getValue('pid'))
                    ->execute();
            $p = $query->fetchObject();
            $acc = \Drupal\user\Entity\User::load($p->owner);
            $to = '';
            if ($acc) {
                $to = $acc->getEmail();
            }
            $params['text'] = Xss::filter($form_state->getValue('message'));
            $params['pcode'] = $p->pcode;
            $params['options']['url'] = ProjectData::geturl($form_state->getValue('pid'), true);
            $params['priority'] = $form_state->getValue('priority');

            $code = explode("-", $p->pcode);
            $code = array_reverse($code);
            if ($form_state->getValue('priority') == 1) {
                $params['subject'] = '[' . $this->t('urgent') . '] ' . $this->t("Notification") . ": " . $code[0] . ' | ' . $p->pcode;
            } else {
                $params['subject'] = $this->t("Notification") . ": " . $code[0] . ' | ' . $p->pcode;
            }
        
            $from = \Drupal::currentUser()->getEmail();
            $params['from'] = $from;
            $addresses = $form_state->getValue('notify');
            $error = [];
            //
            // System message record
            //
            if ($this->moduleHandler->moduleExists('ek_messaging')) {
                $inbox = ',';
                foreach ($addresses as $key => $email) {
                    if ($email != null) {
                        // convert emails address into uid
                        $query = Database::getConnection()->select('users_field_data', 'u');
                        $query->fields('u', ['uid']);
                        $query->condition('mail', $email);
                        $to = $query->execute()->fetchField();
                        $inbox .= $to . ',';
                    }
                }
                $text = $params['text'] . '<br/>'
                        . $this->t('Project ref.') . ': '
                        . $params['options']['url'];

                ek_message_register(
                        [
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
                        ]
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
                if ($email != null) {
                    if ($target_user = user_load_by_mail($email)) {
                        $target_langcode = $target_user->getPreferredLangcode();
                    } else {
                        $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                    }
                    $send = \Drupal::service('plugin.manager.mail')->mail(
                            'ek_projects', 'project_note', trim($email), $target_langcode, $params, $from, true
                    );

                    if ($send['result'] == false) {
                        $error[] = $email;
                    }
                }
            }

            if (!empty($error)) {
                $alert = new InsertCommand('.alert', "<div class='messages messages--error'>" . $this->t('Error sending to') . ': ' . implode(',',$error) . "</div>");
                $response->addCommand($alert);
                return $response;
                
            } else {
                // clear errors alerts
                $error_field = new InvokeCommand( '#edit-email', 'css', ['background-color', '']);
                $response->addCommand($error_field);
                
                $alert = new InsertCommand('.alert', "<div class='messages messages--status'>" . $this->t('Message sent') . "</div>");
                $response->addCommand($alert);$response->addCommand($alert);
                return $response;
            }
            

        }

    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        $clear = []; // use to reset errors display
        if ($form_state->getValue('email') == '') {
            $form_state->setErrorByName('email', $this->t('There is no receipient'));
        } else {
            $users = explode(',', $form_state->getValue('email'));
            $error = [];
            $notify = [];
            foreach ($users as $u) {
                if (trim($u) != '') {
                    // check it is a registered user
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u', ['uid', 'mail']);
                    $query->condition('name', trim($u));
                    $id = $query->execute()->fetchObject();
                    if (!$id) {
                        $error[] = trim($u) ;
                    } else {
                        $notify[]= $id->mail;
                    }
                }
            }

            if (!empty($error)) { 
                $form_state->setErrorByName('email', $this->t('Invalid user(s)') . ': ' . implode(',', $error));
            } else {
                $clear[] = 'email';
                $form_state->setValue('notify', $notify);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

}
