<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\UserSelect.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a search form.
 */
class UserSelect extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_admin_user_select';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['name'] = array(
            '#type' => 'textfield',
            '#size' => 70,
            '#required' => true,
            '#default_value' => isset($_SESSION['admin_user_select']) ? $_SESSION['admin_user_select'] : null,
            '#attributes' => array('placeholder' => $this->t('Enter user name')),
            '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Validate'));

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $query = Database::getConnection()->select('users_field_data', 'u');
        $query->fields('u', ['uid']);
        $query->condition('name', $form_state->getValue('name'));
        $data = $query->execute()->fetchField();
        if ($data) {
            $form_state->setValue('id', $data);
        } else {
            $form_state->setErrorByName('name', $this->t('Unknown user'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['admin_user_select'] = $form_state->getValue('name');
        $_SESSION['admin_user_id_select'] = $form_state->getValue('id');
    }
}
