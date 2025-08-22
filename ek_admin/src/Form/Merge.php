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

/**
 * Provides a form.
 */
class Merge extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_admin_merge';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)  {
        $info = $this->t('You can merge data from another EK account with the current database. 
            The source file must be a sql format file extracted from the EK account 
            you want to merge with this account. The sql file must include `CREATE TABLE` and `INSERT` 
            scripts of the exact table name you want to merge. <br/>Once merged, you cannot undo the merging of data. 
            If you are not sure, backup the current database as safety precaution. 
            Also when merging data you may loose some information links (foreign keys) between tables.
            You can only correct this manually once merged.');
        
        $form['info'] = [
            '#markup' => $info,
            '#prefix' => '<div>',
            '#suffix' => '</div>',
        ];

        $form['upload'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#description' => $this->t('Select a sql file containing the data table to merge'),
            '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'sql zip'],
            ],
        ];

        $form['table'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('Table target name')),
            '#required' => true,
        ];


        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#type' => 'submit',
            '#value' => $this->t('Merge'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $table = Xss::filter($form_state->getValue('table'));
        $true = Database::getConnection('external_db', 'external_db')
                ->schema()
                ->tableExists($table);

        if (!$true) {
            $form_state->setErrorByName('table', $this->t('The target table does not exist.'));
        }

        $field = "upload";
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
        $file = $form_state->get('upload');
        $table = Xss::filter($form_state->getValue('table'));
        $errors = [];
        
        if (!$file) {
            \Drupal::messenger()->addError(t('No file uploaded.'));
            return;
        }

        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            \Drupal::messenger()->addError(t('Invalid table name format.'));
            return;
        }

        $uri = $file->getFileUri();
        $file_extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        
        try {
            // Handle different file types
            if ($file_extension === 'zip') {
                $content = $this->extractSqlFromZip($uri);
            } elseif ($file_extension === 'sql') {
                $content = $this->readFileContent($uri);
            } else {
                throw new \Exception('Unsupported file type. Only SQL and ZIP files are allowed.');
            }

            // Validate that the content contains the expected table
            if (strpos($content, "`" . $table . "`") === false) {
                throw new \Exception('Source file does not contain the target table: ' . $table);
            }

            // Create temporary table name
            $tmp_table = 'tmp_' . $table . '_' . time() . '_' . rand(1000, 9999);
            $content = str_replace('`' . $table . '`', '`' . $tmp_table . '`', $content);

            // Get database connection
            $connection = Database::getConnection('external_db', 'external_db');
            
            // Start transaction for data integrity
            $transaction = $connection->startTransaction();
            
            try {
                // Execute SQL statements
                $this->executeSqlStatements($connection, $content, $errors);
                
                // Validate temporary table exists
                if (!$this->tableExists($connection, $tmp_table)) {
                    throw new \Exception('Temporary table was not created successfully.');
                }
                
                // Get non-primary, non-auto-increment fields
                $fields = $this->getNonPrimaryFields($connection, $table);
                
                if (empty($fields)) {
                    throw new \Exception('No valid fields found for merging.');
                }
                
                // Perform merge operation
                $affected_rows = $this->mergeData($connection, $table, $tmp_table, $fields);
                
                // Clean up temporary table
                $this->dropTable($connection, $tmp_table);
                
                // Commit transaction
                $transaction = null;
                
                // Success message
                \Drupal::messenger()->addStatus(t('Successfully merged @count rows into table @table.', [
                    '@count' => $affected_rows,
                    '@table' => $table
                ]));
                
                // Display any non-fatal errors
                if (!empty($errors)) {
                    \Drupal::messenger()->addWarning(t('Some statements had issues: @errors', [
                        '@errors' => implode('; ', $errors)
                    ]));
                }
                
            } catch (\Exception $e) {
                // Rollback transaction on error
                if ($transaction) {
                    $transaction->rollBack();
                }
                
                // Clean up temporary table if it exists
                $this->dropTable($connection, $tmp_table);
                
                throw $e;
            }
            
        } catch (\Exception $e) {
            \Drupal::messenger()->addError(t('Error processing file: @error', [
                '@error' => $e->getMessage()
            ]));
            \Drupal::logger('your_module')->error('File processing error: @error', [
                '@error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Read file content safely
     */
    private function readFileContent($uri) {
        if (!file_exists($uri)) {
            throw new \Exception('File does not exist.');
        }
        
        $content = file_get_contents($uri);
        if ($content === false) {
            throw new \Exception('Unable to read file content.');
        }
        
        return $content;
    }

    /**
     * Extract SQL content from ZIP file
     */
    private function extractSqlFromZip($uri) {
        if (!class_exists('ZipArchive')) {
            throw new \Exception('ZIP support not available.');
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($uri) !== true) {
            throw new \Exception('Unable to open ZIP file.');
        }
        
        $sql_content = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'sql') {
                $sql_content .= $zip->getFromIndex($i) . "\n";
            }
        }
        $zip->close();
        
        if (empty($sql_content)) {
            throw new \Exception('No SQL files found in ZIP archive.');
        }
        
        return $sql_content;
    }

    /**
     * Execute SQL statements safely
     */
    private function executeSqlStatements($connection, $content, &$errors) {
        $statements = array_filter(array_map('trim', explode(';', $content)));
        
        foreach ($statements as $statement) {
            if (empty($statement)) {
                continue;
            }
            
            // Only allow CREATE TABLE and INSERT statements
            if (!preg_match('/^\s*(CREATE\s+TABLE|INSERT\s+INTO)\s+/i', $statement)) {
                continue;
            }
            
            try {
                $connection->query($statement);
            } catch (\Exception $e) {
                $errors[] = 'Statement error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Check if table exists
     */
    private function tableExists($connection, $table_name) {
        try {
            $query = "SHOW TABLES LIKE :table";
            $result = $connection->query($query, [':table' => $table_name]);
            return $result->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get non-primary, non-auto-increment fields
     */
    private function getNonPrimaryFields($connection, $table) {
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = :db "
            . "AND TABLE_NAME = :table "
            . "AND NOT (COLUMN_KEY = 'PRI' AND EXTRA = 'auto_increment')";
        
        $db_info = Database::getConnectionInfo('external_db');
        $params = [
            ':table' => $table,
            ':db' => $db_info['default']['database']
        ];
        
        $result = $connection->query($query, $params);
        $fields = [];
        
        while ($row = $result->fetchObject()) {
            $fields[] = $row->COLUMN_NAME;
        }
        
        return $fields;
    }

    /**
     * Merge data from temporary table to target table
     */
    private function mergeData($connection, $target_table, $tmp_table, $fields) {
        $field_list = '`' . implode('`, `', $fields) . '`';
        
        $query = "INSERT IGNORE INTO `" . $target_table . "` "
            . "(" . $field_list . ") "
            . "SELECT " . $field_list . " FROM `" . $tmp_table . "`";
        
        $result = $connection->query($query);
        return $connection->query("SELECT ROW_COUNT()")->fetchField();
    }

    /**
     * Drop table safely
     */
    private function dropTable($connection, $table_name) {
        try {
            if ($this->tableExists($connection, $table_name)) {
                $connection->query("DROP TABLE `" . $table_name . "`");
            }
        } catch (\Exception $e) {
            \Drupal::logger('your_module')->warning('Failed to drop temporary table @table: @error', [
                '@table' => $table_name,
                '@error' => $e->getMessage()
            ]);
        }
    }
}
