<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\NewAddressBookCardForm
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a new address book contact form.
 */
class NewAddressBookCardForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_address_book_card_form';
    }
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $form['back'] = array(
            '#type' => 'item',
            '#markup' => '<a href="' . $_SERVER['HTTP_REFERER'] . '" >' . t('Back to address book') . '</a>',
        );


        if (isset($id)) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $id,
            );
        }



        $vocabulary = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree('salutation', 0, 1);
        if ($vocabulary) {
            $checklist_vocab_array = array();
            foreach ($vocabulary as $item) {
                $key = $item->tid;
                $value = $item->name;
                $salutation[$key] = $value;
            }
        } else {
            $salutation = array(t('Mr.'), t('Mrs.'), t('Miss.'));
        }
    // @TODO keep this format for future 'add card option' on single form 
        $i = 0;


        $form[$i] = array(
            '#type' => 'details',
            '#title' => t('New contact card'),
            '#collapsible' => TRUE,
            '#open' => TRUE,
        );

        $form[$i]['id' . $i] = array(
            '#type' => 'hidden',
            '#default_value' => 'new',
        );

        $form[$i]['contact_name' . $i] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#attributes' => array('placeholder' => t('Contact name')),
        );


        $form[$i]['salutation' . $i] = array(
            '#type' => 'select',
            '#options' => array_combine($salutation, $salutation),
            '#required' => FALSE,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['title' . $i] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#required' => FALSE,
            '#attributes' => array('placeholder' => t('Title or function')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['ctelephone' . $i] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => t('Telephone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['cmobilephone' . $i] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => t('Mobile phone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['email' . $i] = array(
            '#type' => 'email',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => t('Email address')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['image' . $i] = array(
            '#type' => 'file',
            '#title' => t('Upload a name card image'),
            '#maxlength' => 100,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['department' . $i] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => t('Department or office')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['link' . $i] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => t('Social network')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['ccomment' . $i] = array(
            '#type' => 'textarea',
            '#rows' => 1,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form['cards'] = array(
            '#type' => 'hidden',
            '#default_value' => $i,
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));




        return $form;
        // return parent::buildForm($form, $form_state);
        //buildForm
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {
            if ($form_state->getValue('contact_name' . $i) <> '') {

                // Handle file uploads.
                //$validators = array('file_validate_extensions' => array('ico png gif jpg jpeg apng svg'));
                $validators = array('file_validate_is_image' => array());
                $field = "image" . $i;
                // Check for a new uploaded logo.
                $file = file_save_upload($field, $validators, FALSE, 0);
                if (isset($file)) {
                    // File upload was attempted.
                    if ($file) {
                        // Put the temporary file in form_values so we can save it on submit.
                        $form_state->setValue($field, $file);
                    } else {
                        // File upload failed.
                        $form_state->setErrorByName($field, $this->t('Card No. @i could not be uploaded', array('@i' => $i + 1)));
                    }
                } else {
                    $form_state->setValue($field, 0);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



    //update contact card
        if ($form_state->getValue('cards') >= 0) {
            //update cards

            for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {


                //verify  the name input and proceed only if not empty
                if ($form_state->getValue('contact_name' . $i) != '') {

                    // If the user uploaded a new card save it to a permanent location
                    // retrieve previous card file if any
                    $filename = '';

                    if (!$form_state->getValue('image' . $i) == 0) {

                        $file = $form_state->getValue('image' . $i);
                        $dir = "private://address_book/cards/" . $id;
                        file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                        $filename = file_unmanaged_copy($file->getFileUri(), $dir);
                    }



                    //end upload//


                    $fields = array(
                        'abid' => $form_state->getValue('for_id'),
                        'contact_name' => $form_state->getValue('contact_name' . $i),
                        'salutation' => $form_state->getValue('salutation' . $i),
                        'title' => $form_state->getValue('title' . $i),
                        'telephone' => $form_state->getValue('ctelephone' . $i),
                        'mobilephone' => $form_state->getValue('cmobilephone' . $i),
                        'email' => $form_state->getValue('email' . $i),
                        'card' => $filename,
                        'department' => $form_state->getValue('department' . $i),
                        'link' => $form_state->getValue('link' . $i),
                        'comment' => $form_state->getValue('ccomment' . $i),
                        'main' => $form_state->getValue('main' . $i),
                        'stamp' => strtotime("now"),
                    );

                    $insert = Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();
                } else {

                    drupal_set_message(t('Empty contact No. @i not recorded.', array('@i' => $i + 1)), 'warning');
                }
            }
        } else {

            drupal_set_message(t('No contact card available'), 'warning');
        }


        if (isset($insert)) {
            drupal_set_message(t('The address book card is recorded'), 'status');
        }

        $form_state->setRedirect('ek_address_book.view', array('abid' => $form_state->getValue('for_id')));
    }

}
