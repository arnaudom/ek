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

/**
 * Provides a form to share documents.
 */
class ShareForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_documents_share';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        //confirm the file is owned by current user to avoid access via direct link
        if (DocumentsData::validate_owner($id)) {

            $query = 'SELECT uid, filename,share,share_uid, share_gid,expire FROM {ek_documents} WHERE id=:id';
            $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

            $users = db_query('SELECT uid,name FROM {users_field_data} WHERE uid<>:u AND uid<>0 AND status <> :s order by name'
                    , array(':u' => $data->uid, ':s' => 0))->fetchAllKeyed();

            $default = explode(',', $data->share_uid);


            $form['item'] = array(
                '#markup' => t('Document') . ': ' . $data->filename,
            );


            $form['users'] = array(
                '#type' => 'select',
                '#options' => $users,
                '#multiple' => TRUE,
                '#size' => 3,
                '#attributes' => array('class' => ['form-select-multiple']),
                '#default_value' => $default,
                '#description' => t('Select in left column users to share document with'),
                '#attached' => array(
                    'drupalSettings' => array('left' => t('not shared'), 'right' => t('shared')),
                    'library' => array('ek_admin/ek_admin_multi-select'),
                ),
            );

            $form['comment'] = array(
                '#type' => 'textarea',
                '#attributes' => array('placeholder' => t('optional message')),
            );

            $form['notify'] = array(
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#attributes' => array('title' => t('Send notification')),
                '#title' => t('Send notification'),
            );

            $form['expire'] = array(
                '#type' => 'date',
                '#size' => 11,
                '#default_value' => $data->expire ? date('Y-m-d', $data->expire) : NULL,
                //'#attributes' => array('class' => array('date'), 'placeholder' => t('date') ),
                '#description' => t('Optional expiration date'),
            );

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $id,
            );

            $form['actions'] = array('#type' => 'actions');
            $form['actions']['share'] = array(
                '#id' => 'sharebuttonid',
                '#type' => 'submit',
                '#value' => t('Share'),
                '#attributes' => array('class' => array('use-ajax-submit')),
            );


            if ($form_state->get('message') <> '') {
                $form['message'] = array(
                    '#markup' => "<div class='red'>" . t('Data record') . ": " . $form_state->get('message') . "</div>",
                );
                $form_state->set('message', '');
                $form_state->setRebuild();
            }
        } else {
            //not valid owner
            $form['item'] = array(
                '#markup' => t('Your are not the owner of this file. Access denied.'),
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
                $d = t('Wrong date format input.') . ' ' . t('No expiry date set.');
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

        $log = 'doc id ' . $id . '|user ' . \Drupal::currentUser()->getUsername() . '|shared|' . $share_uid;
        \Drupal::logger('ek_documents')->notice($log);

        if ($update) {
            if ($form_state->getValue('notify')) {
                $message = Xss::filter($form_state->getValue('comment'));
                if ($expire != 0) {
                    $message .= '<br>' . t('Expiration date @d', array('@d' => date('Y-m-d', $expire)));
                }
                $data = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT uri,filename FROM {ek_documents} WHERE id=:id', array(':id' => $id))
                        ->fetchObject();
                if (\Drupal::moduleHandler()->moduleExists('ek_messaging')) {
                    $mode = 'internal message';
                } else {
                    $mode = FALSE;
                }
                
                ek_documents_message('share', $form_state->getValue('users'), $message, $data->uri, $data->filename, $mode
                );
            }


            $form_state->set('message', t('success') . '. ' . $d );
            $form_state->setRebuild();
            
        } else {
            $form_state->set('message', t('failed') . '. ' . $d);
            $form_state->setRebuild();
        }

    }

}
