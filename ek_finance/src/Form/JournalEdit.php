<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\JournalEdit.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to edit journal entry
 *
 */
class JournalEdit extends FormBase {

    public function __construct() {
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
        $this->baseCurrency = $this->settings->get('baseCurrency');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'journal_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $form['param'] = array(
            '#type' => 'hidden',
            '#value' => $param,
        );

        // $settings = new FinanceSettings();
        // $baseCurrency = $settings->get('baseCurrency');
        $CurrencyOptions = CurrencyData::listcurrency(1);
        $accountOptions = ['0' => ''];
        $accountOptions += AidList::listaid($param['coid'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], 1);
        $query = "SELECT name from {ek_company} WHERE id=:id";
        $company = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $param['coid']])
                ->fetchField();

        $url = Url::fromRoute('ek_finance.extract.general_journal', [], [])->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t("<a href='@url'>Back</a>", ['@url' => $url]),
        ];

        $form['company'] = [
            '#type' => 'item',
            '#markup' => $company,
        ];
        $form['currency'] = [
            '#type' => 'item',
            '#markup' => $param['currency'],
        ];

        $form["delete"] = [
            '#type' => 'checkbox',
            '#title' => $this->t('delete')
        ];

        $form["date"] = [
            '#type' => 'date',
            '#size' => 12,
            '#required' => true,
            '#default_value' => $param['date'],
            '#title' => $this->t('record date'),
            '#prefix' => "",
            '#suffix' => '',
        ];

        $headerline = "<div class='table'><div class='row'><div class='cell cellborder'>"
                . $this->t("Debit account") . "</div><div class='cell cellborder'>"
                . $this->t("Debit") . "</div><div class='cell cellborder'>"
                . $this->t("Credit") . "</div><div class='cell cellborder'>"
                . $this->t("Credit account") . "</div><div class='cell cellborder'>"
                . $this->t("Comment") . "</div>";

        $totalcredit = 0;
        $totalcredit_exchange = 0;
        $totaldebit = 0;
        $totaldebit_exchange = 0;

        $form['items']["headerline"] = [
            '#type' => 'item',
            '#markup' => $headerline,
        ];

        $query = "SELECT * FROM {ek_journal} WHERE source=:s AND reference=:r AND type=:t ORDER by id";
        $dataDT = Database::getConnection('external_db', 'external_db')
                ->query($query, [':s' => $param['source'], ':r' => $param['reference'], ':t' => 'debit'])
                ->fetchAll();
        $dataCT = Database::getConnection('external_db', 'external_db')
                ->query($query, [':s' => $param['source'], ':r' => $param['reference'], ':t' => 'credit'])
                ->fetchAll();

        if (count($dataDT) >= count($dataCT)) {
            $count = count($dataDT);
        } elseif (count($dataCT) >= count($dataDT)) {
            $count = count($dataCT);
        }
        // loop  data

        $i = 0;

        for ($n = 0; $n < $count; $n++) {
            $i++;
            if ($dataDT[$n]->exchange == 0) {
                $totaldebit += $dataDT[$n]->value;
                $tagDT = "";
            } else {
                $totaldebit_exchange += $dataDT[$n]->value;
                $tagDT = "*e";
            }
            if ($dataCT[$n]->exchange == 0) {
                $totalcredit += $dataCT[$n]->value;
                $tagCT = "";
            } else {
                $totalcredit_exchange += $dataCT[$n]->value;
                $tagCT = "*e";
            }

            $form['items']["dtid$i"] = [
                '#type' => 'hidden',
                '#value' => $dataDT[$n]->id,
            ];
            $form['items']["d-account$i"] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => $dataDT[$n]->aid,
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            ];

            $form['items']["debit$i"] = [
                '#type' => 'textfield',
                '#id' => "debit$i",
                '#size' => 12,
                '#maxlength' => 255,
                '#title' => $tagDT,
                '#title_display' => 'after',
                '#default_value' => $dataDT[$n]->value,
                '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'ondblclick' => "this.value=''", 'onKeyPress' => "return(number_format(this,',','.', event))"],
                '#prefix' => "<div class='cell container-inline'>",
            ];

            $form['items']["force_dt_ex$i"] = [
                '#type' => 'checkbox',
                '#default_value' => ($dataDT[$n]->exchange == 0) ? 0 : 1,
                '#attributes' => ['title' => $this->t('Force exchange record')],
                '#suffix' => '</div>',
            ];/**/

            $form['items']["ctid$i"] = [
                '#type' => 'hidden',
                '#value' => $dataCT[$n]->id,
            ];
            
            $form['items']["credit$i"] = [
                '#type' => 'textfield',
                '#id' => "credit$i",
                '#size' => 12,
                '#maxlength' => 255,
                '#title' => $tagCT,
                '#title_display' => 'after',
                '#default_value' => $dataCT[$n]->value,
                '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'ondblclick' => "this.value=''", 'onKeyPress' => "return(number_format(this,',','.', event))"],
                '#prefix' => "<div class='cell container-inline'>",
            ];

            $form['items']["force_ct_ex$i"] = [
                '#type' => 'checkbox',
                '#default_value' => ($dataCT[$n]->exchange == 0) ? 0 : 1,
                '#attributes' => ['title' => $this->t('Force exchange record')],
                '#suffix' => '</div>',
            ];/**/
            
            $form['items']["c-account$i"] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => $dataCT[$n]->aid,
                '#description' => '',
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            ];



            $form['items']["comment$i"] = [
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => $dataDT[$n]->comment,
                '#attributes' => ['placeholder' => $this->t('comment'),],
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            ];
        }


        if ($totalcredit == $totaldebit && $totalcredit_exchange == $totaldebit_exchange) {
            $style = '';
        } else {
            $style = 'delete';
        }

        // footer
        $form['items']["footer1"] = [
            '#type' => 'item',
            '#prefix' => "<div class='row'><div class='cell cellborder'>" . $param['currency'],
            '#suffix' => '</div>',
        ];
        $form['items']["footer2"] = [
            '#type' => 'item',
            '#prefix' => "<div class='cell cellborder $style' id='totald'>" . number_format($totaldebit, 2) . "",
            '#suffix' => '</div>',
        ];
        $form['items']["footer3"] = [
            '#type' => 'item',
            '#prefix' => "<div class='cell cellborder $style' id='totalc'>" . number_format($totalcredit, 2) . "",
            '#suffix' => '</div>',
        ];
        $form['items']["footer4"] = [
            '#type' => 'item',
            '#prefix' => "<div class='cell cellborder'>",
            '#suffix' => '</div>',
        ];
        $form['items']["footer5"] = [
            '#type' => 'item',
            '#prefix' => "<div class='cell cellborder'>",
            '#suffix' => '</div></div>',
        ];

        if ($totaldebit_exchange != 0 || $totalcredit_exchange != 0) {
            $form['items']["footer6"] = [
                '#type' => 'item',
                '#prefix' => "<div class='row'><div class='cell cellborder'>" . $this->baseCurrency,
                '#suffix' => '</div>',
            ];
            $form['items']["footer7"] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder $style' id='totald'>" . number_format($totaldebit + $totaldebit_exchange, 2) . "",
                '#suffix' => '</div>',
            ];
            $form['items']["footer8"] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder $style' id='totalc'>" . number_format($totalcredit + $totalcredit_exchange, 2) . "",
                '#suffix' => '</div>',
            ];
            $form['items']["footer9"] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div>',
            ];
            $form['items']["footer10"] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div></div></div>',
            ];
        } else {
            $form['items']["footer6"] = [
                '#type' => 'item',
                '#prefix' => "</div>",
            ];
        }

        $form['rows'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'rows'],
            '#value' => $n,
        ];
        
        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#suffix' => ''
        ];


        $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';


        return $form;
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        $currency = $form_state->getValue('currency');
        $fx = CurrencyData::rate($currency);

        if ($fx <> 1) {
            $form['fx_rate']['#value'] = $fx;
            $form['fx_rate']['#required'] = true;
            $form['credit']['fx_rate']['#description'] = '';
        } else {
            $form['fx_rate']['#required'] = false;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('delete') == 0) {
            $totalcredit = 0;
            $totaldebit = 0;

            for ($i = 1; $i <= $form_state->getValue('rows'); $i++) {
                $debit = str_replace(',', '', $form_state->getValue("debit$i"));
                if ($debit == '') {
                    $debit = 0;
                }
                $credit = str_replace(',', '', $form_state->getValue("credit$i"));
                if ($credit == '') {
                    $credit = 0;
                }

                if (!is_numeric($debit)) {
                    $form_state->setErrorByName("debit$i", $this->t('input value error'));
                }
                if (!is_numeric($credit)) {
                    $form_state->setErrorByName("credit$i", $this->t('input value error'));
                }

                if ($debit > 0 && $form_state->getValue("d-account$i") == 0) {
                    $form_state->setErrorByName("d-account$i", $this->t('no account selected'));
                }

                if ($credit > 0 && $form_state->getValue("c-account$i") == 0) {
                    $form_state->setErrorByName("c-account$i", $this->t('no account selected'));
                }


                $totalcredit += (double)$credit;
                $totaldebit += (double)$debit;
            }

            if (round($totalCT,$this->rounding) <> round($totalDT,$this->rounding)) {
                $form_state->setErrorByName('items][footer2', $this->t('entry is not balanced'));
                $form_state->setErrorByName('items][footer3');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $url = Url::fromRoute('ek_finance.extract.general_journal', array(), array())->toString();
        if ($form_state->getValue('delete') == 1) {
            $p = $form_state->getValue("param");

            //if source is connected to cash, remove cash entry first
            if ($p['source'] == 'general cash') {
                $fields = [
                    'coid' => 'x' . $p['coid'],
                    'comment' => 'journal deleted'
                ];

                Database::getConnection('external_db', 'external_db')
                        ->update('ek_cash')
                        ->fields($fields)
                        ->condition('id', $p['reference'])
                        ->execute();
            }


            $journal = new Journal();
            $journalId = $journal->delete($p['source'], $p['reference'], $p['coid']);
            //count field sequence must be restored
            $journal->resetCount($p['coid'], $journalId[1]);


            \Drupal::messenger()->addStatus(t('Data deleted. Go to <a href="@url">journal</a>', ['@url' => $url]));
        } else {
            for ($i = 1; $i <= $form_state->getValue('rows'); $i++) {
                $debit = str_replace(',', '', $form_state->getValue("debit$i"));
                $credit = str_replace(',', '', $form_state->getValue("credit$i"));
                $fields1 = [
                    'date' => $form_state->getValue("date"),
                    'value' => $debit,
                    'aid' => $form_state->getValue("d-account$i"),
                    'exchange' => $form_state->getValue("force_dt_ex$i"),
                ];
                $fields2 = [
                    'date' => $form_state->getValue("date"),
                    'value' => $credit,
                    'aid' => $form_state->getValue("c-account$i"),
                    'exchange' => $form_state->getValue("force_ct_ex$i"),
                ];

                Database::getConnection('external_db', 'external_db')
                        ->update('ek_journal')
                        ->fields($fields1)
                        ->condition('id', $form_state->getValue("dtid$i"))
                        ->execute();
                Database::getConnection('external_db', 'external_db')
                        ->update('ek_journal')
                        ->fields($fields2)
                        ->condition('id', $form_state->getValue("ctid$i"))
                        ->execute();
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            \Drupal::messenger()->addStatus(t('Data edited. Go to <a href="@url">journal</a>', ['@url' => $url]));
        }
    }

}
