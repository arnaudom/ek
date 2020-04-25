<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\DeleteForm.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a form to reecord and edit purchase email alerts.
 */
class DeleteForm extends FormBase
{


  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_assets_delete_item';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $name = null, $del = null)
    {
        $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
        $form['back'] = array(
      '#type' => 'item',
      '#markup' => $this->t('<a href="@url" >Assets list</a>', array('@url' => $url )) ,

    );

        $form['edit_item'] = array(
        '#type' => 'item',
        '#markup' => $this->t('Asset : @p', array('@p' => $name)),
    );
     
    
        if ($del != '0') {
            $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,
        );

            $form['asset_pic'] = array(
          '#type' => 'hidden',
          '#value' => $data->asset_pic,
        );

            $form['asset_doc'] = array(
          '#type' => 'hidden',
          '#value' => $data->asset_doc,
        );
                
            $form['alert'] = array(
          '#type' => 'item',
          '#markup' => $this->t('Are you sure you want to delete this asset ?'),

        );
      
            $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Delete'),
        );
        } else {
            $form['alert'] = array(
          '#type' => 'item',
          '#markup' => $this->t('No access'),

        );
        }
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
        $delete = Database::getConnection('external_db', 'external_db')
          ->delete('ek_assets')
          ->condition('id', $form_state->getValue('for_id'))
          ->execute();
  
        if ($form_state->getValue('asset_pic') != '') {
            $uri = 'private://assets/' . $form_state->getValue('asset_pic');
            if (file_exists($uri)) {
                \Drupal::service('file_system')->delete($uri);
                \Drupal::messenger()->addStatus(t('The asset image is deleted'));
            }
        }
        if ($form_state->getValue('asset_doc') != '') {
            $uri = 'private://assets/' . $form_state->getValue('asset_doc');
            if (file_exists($uri)) {
                \Drupal::service('file_system')->delete($uri);
                \Drupal::messenger()->addStatus(t('The asset attachment is deleted'));
            }
        }

        $delete2 = Database::getConnection('external_db', 'external_db')
          ->delete('ek_assets_amortization')
          ->condition('asid', $form_state->getValue('for_id'))
          ->execute();
    
        if ($delete) {
            \Drupal::messenger()->addStatus(t('The asset data have been deleted'));
            $form_state->setRedirect("ek_assets.list");
        }
    }
}
