<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\DocAccessEdit
 */

namespace Drupal\ek_sales\Form;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;

/**
 * Provides a form to manage access to sales documents.
 */
class DocAccessEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_doc_edit_access';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $type = null) {
                
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_documents', 'd');
        $query->fields('d', ['share', 'deny', 'abid']);
        $query->condition('id', $id);
        $data = $query->execute()->fetchObject();
        $form_state->set('data', $data);
        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);

        if ($data->share == 0) {
            // no custom settings
            // default users are selected
            $default_users = array();

            foreach ($users as $key => $value) {
                $u = User::load($key);
                if ($u->hasPermission('view_address_book')) {
                    $default_users[$key] = $value;
                }
            }
        } else {
            $default_users = explode(',', $data->share);
            $deny = explode(',', $data->deny);
        }

        $form['item'] = array(
            '#type' => 'item',
            '#markup' => '<span class="help">' . $this->t('By default access is given to users who have access to the address book unless custom access has been defined by owner. Use "Ctrl C" to select multiple users in the box below.') . '</span>',
        );

        $form['users'] = array(
            '#type' => 'select',
            '#options' => $users,
            '#multiple' => true,
            '#size' => 8,
            '#default_value' => $default_users,
            '#attributes' => array('class' => ['form-select-multiple']),
            '#attached' => array(
                'drupalSettings' => array('left' => $this->t('Restricted'), 'right' => $this->t('Allowed')),
                'library' => array('ek_admin/ek_admin_multi-select'),
            ),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );

        $form['type'] = array(
            '#type' => 'hidden',
            '#default_value' => $type,
        );

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
            '#value' => $this->t('Save'),
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

        $form['#attached']['css'][] = array(
            'data' => \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/css/ek_sales.css',
            'type' => 'file',
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * {@inheritdoc}
     */
    public function formCallback(array &$form, FormStateInterface $form_state) {

        $data = $form_state->get('data');
        // owner can edit data
        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);
        $share = explode(',', $data->share);
        $deny = explode(',', $data->deny);
        $new_share = [];
        $new_deny = [];

        foreach ($users as $uid => $name) {
            if (in_array($uid, $form_state->getValue('users'))) {
                array_push($new_share, $uid);
            } else {
                array_push($new_deny, $uid);
            }
        }

        if (empty($new_deny)) {
            $new_deny = '0';
        }

        $fields = array(
            'share' => implode(',', $new_share),
            'deny' => implode(',', $new_deny),
        );

        if ($form_state->getValue('type') == 'sales_doc') {
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_sales_documents')
                    ->fields($fields)
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        }
        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);

        if ($update) {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('saved') . "</div>"));
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('error') . "</div>"));
        }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }

}
