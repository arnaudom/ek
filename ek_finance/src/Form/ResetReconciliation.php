<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\ResetReconciliation.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\BankData;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to reset and record reconciliation data.
 */
class ResetReconciliation extends FormBase {

    /**
     * The file storage service.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $fileStorage;

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler, EntityStorageInterface $file_storage) {
        $this->moduleHandler = $module_handler;
        $this->fileStorage = $file_storage;
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'), $container->get('entity_type.manager')->getStorage('file')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'resetreconciliation';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal_reco_history', 'h')
                    ->fields('h')
                    ->condition('id', $id)
                    ->execute();
        $h = $query->fetchObject();
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a', ['aname'])
                    ->condition('aid', $h->aid)
                    ->condition('coid', $h->coid)
                    ->execute();
        $a = $query->fetchField();
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c', ['name'])
                    ->condition('id', $h->coid)
                    ->execute();
        $c = $query->fetchField();
        
        $data = unserialize(utf8_decode($h->data));
        $form['id'] = [
                '#type' => 'hidden',
                '#value' => $h->id,
            ];
        $form['data'] = [
                '#type' => 'hidden',
                '#value' => $h->data,
            ];
        $form['uri'] = [
                '#type' => 'hidden',
                '#value' => $h->uri,
            ];
        
        $form['actions'] = [
                '#type' => 'actions',
                '#attributes' => ['class' => ['container-inline']],
            ];
        
        $input = ['@a' => $h->aid . ' ' . $a, '@c' => $c, '@d' => $data[0][5]];
        $form['info'] = array(
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'>" .
                   $this->t('You are about to reset reconciliation for @a of company @c dated @d.', $input) .
                   "</div>",
                );

        $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Confirm'),
            ];
        

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
        
        $data = unserialize(utf8_decode($form_state->getValue('data')));
        $id = $form_state->getValue('id');
        
        for ($i=1; $i <= count($data); $i++) {
            
            // change flag in journal
            $journal_id = $data[$i][7];
            Database::getConnection('external_db', 'external_db')->update('ek_journal')
                            ->condition('id', $journal_id)
                            ->fields(['reconcile' => 0])
                            ->execute();
            
            // remove from bank history        
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'a');
            $query->fields('a', ['id', 'account_ref']);
            $query->leftJoin('ek_bank', 'b', 'a.bid = b.id');
            $query->condition('coid', $data[0][6], '=');
            $query->condition('aid', $data[0][7], '=');
            
            $bank = $query->execute()->fetchObject();
            
            if($bank) {
                $j = Journal::journalEntryDetails($journal_id);
                $year = explode("-", $j['date']);
                if (is_array($j['comment'])) {
                        //remove the hyperlink tag
                        preg_match("'>(.*?)</a>'si", $j['comment']['#markup'], $match);
                        $j['comment'] = $j['reference'] . " - " . $match[1];
                    } else {
                        $j['comment'] = $j['reference'] . " - " . $j['comment'];
                    }
                Database::getConnection('external_db', 'external_db')->delete('ek_bank_transactions')
                        ->condition('account_ref', $bank->account_ref)
                        ->condition('date_transaction' , $j['date'])
                        ->condition('year_transaction' , $year[0])
                        ->condition('type' , $j['type'])
                        ->condition('currency' , $j['currency'])
                        ->condition('amount' , $j['value'])
                        ->condition('description' , $j['comment'])
                        ->execute();
            }
            
            
        }
        
        // attachment
        if($form_state->getValue('uri')) {
            \Drupal::service('file_system')->delete($form_state->getValue('uri'));
        }
        
        // history
        Database::getConnection('external_db', 'external_db')->delete('ek_journal_reco_history')
            ->condition('id' , $form_state->getValue('id'))
            ->execute();
        
        \Drupal::messenger()->addStatus(t('Reconciliation was reset'));
        $form_state->setRedirect('ek_finance.manage.reconciliation_reports');
        
        
        /*
        if ($form_state->get('step') == 2) {
            $reco_lines = array();
            $error = 0;
            $reco_lines[0] = array(
                $form_state->getValue('credits'),
                $form_state->getValue('debits'),
                $form_state->getValue('openbalance'),
                $form_state->getValue('statement'),
                $form_state->getValue('difference'),
                $form_state->getValue('date'),
                $form_state->getValue("coid"),
                $form_state->getValue('account')
            );

            $items = $form_state->getValue('items');
            for ($i = 0; $i < $form_state->getValue('rows'); $i++) {
                if ($items[$i]['select' . $i] <> 0) {
                    $journal_id = $items[$i]['journal_id'];


                    //set the reconciliation flag to 1 in journal entry
                    $update = Database::getConnection('external_db', 'external_db')->update('ek_journal')
                            ->condition('id', $journal_id)
                            ->fields(array('reconcile' => 1))
                            ->execute();

                    if (!$update) {
                        //keep a record of id not properly updated in journal
                        $error = 1;
                    } else {
                        $error = 0;
                    }

                    //set the reconcilition flag to 1 for all journal entries (block edit of entries, i.e. expense)
                    $j = Journal::journalEntryDetails($journal_id);

                    //Fix a bug to retreive exchange value when inter accounts transfer with different currencies
                    if ($form_state->getValue('account_currency') && ($form_state->getValue('account_currency') != $j['currency'])) {
                        $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_journal', 'jr');
                        $query->fields('jr', ['value']);
                        $query->condition('coid', $j['coid'], '=');
                        $query->condition('aid', $j['aid'], '=');
                        $query->condition('date', $j['date'], '=');
                        $query->condition('type', $j['type'], '=');
                        $query->condition('source', $j['source'], '=');
                        $query->condition('reference', $j['reference'], '=');
                        $query->condition('exchange', 1, '=');

                        $exchange = $query->execute()->fetchObject();

                        $j['value'] = $j['value'] + $exchange->value;
                        $j['currency'] = $form_state->getValue('account_currency');
                    }

                    // verify if date of entry is current or past year. If past year reco tag is changed from 1 to current year
                    // this is used for balance calculation with overlaping data when doing reconciliation
                    $current_year = date('Y');
                    if (date('Y', strtotime($j['date'])) < $current_year) {
                        $reco = $current_year;
                    } else {
                        $reco = 1;
                    }

                    Database::getConnection('external_db', 'external_db')->update('ek_journal')
                            //->condition('exchange', 1)
                            ->condition('aid', $j['aid'])
                            ->condition('reference', $j['reference'])
                            ->condition('source', $j['source'])
                            ->condition('date', $j['date'])
                            ->fields(array('reconcile' => $reco))
                            ->execute();

                    // verify if aid is bank account, if yes update bank history
                    $query = "SELECT ba.id,account_ref FROM {ek_bank_accounts} ba INNER JOIN {ek_bank} b ON ba.bid=b.id where aid=:aid and coid=:coid";
                    $a = array(':aid' => $form_state->getValue('account'), ':coid' => $form_state->getValue('coid'));
                    $result = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
                    $year = explode("-", $j['date']);

                    if (is_array($j['comment'])) {
                        //remove the hyperlink tag
                        preg_match("'>(.*?)</a>'si", $j['comment']['#markup'], $match);
                        $j['comment'] = $j['reference'] . " - " . $match[1];
                    } else {
                        $j['comment'] = $j['reference'] . " - " . $j['comment'];
                    }

                    if ($result) {
                        $fields = array(
                            'account_ref' => $result->account_ref,
                            'date_transaction' => $j['date'],
                            'year_transaction' => $year[0],
                            'type' => $j['type'],
                            'currency' => $j['currency'],
                            'amount' => $j['value'],
                            'description' => $j['comment']
                        );

                        Database::getConnection('external_db', 'external_db')->insert('ek_bank_transactions')
                                ->fields($fields)
                                ->execute();
                    }

                    // save reco line for report

                    $reco_lines[$i + 1] = array(
                       0 $form_state->getValue('account'),
                       1 $j['date'],
                       2 $year[0],
                       3 $j['type'],
                       4 $j['currency'],
                       5 $j['value'],
                       6 $j['comment'],
                       7 $journal_id,
                       8 $error,
                    );
                }
            } //for
            //save report data

            if (!$form_state->getValue('upload_doc') == 0) {
                $file = $form_state->getValue('upload_doc');
                $dir = "private://finance/bank/" . $form_state->getValue('coid');
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $filename = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
            } else {
                $filename = "";
            }
            $reco_lines = serialize($reco_lines);
            $error = serialize($error);
            $fields = array(
                'type' => 1,
                'date' => $form_state->getValue('date'),
                'aid' => $form_state->getValue('account'),
                'coid' => $form_state->getValue('coid'),
                'data' => $reco_lines,
                'uri' => $filename,
            );
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_journal_reco_history')
                    ->fields($fields)
                    ->execute();
        } //if
        
         */
    }

}
