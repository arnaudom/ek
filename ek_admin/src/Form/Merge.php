<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\Merge
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Schema;

/**
 * Provides a form.
 */
class Merge extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_admin_merge';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $info = $this->t('You can merge data from another EK account with the current database. 
            The source file must be a sql format file extracted from the EK account 
            you want to merge with this account. The sql file must include `CREATE TABLE` and `INSERT` 
            scripts of the exact table name you want to merge. <br/>Once merged, you cannot undo the merging of data. 
            If you are not sure, backup the current database as safety precaution. 
            Also when merging data you may loose some information links (foreign keys) between tables.
            You can only correct this manually once merged.');
        
        $form['info'] = array(
            '#markup' => $info,
            '#prefix' => '<div>',
            '#suffix' => '</div>',
        );

        $form['upload'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#description' => $this->t('Select a sql file containing the data table to merge'),
            //'#required' => TRUE,
        );

        $form['table'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('Table target name')),
            '#required' => true,
        );


        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Merge'),
        );


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $table = Xss::filter($form_state->getValue('table'));
        $true = Database::getConnection('external_db', 'external_db')
                ->schema()
                ->tableExists($table);

        if (!$true) {
            $form_state->setErrorByName('table', $this->t('The target table does not exist.'));
        }

        $extensions = 'sql zip';
        $validators = array('file_validate_extensions' => array($extensions));
        $file = file_save_upload("upload", $validators, false, 0);

        if ($file) {
            $form_state->set('new_upload', $file);
        } else {
            $form_state->setErrorByName('upload', $this->t('No file to upload.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $file = $form_state->get('new_upload');
        $table = Xss::filter($form_state->getValue('table'));
        $markup = '';
        if ($file) {
            $uri = $file->getFileUri();

            $handle = fopen($uri, "rb");
            //When reading from anything that is not a regular local file, such as streams
            //reading will stop after a packet is available. This means that
            //should collect the data together in chunks
            $content = '';
            while (!feof($handle)) {
                $content .= fread($handle, 8192);
            }
            fclose($handle);

            $str = strpos($content ?? "", "`" . $table . "`");
            if ($str) {
                $tpm_table = 'tmp_' . $table;
                $content = str_replace($table, $tpm_table, $content);

                $parts = explode(';', $content);
                foreach ($parts as $k => $string) {
                    if (stripos($string, 'create table') || stripos($string, 'insert into')) {
                        try {
                            $temp = Database::getConnection('external_db', 'external_db')
                                ->query($string);
                        } catch (Exception $e) {
                            $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
                        }
                    }
                }

                //filter primary and auto incremented fields
                $query = "SELECT COLUMN_NAME, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS "
                        . "WHERE TABLE_SCHEMA = :db "
                        . "AND TABLE_NAME = :table";
                $db = Database::getConnectionInfo('external_db');
                $a = array(':table' => $table, ':db' => $db['default']['database']);
                $result = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a);

                $fields = [];

                while ($r = $result->fetchObject()) {
                    if ($r->COLUMN_KEY != 'PRI' || $r->EXTRA != 'auto_increment') {
                        array_push($fields, $r->COLUMN_NAME);
                    }
                }

                $field_list = implode(',', $fields);

                $query = "INSERT IGNORE INTO {" . $table . "} "
                        . "(" . $field_list . ") "
                        . "SELECT " . $field_list . " FROM {" . $tpm_table . "}";
                $merge = Database::getConnection('external_db', 'external_db')
                        ->query($query);


                $query = "DROP TABLE {" . $tpm_table . "}";
                Database::getConnection('external_db', 'external_db')
                        ->query($query);
                
                if ($merge) {
                    \Drupal::messenger()->addStatus(t('Data have been merged with target table @tb.', ['@tb' => $table]));
                }
                
                if ($markup != '') {
                    \Drupal::messenger()->addError($markup);
                }
            } else {
                $e = 'Error while trying to read the source file. '
                      . 'Please check if table name of source file matches the target table name (@tb).';
                \Drupal::messenger()->addError(t($e, ['@tb' => $table]));
            }
        }
    }
}
