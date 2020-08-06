<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Form\ShareForm
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use DateTime;
use Drupal\ek_documents\DocumentsData;
use Drupal\ek_documents\Settings;

/**
 * Provides a form to share documents.
 */
class ShareForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_documents_share';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        //confirm the file is owned by current user to avoid access via direct link
        if (DocumentsData::validate_owner($id)) {
            $settings = new Settings();
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d', ['uid', 'filename', 'share', 'share_uid', 'share_gid', 'expire']);
            $query->condition('id', $id);
            $data = $query->execute()->fetchObject();
                                    
            $users = [];
            foreach (\Drupal\user\Entity\User::loadMultiple() as $account) {
                if ($account->isActive() && $account->id() != $data->uid) {
                    if ($settings->get('filter_permission') == '1' && $account->hasPermission('manage_documents')) {
                        $users[$account->id()] = $account->getAccountName();
                    } elseif ($settings->get('filter_permission') == '0') {
                        $roles = $account->getRoles();
                        $users[$account->id()] = $account->getAccountName() . " [" . $roles[1] . "]";
                    }
                }
            }
            
            $default = explode(',', $data->share_uid);

            $form['item'] = array(
                '#markup' => $this->t('Document') . ': ' . $data->filename,
            );

            $form['users'] = array(
                '#type' => 'select',
                '#options' => $users,
                '#multiple' => true,
                '#size' => 3,
                '#attributes' => array('class' => ['form-select-multiple'], 'style' => array('width:300px;')),
                '#default_value' => $default,
                '#description' => $this->t('Select in left column users to share document with'),
                '#attached' => array(
                    'drupalSettings' => array('left' => $this->t('not shared'), 'right' => $this->t('shared')),
                    'library' => array('ek_admin/ek_admin_multi-select'),
                ),
            );

            $form['comment'] = array(
                '#type' => 'textarea',
                '#attributes' => array('placeholder' => $this->t('optional message')),
            );

            $form['notify'] = array(
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#attributes' => array('title' => $this->t('Send notification')),
                '#title' => $this->t('Send notification'),
            );

            if (\Drupal::moduleHandler()->moduleExists('ek_messaging')) {
                $priority = array('3' => $this->t('low'), '2' => $this->t('normal'), '1' => $this->t('high'));
                $form['priority'] = array(
                    '#type' => 'select',
                    '#options' => $priority,
                    '#default_value' => 2,
                    '#description' => $this->t('priority'),
                    '#states' => array(
                        'invisible' => array(
                            "input[name='notify']" => array('checked' => false),
                        ),
                    ),
                );
            } else {
                $form['priority'] = array(
                        '#type' => 'hidden',
                        '#value' => 2,
                    );
            }


            $form['expire'] = array(
                '#type' => 'date',
                '#size' => 11,
                '#default_value' => $data->expire ? date('Y-m-d', $data->expire) : null,
                //'#attributes' => array('class' => array('date'), 'placeholder' => $this->t('date') ),
                '#description' => $this->t('Optional expiration date'),
            );

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $id,
            );

            $form['actions'] = array('#type' => 'actions');
            $form['actions']['share'] = array(
                '#id' => 'sharebuttonid',
                '#type' => 'submit',
                '#value' => $this->t('Share'),
                '#attributes' => array('class' => array('use-ajax-submit')),
            );


            if ($form_state->get('message') <> '') {
                $form['message'] = array(
                    '#markup' => "<div class='red'>" . $this->t('Data record') . ": " . $form_state->get('message') . "</div>",
                );
                $form_state->set('message', '');
                $form_state->setRebuild();
            }
        } else {
            //not valid owner
            $form['item'] = array(
                '#markup' => $this->t('Your are not the owner of this file. Access denied.'),
            );
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
        $id = $form_state->getValue('for_id');

        $expire = 0;
        $d = '';

        if ($form_state->getValue('expire') != '') {
            if (DateTime::createFromFormat('Y-m-d', $form_state->getValue('expire'))) {
                $expire = strtotime($form_state->getValue('expire'));
            } else {
                $d = $this->t('Wrong date format input.') . ' ' . $this->t('No expiry date set.');
            }
        }

        //prepare the sharing list
        $share_uid = ',' . implode(',', $form_state->getValue('users')) . ',';
        if ($share_uid == ',,') {
            $share = 0;
            $share_uid = 0;
        } else {
            $share = 1;
        }

        //record the list
        $fields = array(
            'share' => $share,
            'share_uid' => $share_uid,
            'expire' => $expire,
        );
        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_documents')
                ->fields($fields)
                ->condition('id', $id)
                ->execute();

        $log = 'doc id ' . $id . '|user ' . \Drupal::currentUser()->getAccountName() . '|shared|' . $share_uid;
        \Drupal::logger('ek_documents')->notice($log);

        if ($update) {
            
            // record user.data
            $userData = \Drupal::service('user.data');
            foreach (\Drupal\user\Entity\User::loadMultiple($form_state->getValue('users')) as $account) {
                if ($account) {
                    $userData->set('ek_documents', $account->id(), $id, 'shared');
                }
            }
            
            if ($form_state->getValue('notify')) {
                $message = Xss::filter($form_state->getValue('comment'));
                if ($expire != 0) {
                    $message .= '<br>' . $this->t('Expiration date @d', array('@d' => date('Y-m-d', $expire)));
                }
                $data = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT uri,filename FROM {ek_documents} WHERE id=:id', array(':id' => $id))
                        ->fetchObject();
                if (\Drupal::moduleHandler()->moduleExists('ek_messaging')) {
                    $mode = 'internal message';
                } else {
                    $mode = false;
                }

                ek_documents_message(
                    'share',
                    $form_state->getValue('users'),
                    $message,
                    $data->uri,
                    $data->filename,
                    $mode,
                    $form_state->getValue('priority')
                );
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['shared_documents','new_documents_shared']);
            $form_state->set('message', $this->t('success') . '. ' . $d);
            $form_state->setRebuild();
        } else {
            $form_state->set('message', $this->t('failed') . '. ' . $d);
            $form_state->setRebuild();
        }
    }
}
