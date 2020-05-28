<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\ProjectAccessEdit
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Cache\Cache;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to edit user access to a project.
 */
class ProjectAccessEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_edit_access';
    }

    /**
     * {@inheritdoc}
     * @param id : project id
     * @param type: project|NULL
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $type = null) {
        if ($type == 'project') {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p');
            $query->fields('p', ['share', 'deny', 'cid', 'pcode', 'owner']);
            $query->condition('id', $id);
        } else {
            //select data from documents
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_documents', 'd');
            $query->fields('d', ['share', 'deny', 'pcode']);
            $query->leftJoin('ek_project', 'p', 'p.pcode = d.pcode');
            $query->fields('p', ['cid', 'owner']);
            $query->condition('d.id', $id);
        }

        $data = $query->execute()->fetchObject();

        $users = [];
        foreach (\Drupal\user\Entity\User::loadMultiple() as $account) {
            if ($account->isActive() && $account->id() != $data->owner && $account->hasPermission('view_project')) {
                $roles = $account->getRoles();
                $users[$account->id()] = $account->getAccountName() . " [" . $roles[1] . "]";
            }
        }

        if (\Drupal::currentUser()->id() == $data->owner) {
            $disabled = false;
            $info = '';
        } else {
            $disabled = true;
            $info = '<p class="red">' . $this->t('Only project owner can edit access.') . '</p>';
        }

        if ($data->share == 0) {
            //no custom settings
            //default users are selected
            $default_users = AccessCheck::GetCountryAccess($data->cid);
            $default_users = $default_users[$data->cid];
        } else {
            $default_users = explode(',', $data->share);
            $deny = explode(',', $data->deny);
        }

        $info .= '<p class="help">' . $this->t('By default access is given to users '
                        . 'who have access to the country of the project unless custom access has been defined by owner.') . '</p>';

        $form['item'] = array(
            '#type' => 'item',
            '#markup' => $info,
        );

        $form['users'] = array(
            '#type' => 'select',
            '#options' => $users,
            '#disabled' => $disabled,
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

        if (!$disabled) {
            $form['actions'] = array('#type' => 'actions');
            $form['actions']['access'] = array(
                '#id' => 'accessbutton',
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#attributes' => array('class' => array('use-ajax-submit')),
            );
        }

        if ($form_state->get('message') <> '') {
            $form['message'] = array(
                '#markup' => "<div class='red'>" . $this->t('Data record') . ": " . $form_state->get('message') . "</div>",
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }
        /**/
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
        if ($form_state->getValue('type') == 'project') {
            $query = "SELECT share,deny,cid,pcode,owner FROM {ek_project} WHERE id=:id";
        } else {
            //select data from documents
            $query = "SELECT d.share,d.deny,cid,d.pcode,owner FROM {ek_project_documents} d INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE d.id=:id";
        }
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();

        if (\Drupal::currentUser()->id() == $data->owner) {
            //owner can edit data
            $owner = 1;
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid', 'name']);
            $query->condition('uid', 0, '<>');
            $users = $query->execute();
            $share = explode(',', $data->share);
            $deny = explode(',', $data->deny);
            $new_share = array();
            $new_deny = array();

            while ($u = $users->fetchObject()) {
                if (in_array($u->uid, $form_state->getValue('users')) || $data->owner == $u->uid) {
                    array_push($new_share, $u->uid);
                } else {
                    array_push($new_deny, $u->uid);
                }
            }

            if (empty($new_deny)) {
                $new_deny = '0';
            }


            $fields = array(
                'share' => implode(',', $new_share),
                'deny' => $new_deny == '0' ? 0 : implode(',', $new_deny),
            );

            if ($form_state->getValue('type') == 'project') {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
            } else {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_documents')
                        ->condition('id', $form_state->getValue('for_id'))
                        ->fields($fields)
                        ->execute();
            }
        } else {
            //the values cannot be changed
            $owner = 0;
        }




        if ($owner == '0') {
            $form_state->set('message', $this->t('unchanged'));
            $form_state->setRebuild();
        } elseif ($update) {
            Cache::invalidateTags(['project_page_view']);
            $form_state->set('message', $this->t('saved'));
            $form_state->setRebuild();
        } else {
            $form_state->set('message', $this->t('error'));
            $form_state->setRebuild();
        }
    }

}
