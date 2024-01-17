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
        
        $data = unserialize($h->data);
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
        
        $data = unserialize($form_state->getValue('data'));
        $coid = $data[0][6];
        $aid = $data[0][7];
        
        foreach($data as $key => $line) {

            if($key > 0)  {
                if(!null == $line[7]) {
                    // change flag in journal
                    $journal_id = $line[7];
                    Database::getConnection('external_db', 'external_db')->update('ek_journal')
                                    ->condition('id', $journal_id)
                                    ->fields(['reconcile' => 0])
                                    ->execute();
                    
                    // remove from bank history        
                    $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_bank_accounts', 'a');
                    $query->fields('a', ['id', 'account_ref']);
                    $query->leftJoin('ek_bank', 'b', 'a.bid = b.id');
                    $query->condition('coid', $coid, '=');
                    $query->condition('aid', $aid, '=');
                    
                    $bank = $query->execute()->fetchObject();
                    
                    if($bank) {
                        $j = Journal::journalEntryDetails($journal_id);
                        $year = explode("-", $j['date']);
                        if (is_array($j['comment'])) {
                                // remove the hyperlink tag
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
        
        
        
    }

}
