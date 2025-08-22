<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\NewAddressBookCardForm
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\File\FileSystemInterface;
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
    public function buildForm(array $form, FormStateInterface $form_state, $abid = null) {
        $form['back'] = [
            '#type' => 'item',
            '#markup' => '<a href="' . $_SERVER['HTTP_REFERER'] . '" >' . $this->t('Back to address book') . '</a>',
        ];


        if (isset($abid)) {
            $form['for_id'] = [
                '#type' => 'hidden',
                '#default_value' => $abid,
            ];
        }

        /*pull names from other cards*/
        $form['copy'] = [
            '#type' => 'details',
            '#title' => $this->t('Copy existing card'),
            '#collapsible' => true,
            '#open' => true,
        ];

        $form['copy']['names'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Enter names to copy'),
            '#attributes' => array('class' => ['form-select-tag']),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_tageditor'),
                'drupalSettings' => array('auto_complete' => 'look_up_contact_ajax/4'),
            ),
        ];
        
        $salutation = array('-', $this->t('Mr.'), $this->t('Mrs.'), $this->t('Miss.'));
       
        if ($vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('salutation', 0, 1)) {
            foreach ($vocabulary as $item) {
                array_push($salutation, $item->name);
            }
        }
        // @TODO keep this format for future 'add card option' on single form
        $i = 0;


        $form[$i] = [
            '#type' => 'details',
            '#title' => $this->t('New contact card'),
            '#collapsible' => true,
            '#open' => true,
        ];

        $form[$i]['id' . $i] = [
            '#type' => 'hidden',
            '#default_value' => 'new',
        ];

        $form[$i]['contact_name' . $i] = [
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#attributes' => array('placeholder' => $this->t('Contact name')),
        ];


        $form[$i]['salutation' . $i] = [
            '#type' => 'select',
            '#options' => array_combine($salutation, $salutation),
            '#required' => false,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['title' . $i] = [
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#required' => false,
            '#attributes' => array('placeholder' => $this->t('Title or function')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['ctelephone' . $i] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => $this->t('Telephone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['cmobilephone' . $i] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => $this->t('Mobile phone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['email' . $i] = [
            '#type' => 'email',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => $this->t('Email address')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        // file is not managed by Drupal
        $allowed = 'png jpg jpeg';
        $upload_validators = ['FileExtension' => ['extensions' => $allowed]];
        $form[$i]['image' . $i] = [
            '#type' => 'file',
            '#title' => $this->t('Upload a name card image'),
            '#upload_validators' => $upload_validators,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['department' . $i] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => $this->t('Department or office')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['link' . $i] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => $this->t('Social network')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form[$i]['ccomment' . $i] = [
            '#type' => 'textarea',
            '#rows' => 1,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        ];

        $form['cards'] = [
            '#type' => 'hidden',
            '#default_value' => $i,
        ];

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Record')];

        return $form;
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
        
        //validate copies
        if ($form_state->getValue('names')) {
            $parts = explode(",", $form_state->getValue('names'));
            $invalid = [];
            $ids = [];
            foreach ($parts as $key => $name) {
                $str = explode("[", $name);
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book_contacts', 'abc');
                $query->fields('abc', ['id']);
                $query->condition('contact_name', trim($str[0]), '=');
                $data = $query->execute()->fetchObject();
                
                if (!$data->id) {
                    $invalid[] = $name;
                } else {
                    $ids[] = $data->id;
                }
            }
            if (!empty($ids)) {
                $form_state->set('ids', $ids);
            }
            
            if (!empty($invalid)) {
                $form_state->setErrorByName('names', $this->t('Unknown name(s) to copy: @n', array('@n' => implode(',', $invalid))));
            }
        }

        if ($form_state->getValue('contact_name0')) {
            for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {
                if ($form_state->getValue('contact_name' . $i) <> '') {

                    $file = _file_save_upload_from_form($form[$i]["image" . $i], $form_state, 0);
                    if ($file) {
                        $form_state->set('namecard'.$i, $file);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //copy cards
        if ($form_state->get('ids')) {
            foreach ($form_state->get('ids') as $key => $id) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book_contacts', 'abc');
                $query->fields('abc');
                $query->condition('id', $id, '=');
                $data = $query->execute()->fetchObject();
                
                $fields = array(
                        'abid' => $form_state->getValue('for_id'),
                        'contact_name' => $data->contact_name,
                        'salutation' => $data->salutation,
                        'title' => $data->title,
                        'telephone' => $data->telephone,
                        'mobilephone' => $data->mobilephone,
                        'email' => $data->email,
                        'card' => $data->card,
                        'department' => $data->department,
                        'link' => $data->link,
                        'comment' => $data->comment,
                        'main' => 0,
                        'stamp' => strtotime("now"),
                    );

                $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book_contacts')->fields($fields)->execute();
            }
        }

        //update contact card
        if ($form_state->getValue('contact_name0')) {
            //update cards
            for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {

                //verify  the name input and proceed only if not empty
                if ($form_state->getValue('contact_name' . $i) != '') {

                    // If the user uploaded a new card save it to a permanent location
                    // retrieve previous card file if any
                    $card = '';

                    if (!empty($form_state->get('namecard' .$i))) {
                        $file = $form_state->get('namecard' .$i);  
                        $dir = "private://address_book/cards/" . $form_state->getValue('for_id');
                        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                        $card = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
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
                        'card' => $card,
                        'department' => $form_state->getValue('department' . $i),
                        'link' => $form_state->getValue('link' . $i),
                        'comment' => $form_state->getValue('ccomment' . $i),
                        'main' => $form_state->getValue('main' . $i),
                        'stamp' => strtotime("now"),
                    );

                    $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book_contacts')->fields($fields)->execute();
                } else {
                    //\Drupal::messenger()->addWarning(t('Empty contact No. @i not recorded.', ['@i' => $i + 1]));
                }
            }
        } else {
            \Drupal::messenger()->addWarning(t('No contact card available'));
        }


        if (isset($insert)) {
            \Drupal::messenger()->addStatus(t('The address book card is recorded'));
        }

        \Drupal\Core\Cache\Cache::invalidateTags(['address_book_card']);
        $form_state->setRedirect('ek_address_book.view', array('abid' => $form_state->getValue('for_id')));
    }
}
