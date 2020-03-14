<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\DocAccessEdit
 */

namespace Drupal\ek_sales\Form;

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
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $type = NULL) {

        //if($type == 'sales_doc') {} 

        $query = "SELECT share,deny,abid FROM {ek_sales_documents} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);

        if ($data->share == 0) {
            //no custom settings
            //default users are selected
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
            '#markup' => '<span class="help">' . t('By default access is given to users who have access to the address book unless custom access has been defined by owner. Use "Ctrl C" to select multiple users in the box below.') . '</span>',
        );

        $form['users'] = array(
            '#type' => 'select',
            '#options' => $users,
            '#multiple' => TRUE,
            '#size' => 8,
            '#default_value' => $default_users,
            '#attributes' => array('class' => ['form-select-multiple']),
            '#attached' => array(
                'drupalSettings' => array('left' => t('Restricted'), 'right' => t('Allowed')),
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

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['access'] = array(
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' => t('Save'),
            '#attributes' => array('class' => array('use-ajax-submit')),
        );

        $form['#attached']['css'][] = array(
            'data' => drupal_get_path('module', 'ek_sales') . '/css/ek_sales.css',
            'type' => 'file',
        );

        if ($form_state->get('message') <> '') {
            $form['message'] = array(
                '#markup' => "<div class='red'>" . t('Data') . ": " . $form_state->get('message') . "</div>",
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }

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
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //set a security check in order to prevent any user to change data except the owner
        if ($form_state->getValue('type') == 'sales_doc') {
            $query = "SELECT share,deny,abid FROM {ek_sales_documents} WHERE id=:id";
        }
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();


        //owner can edit data
        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);
        $share = explode(',', $data->share);
        $deny = explode(',', $data->deny);
        $new_share = array();
        $new_deny = array();

        foreach ($users as $uid => $name) {
            if (in_array($uid, $form_state->getValue('users'))) {
                array_push($new_share, $uid);
            } else {
                array_push($new_deny, $uid);
            }
        }

        if (empty($new_deny))
            $new_deny = '0';

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

        if ($update) {
            $form_state->set('message', t('saved'));
            $form_state->setRebuild();
        } else {
            $form_state->set('message', t('error'));
            $form_state->setRebuild();
        }
    }

}
