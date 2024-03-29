<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\ExportAddressBook.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a data export form.
 */
class ExportAddressBook extends FormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_address_book_export';
    }
  
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['type'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => ['%' => $this->t('All types'),'1' => $this->t('Clients'),'2' => $this->t('Suppliers'),'3' => $this->t('Others')],
              '#required' => true,
              '#attributes' => array('title' => $this->t('Select type')),

            );
        $form['source'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => ['0' => $this->t('Entities'),'1' => $this->t('Contacts')],
              '#required' => true,
              '#attributes' => array('title' => $this->t('Select source')),
            );
          
        $form['info'] = array(
              '#type' => 'item',
              '#markup' => $this->t('The export format is in excel. You can re-use the export file structure to import new data in csv format. '),

            );
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Export'));

       
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        switch ($form_state->getValue('source')) {
          
      case '0':
            $source = 'Address_book';
            $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_address_book', 'ab');
            $query->fields('ab');
            $query->condition('type', $form_state->getValue('type'), 'LIKE');
            $query->orderBy('id');
      break;
  
      case '1':
            $source = 'Address_book_contacts';
            $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc');
            $query->leftJoin('ek_address_book', 'ab', 'ab.id = abc.abid');
            $query->fields('ab', ['name']);
            $query->condition('type', $form_state->getValue('type'), 'LIKE');
            $query->orderBy('abc.id');
      break;
          
      }
      
        $data = $query->execute();
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_address_book').'/excel_export.inc';
        return $markup;
    }
}
