<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\ImportAddressBook.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
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
        $form['source'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => ['0' => $this->t('Entities'), '1' => $this->t('Contacts')],
            '#required' => true,
            '#title' => $this->t('Select source'),
        ];

        $form['mode'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => ['0' => $this->t('Replace (delete existing)'), '1' => $this->t('Insert (add to existing)')],
            '#required' => true,
            '#title' => $this->t('Select mode'),
        ];

        $form['alert'] = [
            '#type' => 'item',
            '#markup' => "<div id='fx' class='messages messages--error'>"
            . $this->t('You are going to delete current data. Do a backup first or contact administrator if you are not sure.') . "</div>",
            '#states' => array(
                // Hide data fieldset when field is empty.
                'visible' => array(
                    "select[name='mode']" => array('value' => 0),
                ),
            ),
        ];

        $form['delimiter'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => [',' => $this->t(', : comma'), ';' => $this->t('; : semicolon')],
            '#required' => true,
            '#attributes' => ['title' => $this->t('Select format')],
            '#description' => $this->t('delimiter character'),
        ];

        $form['enclose'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => [',' => $this->t(" ' : quote"), ';' => $this->t(' " : double quote')],
            '#required' => true,
            '#attributes' => ['title' => $this->t('Select enclosure')],
            '#description' => $this->t('enclose character'),
        ];

        $form['file'] = [
            '#type' => 'file',
            '#title' => $this->t('Upload data file'),
            '#description' => $this->t('image type allowed: csv'),
            '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'csv'],
                ],
        ];

        $form['info'] = [
            '#type' => 'item',
            '#markup' => $this->t('The import format should be a text csv file. '
                    . 'You can re-use the export file structure to import new data. '),
        ];
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Import'));


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
       $field = "file";
        $file = _file_save_upload_from_form($form[$field], $form_state, 0);
        if ($file) {
            if($errors = $form_state->getErrors()) {
                foreach ($errors as $error) {
                    $form_state->setErrorByName($field, $error);
                }
                $file->delete();
            } else {
                $form_state->set($field, $file) ;
            }           
        } else {            
                $form_state->setErrorByName($field, 'error with upload');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $file = $form_state->get('file');
        if (!$file) {
            \Drupal::messenger()->addError($this->t('No file found'));
            return;
        }

        // Get the file URI and open it
        $file_uri = $file->getFileUri();
        $handle = fopen($file_uri, "r");
        
        if (!$handle) {
            \Drupal::messenger()->addError($this->t('Could not open file'));
            return;
        }

        $delimiter = $form_state->getValue('delimiter');
        $enclose = $form_state->getValue('enclose');
        $source = $form_state->getValue('source');
        $mode = $form_state->getValue('mode');

        $values = 0;
        $error = '';
        $abid = '';

        // Read the first line to check if it's a header
        $first_line = fgetcsv($handle, 0, $delimiter, $enclose);
        $has_header = false;
        
        // Check if first row contains headers (non-numeric ID or contains 'id' text)
        if ($first_line && (!is_numeric($first_line[0]) || strtolower($first_line[0]) === 'id')) {
            $has_header = true;
        } else {
            // If no header, rewind to beginning
            rewind($handle);
        }

        switch ($source) {
            case '0': // Entities
                if ($mode == 0) {
                    // Replace mode - delete existing records
                    Database::getConnection('external_db', 'external_db')
                        ->delete('ek_address_book')
                        ->execute();
                }

                // Process CSV data
                while (($data = fgetcsv($handle, 0, $delimiter, $enclose)) !== FALSE) {
                    // Skip empty rows
                    if (empty($data[0])) {
                        continue;
                    }

                    // Skip header row if it exists and we haven't processed it yet
                    if ($has_header && !is_numeric($data[0])) {
                        $has_header = false; // Mark header as processed
                        continue;
                    }

                    // Validate that we have a numeric ID
                    if (!is_numeric($data[0])) {
                        continue;
                    }

                    // Prepare fields array with proper sanitization
                    $fields = [
                        'name' => isset($data[1]) ? trim($data[1]) : '',
                        'reg' => isset($data[2]) ? trim($data[2]) : '',
                        'shortname' => isset($data[3]) ? trim($data[3]) : '',
                        'address' => isset($data[4]) ? trim($data[4]) : '',
                        'address2' => isset($data[5]) ? trim($data[5]) : '',
                        'state' => isset($data[6]) ? trim($data[6]) : '',
                        'postcode' => isset($data[7]) ? trim($data[7]) : '',
                        'city' => isset($data[8]) ? trim($data[8]) : '',
                        'country' => isset($data[9]) ? trim($data[9]) : '',
                        'telephone' => isset($data[10]) ? trim($data[10]) : '',
                        'fax' => isset($data[11]) ? trim($data[11]) : '',
                        'website' => isset($data[12]) ? trim($data[12]) : '',
                        'type' => isset($data[13]) ? trim($data[13]) : '',
                        'category' => isset($data[14]) ? trim($data[14]) : '',
                        'status' => isset($data[15]) ? trim($data[15]) : '',
                        'stamp' => isset($data[16]) && !empty($data[16]) ? strtotime($data[16]) : time(),
                        'activity' => isset($data[17]) ? trim($data[17]) : ''
                    ];

                    // For replace mode, include the ID
                    if ($mode == 0) {
                        $fields['id'] = (int)$data[0];
                    }

                    try {
                        $insert_id = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book')
                            ->fields($fields)
                            ->execute();
                        
                        $values++;

                        // Handle comment table
                        $record_id = ($mode == 0) ? (int)$data[0] : $insert_id;
                        
                        if ($mode == 0) {
                            // For replace mode, check if comment exists
                            $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_address_book_comment', 'abco');
                            $query->condition('abid', $record_id);
                            $count = $query->countQuery()->execute()->fetchField();

                            if ($count == 0) {
                                Database::getConnection('external_db', 'external_db')
                                    ->insert('ek_address_book_comment')
                                    ->fields(['abid' => $record_id, 'comment' => ''])
                                    ->execute();
                            }
                        } else {
                            // For insert mode, always create comment record
                            Database::getConnection('external_db', 'external_db')
                                ->insert('ek_address_book_comment')
                                ->fields(['abid' => $record_id, 'comment' => ''])
                                ->execute();
                        }

                    } catch (\Exception $e) {
                        $error .= (isset($data[1]) ? $data[1] : 'Unknown') . ' ';
                        \Drupal::logger('address_book')->error('Import error: @message', ['@message' => $e->getMessage()]);
                    }
                }
                break;

            case '1': // Contacts
                if ($mode == 0) {
                    // Replace mode - delete existing records
                    Database::getConnection('external_db', 'external_db')
                        ->delete('ek_address_book_contacts')
                        ->execute();
                }

                // Process CSV data for contacts
                while (($data = fgetcsv($handle, 0, $delimiter, $enclose)) !== FALSE) {
                    // Skip empty rows
                    if (empty($data[0])) {
                        continue;
                    }

                    // Skip header row if it exists
                    if ($has_header && !is_numeric($data[0])) {
                        $has_header = false;
                        continue;
                    }

                    if (!is_numeric($data[0])) {
                        continue;
                    }

                    // Prepare fields for contacts
                    $fields = [
                        'abid' => isset($data[1]) ? (int)$data[1] : 0,
                        'contact_name' => isset($data[2]) ? trim($data[2]) : '',
                        'salutation' => isset($data[3]) ? trim($data[3]) : '',
                        'title' => isset($data[4]) ? trim($data[4]) : '',
                        'telephone' => isset($data[5]) ? trim($data[5]) : '',
                        'mobilephone' => isset($data[6]) ? trim($data[6]) : '',
                        'email' => isset($data[7]) ? trim($data[7]) : '',
                        'card' => isset($data[8]) ? trim($data[8]) : '',
                        'department' => isset($data[9]) ? trim($data[9]) : '',
                        'link' => isset($data[10]) ? trim($data[10]) : '',
                        'comment' => isset($data[11]) ? trim($data[11]) : '',
                        'main' => isset($data[12]) ? trim($data[12]) : '',
                        'stamp' => isset($data[13]) && !empty($data[13]) ? strtotime($data[13]) : time()
                    ];

                    // For replace mode, include the ID
                    if ($mode == 0) {
                        $fields['id'] = (int)$data[0];
                    }

                    try {
                        Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book_contacts')
                            ->fields($fields)
                            ->execute();
                        
                        $values++;

                        // Check if the contact is linked to an existing entity
                        $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_address_book', 'ab');
                        $query->fields('ab', ['name']);
                        $query->condition('id', $fields['abid'], '=');
                        $name = $query->execute()->fetchField();

                        if (empty($name)) {
                            $abid .= $fields['contact_name'] . ', ';
                        }

                    } catch (\Exception $e) {
                        $error .= (isset($data[2]) ? $data[2] : 'Unknown') . ', ';
                        \Drupal::logger('address_book')->error('Contact import error: @message', ['@message' => $e->getMessage()]);
                    }
                }
                break;
        }

        // Close file handle
        fclose($handle);

        // Display results
        \Drupal::messenger()->addStatus($this->t('Inserted @x row(s)', ['@x' => $values]));
        
        if (!empty($error)) {
            \Drupal::messenger()->addError($this->t('Error with row(s): @r', ['@r' => trim($error)]));
        }
        
        if (!empty($abid)) {
            \Drupal::messenger()->addError($this->t('Following contacts are not linked to any entity: @r', ['@r' => trim($abid, ', ')]));
        }
    }
}
