<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Form\SettingsForm.
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_documents\Settings;

/**
 * Provides a settings form for documents parameters.
 */
class SettingsForm extends FormBase
{


  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_edit_documents_settings_form';
    }



    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
        $settings = new Settings();
  
        $default = 'csv png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $form['file_extensions'] = array(
        '#type' => 'textarea',
        '#rows' => 3,
        '#required' => true,
        '#default_value' => ($settings->get('file_extensions')) ? $settings->get('file_extensions') : $default ,
        '#title' => $this->t('Allowed extensions'),
        '#description' => $this->t('Enter file extensions separated by a space'),
      );
    
        $form['filter_char'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#required' => true,
        '#options' => array(0 => $this->t('no'), 1 => $this->t('yes')),
        '#default_value' => $settings->get('filter_char'),
        '#title' => $this->t('Filter special characters in file name'),
        '#description' => $this->t('Restrict upload'),
      );

        $form['filter_permission'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#required' => true,
        '#options' => array(0 => $this->t('no'), 1 => $this->t('yes')),
        '#default_value' => $settings->get('filter_permission'),
        '#title' => $this->t('Restrict share to user with module permission'),
        '#description' => 'Access documents management',
      );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

        return $form;
    }
  

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $settings = new Settings();
  
        $settings->set('file_extensions', $form_state->getValue('file_extensions'));
        $settings->set('filter_char', $form_state->getValue('filter_char'));
        $settings->set('filter_permission', $form_state->getValue('filter_permission'));
 
        $save = $settings->save();
  
        if ($save) {
            \Drupal::messenger()->addStatus(t('The settings are recorded'));
            if ($_SESSION['install'] == 1) {
                unset($_SESSION['install']);
                $form_state->setRedirect('ek_admin.main');
            }
        }
    }
}
