<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\DocAccessEdit
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form.
 */
class DocAccessEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_admin_doc_edit_access';
    }

    /**
     *
     * {@inheritdo}
     *
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $type = null) {

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company_documents', 'd')
                ->fields('d', ['share', 'deny', 'coid'])
                ->condition('id', $id);
        $data = $query->execute()->fetchObject();

        $access = AccessCheck::GetCompanyAccess($data->coid);
        $users = [];
        foreach (\Drupal\user\Entity\User::loadMultiple() as $account) {
            if ($account->isActive() && $account->id() != \Drupal::currentUser()->id()
                    && $account->id() > 0 ) {
                if (in_array($account->id(), $access[$data->coid])) {
                    $roles = $account->getRoles();
                    $users[$account->id()] = $account->getAccountName() . " [" . $roles[1] . "]";
                }
            }
        }

        $default = explode(',', $data->share);

        $form['item'] = [
            '#type' => 'item',
            '#markup' => $this->t('By default access is given to users who have access to the company '
                    . 'unless custom access has been defined by owner.'),
        ];

        $ds = ['left' => $this->t('not shared'), 'right' => $this->t('shared')];
        if ($data->share == 0) {
            $form['item2'] = [
                '#type' => 'item',
                '#markup' => $this->t('Current access: default.'),
            ];
            $ds = ['left' => $this->t('default'), 'right' => $this->t('select')];
        } else {
            $form['reset'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Reset default')
            ];
        }
        
        $form['users'] = [
            '#type' => 'select',
            '#options' => $users,
            '#multiple' => true,
            '#size' => 8,
            '#default_value' => $default,
            '#attributes' => ['class' => ['form-select-multiple'], 'style' => array('width:300px;')],
            '#attached' => [
                'drupalSettings' => $ds,
                'library' => ['ek_admin/ek_admin_multi-select'],
            ],
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];



        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['access'] = [
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#attributes' => array('class' => array('use-ajax-submit')),
        ];


        if ($form_state->get('message') <> '') {
            $form['message'] = [
                '#markup' => "<div class='red'>" . $this->t('Data') . ": " . $form_state->get('message') . "</div>",
            ];
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

        if($form_state->getValue('reset') == 1) {
            $fields = array(
                'share' => 0,
                'deny' => 0,
            );
        } else {
            // include current user by default or document could be locked
            $share = [\Drupal::currentUser()->id()];
            $deny = [];

            foreach (\Drupal\user\Entity\User::loadMultiple() as $account) {
                if (in_array($account->id(), $form_state->getValue('users'))) {
                    array_push($share, $account->id());
                } elseif($account->id() > 0 && $account->id() != \Drupal::currentUser()->id()) {
                    array_push($deny, $account->id());
                }
            }
           
            if (empty($deny)) {
                $deny = '0';
            }


            $fields = array(
                'share' => implode(',', $share),
                'deny' => implode(',', $deny),
            );
        }

        $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_company_documents')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))->execute();


        if ($update) {
            $form_state->set('message', $this->t('saved'));
            $form_state->setRebuild();
        } else {
            $form_state->set('message', $this->t('error'));
            $form_state->setRebuild();
        }
    }

}
