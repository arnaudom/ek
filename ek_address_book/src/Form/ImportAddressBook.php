<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\ImportAddressBook.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a data import form.
 */
class ImportAddressBook extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_address_book_import';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {



        $form['source'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => ['0' => t('Entities'), '1' => t('Contacts')],
            '#required' => true,
            '#title' => t('Select source'),
        );
        $form['mode'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => ['0' => t('Replace (delete existing)'), '1' => t('Insert (add to existing)')],
            '#required' => true,
            '#title' => t('Select mode'),
        );

        $form['alert'] = array(
            '#type' => 'item',
            '#markup' => "<div id='fx' class='messages messages--error'>"
            . t('You are going to delete current data. Do a backup first or contact administrator if you are not sure.') . "</div>",
            '#states' => array(
                // Hide data fieldset when field is empty.
                'visible' => array(
                    "select[name='mode']" => array('value' => 0),
                ),
            ),
        );

        $form['delimiter'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [',' => t(', : comma'), ';' => t('; : semicolon')],
            '#required' => true,
            '#attributes' => array('title' => t('Select format')),
            '#description' => t('delimiter character'),
        );
        $form['enclose'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [',' => t(" ' : quote"), ';' => t(' " : double quote')],
            '#required' => true,
            '#attributes' => array('title' => t('Select enclosure')),
            '#description' => t('enclose character'),
        );

        $form['file'] = array(
            '#type' => 'file',
            '#title' => t('Upload data file'),
        );
        $form['info'] = array(
            '#type' => 'item',
            '#markup' => t('The import format should be a text csv file. '
                    . 'You can re-use the export file structure to import new data. '),
        );
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Import'));


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $extensions = 'csv';
        $validators = array('file_validate_extensions' => array($extensions));
        $file = file_save_upload("file", $validators, FALSE, 0);

        if ($file) {
            $form_state->set('data', $file);
        } else {
            $form_state->setErrorByName('file', $this->t('Wrong format of file'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('data')) {

            $file = $form_state->get('data');
            $path = "temporary://" . $file->getFileName();

            $handle = fopen($path, "r");
            $delimiter = $form_state->getValue('delimiter');
            $enclose = $form_state->getValue('enclose');
            if ($form_state->getValue('ignore') == 1) {
                $ignore = 'IGNORE 1 LINES';
            } else {
                $ignore = '';
            }

            switch ($form_state->getValue('source')) {

                case '0':
                    $destination = 'Address_book';

                    if ($form_state->getValue('mode') == 0) {
                        //replace after deletion of existing records
                        Database::getConnection('external_db', 'external_db')
                                ->delete('ek_address_book')
                                ->execute();
                        $values = 0;
                        $error = '';
                        do {
                            if ($data[0]) {

                                if ($data[0] != 'id' && is_numeric($data[0])) {

                                    //skip the header line if any
                                    $fields = ['id' => addslashes(str_replace(',', '', $data[0])),
                                        'name' => addslashes(str_replace(',', '', $data[1])),
                                        'shortname' => addslashes(str_replace(',', '', $data[2])),
                                        'address' => addslashes(str_replace(',', '', $data[3])),
                                        'address2' => addslashes(str_replace(',', '', $data[4])),
                                        'postcode' => addslashes($data[5]),
                                        'city' => addslashes($data[6]),
                                        'country' => addslashes($data[7]),
                                        'telephone' => addslashes($data[8]),
                                        'fax' => addslashes($data[9]),
                                        'website' => addslashes($data[10]),
                                        'type' => addslashes($data[11]),
                                        'category' => addslashes($data[12]),
                                        'status' => addslashes($data[13]),
                                        'stamp' => addslashes(strtotime($data[14])),
                                        'activity' => addslashes($data[15])];

                                    try {
                                        $i = Database::getConnection('external_db', 'external_db')
                                                ->insert('ek_address_book')
                                                ->fields($fields)
                                                ->execute();
                                        $values +=1;

                                        //verify if comment table has an entry. If not insert
                                        $query = "SELECT count(abid) FROM {ek_address_book_comment} WHERE abid=:id";
                                        $count = Database::getConnection('external_db', 'external_db')
                                                ->query($query, [':id' => $data[0]])
                                                ->fetchField();

                                        if ($count != 1) {
                                            Database::getConnection('external_db', 'external_db')
                                                    ->insert('ek_address_book_comment')
                                                    ->fields(['abid' => $data[0], 'comment' => ''])
                                                    ->execute();
                                        }
                                    } catch (Exception $e) {
                                        $error .= $data[1] . ' ';
                                    }
                                }
                            }
                        } while ($data = fgetcsv($handle, 0, $delimiter, $enclose));


                        drupal_set_message(t('Inserted @x row(s)', ['@x' => $values]), 'status');
                        if ($error != '') {
                            drupal_set_message(t('Error with row(s) @r', ['@r' => $error]), 'error');
                        }
                    } else {
                        //insert only
                        //loop through the csv file and insert into database
                        $values = 0;
                        $error = '';
                        do {
                            if ($data[0]) {

                                if ($data[0] != 'id' && is_numeric($data[0])) {

                                    //skip the header line if any
                                    $fields = [
                                        'name' => addslashes(str_replace(',', '', $data[1])),
                                        'shortname' => addslashes(str_replace(',', '', $data[2])),
                                        'address' => addslashes(str_replace(',', '', $data[3])),
                                        'address2' => addslashes(str_replace(',', '', $data[4])),
                                        'postcode' => addslashes($data[5]),
                                        'city' => addslashes($data[6]),
                                        'country' => addslashes($data[7]),
                                        'telephone' => addslashes($data[8]),
                                        'fax' => addslashes($data[9]),
                                        'website' => addslashes($data[10]),
                                        'type' => addslashes($data[11]),
                                        'category' => addslashes($data[12]),
                                        'status' => addslashes($data[13]),
                                        'stamp' => addslashes(strtotime($data[14])),
                                        'activity' => addslashes($data[15])];

                                    try {
                                        $i = Database::getConnection('external_db', 'external_db')
                                                ->insert('ek_address_book')
                                                ->fields($fields)
                                                ->execute();
                                        $values +=1;

                                        Database::getConnection('external_db', 'external_db')
                                                ->insert('ek_address_book_comment')
                                                ->fields(['abid' => $i, 'comment' => ''])
                                                ->execute();
                                    } catch (Exception $e) {
                                        $error .= $data[1] . ' ';
                                    }
                                }
                            }
                        } while ($data = fgetcsv($handle, 0, $delimiter, $enclose));

                        drupal_set_message(t('Inserted @x row(s)', ['@x' => $values]), 'status');
                        if ($error != '') {
                            drupal_set_message(t('Error with row(s) @r', ['@r' => $error]), 'error');
                        }
                    }


                    break;

                case '1':
                    $destination = 'Address_book_contacts';

                    if ($form_state->getValue('mode') == 0) {
                        //replace after deletion of existing records
                        Database::getConnection('external_db', 'external_db')
                                ->delete('ek_address_book_contacts')
                                ->execute();
                        //loop through the csv file and insert into database
                        $values = 0;
                        $error = '';
                        $abid = '';
                        do {
                            if ($data[0]) {

                                if ($data[0] != 'id' && is_numeric($data[0])) {

                                    //skip the header line if any
                                    $fields = [ 'id' => addslashes($data[0]),
                                        'abid' => addslashes($data[1]),
                                        'contact_name' => addslashes(str_replace(',', '', $data[2])),
                                        'salutation' => addslashes($data[3]),
                                        'title' => addslashes(str_replace(',', '', $data[4])),
                                        'telephone' => addslashes($data[5]),
                                        'mobilephone' => addslashes($data[6]),
                                        'email' => addslashes($data[7]),
                                        'card' => addslashes($data[8]),
                                        'department' => addslashes($data[9]),
                                        'link' => addslashes($data[10]),
                                        'comment' => addslashes($data[11]),
                                        'main' => addslashes($data[12]),
                                        'stamp' => addslashes(strtotime($data[13]))];

                                    try {
                                        $i = Database::getConnection('external_db', 'external_db')
                                                ->insert('ek_address_book_contacts')
                                                ->fields($fields)
                                                ->execute();
                                        $values +=1;

                                        $query = "SELECT name FROM {ek_address_book} WHERE id=:id";
                                        $name = Database::getConnection('external_db', 'external_db')
                                                        ->query($query, array(':id' => $data[1]))->fetchField();

                                        if ($name == "") {
                                            $abid .= $data[2] . ', ';
                                        }
                                    } catch (Exception $e) {
                                        $error .= $data[2] . ', ';
                                    }
                                }
                            }
                        } while ($data = fgetcsv($handle, 0, $delimiter, $enclose));

                        drupal_set_message(t('Inserted @x row(s)', ['@x' => $values]), 'status');
                        if ($error != '') {
                            drupal_set_message(t('Error with row(s) @r', ['@r' => $error]), 'error');
                        }
                        if ($abid != '') {
                            drupal_set_message(t('Following contacts are not linked to any entity: @r', ['@r' => $abid]), 'error');
                        }
                    } else {
                        //insert only
                        //loop through the csv file and insert into database
                        $values = 0;
                        $error = '';
                        $abid = '';
                        do {
                            if ($data[0]) {

                                if ($data[0] != 'id' && is_numeric($data[0])) {

                                    //skip the header line if any
                                    $fields = [
                                        'abid' => addslashes($data[1]),
                                        'contact_name' => addslashes(str_replace(',', '', $data[2])),
                                        'salutation' => addslashes($data[3]),
                                        'title' => addslashes(str_replace(',', '', $data[4])),
                                        'telephone' => addslashes($data[5]),
                                        'mobilephone' => addslashes($data[6]),
                                        'email' => addslashes($data[7]),
                                        'card' => addslashes($data[8]),
                                        'department' => addslashes($data[9]),
                                        'link' => addslashes($data[10]),
                                        'comment' => addslashes($data[11]),
                                        'main' => addslashes($data[12]),
                                        'stamp' => addslashes(strtotime($data[13]))];

                                    try {
                                        $i = Database::getConnection('external_db', 'external_db')
                                                ->insert('ek_address_book_contacts')
                                                ->fields($fields)
                                                ->execute();
                                        $values +=1;

                                        $query = "SELECT name FROM {ek_address_book} WHERE id=:id";
                                        $name = Database::getConnection('external_db', 'external_db')
                                                        ->query($query, array(':id' => $data[1]))->fetchField();

                                        if ($name == "") {
                                            $abid .= $data[2] . ', ';
                                        }
                                    } catch (Exception $e) {
                                        $error .= $data[2] . ', ';
                                    }
                                }
                            }
                        } while ($data = fgetcsv($handle, 0, $delimiter, $enclose));

                        drupal_set_message(t('Inserted @x row(s)', ['@x' => $values]), 'status');
                        if ($error != '') {
                            drupal_set_message(t('Error with row(s) @r', ['@r' => $error]), 'error');
                        }
                        if ($abid != '') {
                            drupal_set_message(t('Following contacts are not linked to any entity: @r', ['@r' => $abid]), 'error');
                        }
                    }

                    break;
            }//switch
        }//if data  
    }

}
