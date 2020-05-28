<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\AccessRequest.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form for user to request access to a project.
 */
class AccessRequest extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_access_request';
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
            '#markup' => $this->t('Sorry, you do not have access to this project.<br/> You can request access to the owner of this project @p with the form below.', array('@p' => $p->pcode)),
        );
        $form['pid'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );


        $form['message'] = array(
            '#type' => 'textarea',
            '#default_value' => $this->t('@u is requesting access to project @p owned by you.', array('@u' => $currentusername, '@p' => $p->pcode)),
            '#attributes' => array('placeholder' => $this->t('optional text message')),
            '#title' => $this->t('Message to owner'),
        );



        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Send request'),
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
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $query = "SELECT pcode,owner from {ek_project} WHERE id=:id";
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('pid')))
                ->fetchObject();
        //$query = "SELECT mail from {users_field_data} WHERE uid=:u";
        //$to = db_query($query, array(':u' => $p->owner))
        //        ->fetchField();
        $acc = \Drupal\user\Entity\User::load($p->owner);
        $to = '';
        if ($acc) {
            $to = $acc->getEmail();
            $params = [];
            if (\Drupal::moduleHandler()->moduleExists('swiftmailer')) {
                $params['body'] = Xss::filter($form_state->getValue('message'));
                $params['options']['pcode'] = $p->pcode;
                $params['options']['url'] = ProjectData::geturl($form_state->getValue('pid'), null, 1, null, $this->t('Open'));
            } else {
                $params['body'] = Xss::filter($form_state->getValue('message')) . '\r\n'
                        . $this->t('Project ref.') . ': ' . ProjectData::geturl($form_state->getValue('pid'), 0, 1);
                $params['options'] = '';
            }
            $code = explode("-", $p->pcode);
            $code = array_reverse($code);
            $params['subject'] = $this->t("Access request") . ": " . $code[0] . ' | ' . $p->pcode;
            $acc2 = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            $from = '';
            if ($acc2) {
                $from = $acc2->getEmail();
            }

            $send = \Drupal::service('plugin.manager.mail')->mail(
                    'ek_projects', 'project_access', $to, $acc->getPreferredLangcode(), $params, $from, true
            );

            if ($send['result'] == true) {
                \Drupal::messenger()->addStatus(t('The request has been sent'));
                $form_state->setRedirect('ek_projects_main');
            }
        } else {
            \Drupal::messenger()->addWarning(t('Project owner cannot be reached'));
        }
    }

}
