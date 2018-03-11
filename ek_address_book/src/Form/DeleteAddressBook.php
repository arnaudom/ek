<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\DeleteAddressBook.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to delete address book main entry.
 */
class DeleteAddressBook extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_address_book_delete_';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $abid = NULL) {


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book', 'ab');
        $query->fields('ab', ['name', 'type']);
        $query->condition('id', $abid, '=');
        $data = $query->execute()->fetchObject();


        $types = [1 => t('client'), 2 => t('supplier'), 3 => t('other')];
        $form['delete'] = array(
            '#type' => 'item',
            '#markup' => '<h2>' . $data->name . ' (' . $types[$data->type] . ')</h2>',
        );
        $form['abid'] = array(
            '#type' => 'hidden',
            '#value' => $abid,
        );


        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Confirm delete (including attached namecards)'),
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


       
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_address_book')
                ->condition('id', $form_state->getValue('abid'))
                ->execute();

        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_address_book_contacts')
                ->condition('abid', $form_state->getValue('abid'))
                ->execute();

        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_address_book_comment')
                ->condition('abid', $form_state->getValue('abid'))
                ->execute();
        
        if ($delete) {
            \Drupal::messenger()->addStatus(t('The address book entry has been deleted'));
            $form_state->setRedirect("ek_address_book.search");
        }
    }

}
