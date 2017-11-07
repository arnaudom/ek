<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\SearchAddressBookForm.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a search form.
 */
class SearchAddressBookForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_address_book_search';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {



        $form['name'] = array(
            '#type' => 'textfield',
            //'#title' => t('Search'),
            '#size' => 35,
            '#required' => true,
            '#attributes' => array('placeholder' => t('Enter name or contact')),
            '#autocomplete_route_name' => 'ek.look_up_contact_ajax',
            '#prefix' => "<div class='container-inline'>",
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Search'), '#suffix' => "</div>");

        if ($form_state->get('message') <> '') {
            $form['message'] = array(
                '#markup' => "<div class=''>" . $form_state->get('message') . "</div>",
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $query = "SELECT id,name,type from {ek_address_book} where name=:c";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':c' => $form_state->getValue('name')));

        $i = 0;
        $list = '<ul>';
        $type = array(1 => t('client'), 2 => t('supplier'), 3 => t('other'));
        while ($d = $data->fetchObject()) {

            $id = $d->id;
            $url = Url::fromRoute('ek_address_book.view', array('abid' => $id), array())->toString();
            $list .= '<li><a href=' . $url . ' >' . $d->name . ' (' . $type[$d->type] . ') </a></li>';
            $i++;
        }

        if ($i == 1) {
            //there is only one entry. redirect to it
            $form_state->setRedirect('ek_address_book.view', array('abid' => $id));
        } elseif ($i == 0) {
            //there is no entry found
            drupal_set_message(t('Contact not found'), 'warning');
            $form_state->setRedirect('ek_address_book.search');
        } else {
            //there are multiple entries
            $list .='</ul>';
            $form_state->set('message', $list);
            $form_state->setRebuild();
        }
    }

}
