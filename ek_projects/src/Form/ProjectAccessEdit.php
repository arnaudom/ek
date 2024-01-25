<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\ProjectAccessEdit
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
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
            
        } else {
            // select data from documents
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_documents', 'd');
            $query->fields('d', ['share', 'deny', 'pcode']);
            $query->leftJoin('ek_project', 'p', 'p.pcode = d.pcode');
            $query->fields('p', ['cid', 'owner']);
            $query->condition('d.id', $id);
        }

        $data = $query->execute()->fetchObject();
        $form_state->set('data', $data);
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
            // no custom settings
            // default users are selected
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

        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div class='alert'>",
            '#suffix' => '</div>',
        ];
   
        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        if (!$disabled) {
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
        }

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
                    $data = $form_state->get('data');

                    if (\Drupal::currentUser()->id() == $data->owner) {
                        // owner can edit data
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
                        // the values cannot be changed
                        $owner = 0;
                    }
        
                    $response = new AjaxResponse();
                    $clear = new InvokeCommand('.alert', "html", [""]);
                    $response->addCommand($clear);
                    if ($owner == '0') {
                        $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--warning'>" . $this->t('unchanged') . "</div>"));
                        
                    } elseif ($update) {
                        Cache::invalidateTags(['project_page_view']);
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

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}
    

}
