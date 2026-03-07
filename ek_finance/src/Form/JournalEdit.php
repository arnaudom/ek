<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\JournalEdit.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Service\JournalService;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to edit journal entry
 *
 */
class JournalEdit extends FormBase {

    protected $settings;
    protected $rounding;
    protected $baseCurrency;
    protected $journal;
    
    public function __construct(JournalService $journal) {
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
        $this->baseCurrency = $this->settings->get('baseCurrency');
        $this->journal = $journal;
    }
    
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('ek_finance.journal')
        );
    }

    public function getFormId() {
        return 'journal_edit';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $form['param'] = [
            '#type' => 'hidden',
            '#value' => $param,
        ];

        $CurrencyOptions = CurrencyData::listcurrency(1);
        $accountOptions = ['0' => ''];
        $accountOptions += AidList::listaid($param['coid'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], 1);
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company', 'c')
                ->fields('c', ['name'])
                ->condition('id', $param['coid'])
                ->execute();

        $company = $query->fetchField();

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

        if($param['edit'] == false) {
            $form['default'] = [
                '#type' => 'item',
                '#markup' => $this->t('Cloning record'),
                '#states' => [
                    'invisible' => [
                        "input[name='delete']" => ['checked' => true],
                    ],
                ],
            ];
            $form['record_as_new'] = [
                '#type' => 'hidden',
                '#value' => 1
            ];

        } else {
            $form['record_as_new'] = [
                '#type' => 'radios',
                '#title' => $this->t('Record'),
                '#options' => ['1' => $this->t('clone'), '0' => $this->t('edit')],
                '#default_value' => 1,
                '#states' => [
                    'invisible' => [
                        "input[name='delete']" => ['checked' => true],
                    ],
                ],
            ];
        }

        $form["date"] = [
            '#type' => 'date',
            '#size' => 12,
            '#required' => true,
            '#default_value' => $param['date'],
            '#title' => $this->t('record date'),
        ];

        $header = [
            'd-account' => $this->t('Debit account'),
            'debit' => $this->t('Debit'),
            'force_dt_ex' => $this->t('Ex.'),
            'credit' => $this->t('Credit'),
            'force_ct_ex' => $this->t('Ex.'),
            'c-account' => $this->t('Credit account'),
            'comment' => $this->t('Comment'),
        ];

        $form['itemTable'] = [
            '#tree' => true,
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => [],
            '#attributes' => ['id' => 'itemTable'],
            '#empty' => '',
        ];

        $query = "SELECT * FROM {ek_journal} WHERE source=:s AND reference=:r AND type=:t ORDER by id";
        $dataDT = Database::getConnection('external_db', 'external_db')
                ->query($query, [':s' => $param['source'], ':r' => $param['reference'], ':t' => 'debit'])
                ->fetchAll();
        $dataCT = Database::getConnection('external_db', 'external_db')
                ->query($query, [':s' => $param['source'], ':r' => $param['reference'], ':t' => 'credit'])
                ->fetchAll();

        $count = max(count($dataDT), count($dataCT));
        $totalDT = 0;
        $totalCT = 0;

        for ($i = 0; $i < $count; $i++) {
            $n = $i + 1;
            
            $form["dtid$n"] = [
                '#type' => 'hidden',
                '#value' => isset($dataDT[$i]) ? $dataDT[$i]->id : '',
            ];
            
            $form["d-account$n"] = [
                '#type' => 'select',
                '#id' => "d-account$n",
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => isset($dataDT[$i]) ? $dataDT[$i]->aid : 0,
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
            ];

            $debitValue = isset($dataDT[$i]) ? $dataDT[$i]->value : '';
            $totalDT += (float)$debitValue;
            
            $form["debit$n"] = [
                '#type' => 'textfield',
                '#id' => "debit$n",
                '#size' => 12,
                '#maxlength' => 255,
                '#default_value' => $debitValue,
                '#attributes' => [
                    'placeholder' => $this->t('value'),
                    'class' => ['amount'],
                    'ondblclick' => "this.value=''",
                    'onKeyPress' => "return(number_format(this,',','.', event))"
                ],
            ];

            $form["force_dt_ex$n"] = [
                '#type' => 'checkbox',
                '#default_value' => isset($dataDT[$i]) ? $dataDT[$i]->exchange : 0,
                '#attributes' => ['title' => $this->t('Exchange record')],
            ];

            $creditValue = isset($dataCT[$i]) ? $dataCT[$i]->value : '';
            $totalCT += (float)$creditValue;
            
            $form["credit$n"] = [
                '#type' => 'textfield',
                '#id' => "credit$n",
                '#size' => 12,
                '#maxlength' => 255,
                '#default_value' => $creditValue,
                '#attributes' => [
                    'placeholder' => $this->t('value'),
                    'class' => ['amount'],
                    'ondblclick' => "this.value=''",
                    'onKeyPress' => "return(number_format(this,',','.', event))"
                ],
            ];

            $form["force_ct_ex$n"] = [
                '#type' => 'checkbox',
                '#default_value' => isset($dataCT[$i]) ? $dataCT[$i]->exchange : 0,
                '#attributes' => ['title' => $this->t('Exchange record')],
            ];

            $form["ctid$n"] = [
                '#type' => 'hidden',
                '#value' => isset($dataCT[$i]) ? $dataCT[$i]->id : '',
            ];

            $form["c-account$n"] = [
                '#type' => 'select',
                '#id' => "c-account$n",
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => isset($dataCT[$i]) ? $dataCT[$i]->aid : 0,
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
            ];

            $form["comment$n"] = [
                '#type' => 'textfield',
                '#id' => "comment$n",
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => isset($dataDT[$i]) ? $dataDT[$i]->comment : '',
                '#attributes' => ['placeholder' => $this->t('comment')],
            ];

            $form['itemTable'][$n] = [
                'd-account' => &$form["d-account$n"],
                'debit' => &$form["debit$n"],
                'force_dt_ex' => &$form["force_dt_ex$n"],
                'credit' => &$form["credit$n"],
                'force_ct_ex' => &$form["force_ct_ex$n"],
                'c-account' => &$form["c-account$n"],
                'comment' => &$form["comment$n"],
            ];

            $form['itemTable']['#rows'][$n] = [
                'data' => [
                    ['data' => &$form["d-account$n"]],
                    ['data' => &$form["debit$n"]],
                    ['data' => &$form["force_dt_ex$n"]],
                    ['data' => &$form["credit$n"]],
                    ['data' => &$form["force_ct_ex$n"]],
                    ['data' => &$form["c-account$n"]],
                    ['data' => &$form["comment$n"]],
                ],
                'id' => [$n],
            ];

            unset($form["d-account$n"]);
            unset($form["debit$n"]);
            unset($form["force_dt_ex$n"]);
            unset($form["credit$n"]);
            unset($form["force_ct_ex$n"]);
            unset($form["c-account$n"]);
            unset($form["comment$n"]);
        }

        // Footer
        $form["footer1"] = ['#type' => 'item'];
        $form["footer2"] = [
            '#type' => 'textfield',
            '#id' => 'totald',
            '#size' => 12,
            '#maxlength' => 255,
            '#default_value' => number_format($totalDT, 2),
            '#attributes' => ['placeholder' => $this->t('total'), 'class' => ['amount'], 'readonly' => 'readonly'],
        ];
        $form["footer3"] = ['#type' => 'item']; // Empty column for force_dt_ex
        $form["footer4"] = [
            '#type' => 'textfield',
            '#id' => 'totalc',
            '#size' => 12,
            '#maxlength' => 255,
            '#default_value' => number_format($totalCT, 2),
            '#attributes' => ['placeholder' => $this->t('total'), 'class' => ['amount'], 'readonly' => 'readonly'],
        ];
        $form["footer5"] = ['#type' => 'item']; // Empty column for force_ct_ex
        $form["footer6"] = ['#type' => 'item'];
        $form["footer7"] = ['#type' => 'item'];

        $form['itemTable']['foot'] = [
            'd-account' => &$form['footer1'],
            'debit' => &$form['footer2'],
            'force_dt_ex' => &$form['footer3'],
            'credit' => &$form['footer4'],
            'force_ct_ex' => &$form['footer5'],
            'c-account' => &$form['footer6'],
            'comment' => &$form['footer7'],
        ];

        $form['itemTable']['#rows']['foot'] = [
            'data' => [
                ['data' => &$form['footer1']],
                ['data' => &$form['footer2']],
                ['data' => &$form['footer3']],
                ['data' => &$form['footer4']],
                ['data' => &$form['footer5']],
                ['data' => &$form['footer6']],
                ['data' => &$form['footer7']],
            ],
            'id' => ['foot'],
        ];

        unset($form['footer1']);
        unset($form['footer2']);
        unset($form['footer3']);
        unset($form['footer4']);
        unset($form['footer5']);
        unset($form['footer6']);
        unset($form['footer7']);

        $form['rows'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'rows'],
            '#value' => $count,
        ];

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];

        $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('delete') == 0) {
            $totalCT = 0;
            $totalDT = 0;
            $rows = $form_state->getValue('itemTable');
            
            if (!empty($rows)) {
                foreach ($rows as $key => $row) {
                    if ($key !== 'foot') {
                        $debit = preg_replace('/[^\d.-]/', '', $row['debit']);
                        $credit = preg_replace('/[^\d.-]/', '', $row['credit']);
                        
                        if ($debit && !is_numeric($debit)) {
                            $form_state->setErrorByName("itemTable][$key][debit", $this->t('Debit value must be numeric'));
                        } elseif ($debit) {
                            $totalDT += (double)$debit;
                        }
                        
                        if ($credit && !is_numeric($credit)) {
                            $form_state->setErrorByName("itemTable][$key][credit", $this->t('Credit value must be numeric'));
                        } elseif ($credit) {
                            $totalCT += (double)$credit;
                        }
                        
                        if ($debit > 0 && $row['d-account'] == 0) {
                            $form_state->setErrorByName("itemTable][$key][d-account", $this->t('Debit account required'));
                        }
                        
                        if ($credit > 0 && $row['c-account'] == 0) {
                            $form_state->setErrorByName("itemTable][$key][c-account", $this->t('Credit account required'));
                        }
                    }
                }
            }

            if ($totalDT == 0 && $totalCT == 0) {
                $form_state->setErrorByName("itemTable][foot][debit", $this->t('Total cannot be zero'));
                $form_state->setErrorByName("itemTable][foot][credit");
            }
            
            if (round($totalDT, $this->rounding) !== round($totalCT, $this->rounding)) {
                $form_state->setErrorByName("itemTable][foot][debit", $this->t('Entry is not balanced'));
                $form_state->setErrorByName("itemTable][foot][credit");
            }
        }
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $param = $form_state->getValue('param');
        $url = Url::fromRoute('ek_finance.extract.general_journal', [], [])->toString();
        
        if ($form_state->getValue('delete') == 1) {
            if ($param['source'] == 'general cash') {
                Database::getConnection('external_db', 'external_db')
                    ->update('ek_cash')
                    ->fields([
                        'coid' => 'x' . $param['coid'],
                        'comment' => 'journal deleted'
                    ])
                    ->condition('id', $param['reference'])
                    ->execute();
            }

            
            $journalId = $this->journal->delete($param['source'], $param['reference'], $param['coid']);
            $this->journal->resetCount($param['coid'], $journalId[1]);

            \Drupal::messenger()->addStatus(t('Data deleted. Go to <a href="@url">journal</a>', ['@url' => $url]));
            return;
        }

        $rows = $form_state->getValue('itemTable');
        
        if ($form_state->getValue('record_as_new') == 1) {
            // Clone as new entry
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j')
                ->fields('j', ['reference'])
                ->condition('source', 'general')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(1)
                ->orderBy('id', 'DESC')
                ->execute();
                
            $ref = $query->fetchField() + 1;
            $rec = [];
            
            foreach ($rows as $key => $row) {
                if ($key !== 'foot') { 
                    $debit = preg_replace('/[^\d.-]/', '', $row['debit']);
                    $credit = preg_replace('/[^\d.-]/', '', $row['credit']);
                    
                    if ($debit) { 
                        $a = [
                            'source' => 'general',
                            'coid' => $param['coid'],
                            'aid' => $row["d-account"],
                            'type' => 'debit',
                            'reference' => $ref,
                            'date' => $form_state->getValue('date'),
                            'value' => $debit,
                            'currency' => $param['currency'],
                            'comment' => Xss::filter($row["comment"]),
                            'fxRate' => isset($param['fxRate']) ? $param['fxRate'] : null,
                            'exchange' => $row['force_dt_ex'],
                        ];
                        $rec[$key] = $this->journal->record($a);
                    }
                    
                    if ($credit) {
                        $a = [
                            'source' => 'general',
                            'coid' => $param['coid'],
                            'aid' => $row["c-account"],
                            'type' => 'credit',
                            'reference' => $ref,
                            'date' => $form_state->getValue('date'),
                            'value' => $credit,
                            'currency' => $param['currency'],
                            'comment' => Xss::filter($row["comment"]),
                            'fxRate' => isset($param['fxRate']) ? $param['fxRate'] : null,
                            'exchange' => $row['force_ct_ex'],
                        ];
                        $this->journal->record($a);
                    }
                }
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            $editUrl = Url::fromRoute('ek_finance.manage.journal_edit', ['id' => $rec[1]], [])->toString();
            \Drupal::messenger()->addStatus(t('New entry cloned. <a href="@url">Edit</a>', ['@url' => $editUrl]));
        } else {
            // Update existing entries
            foreach ($rows as $key => $row) {
                if ($key !== 'foot') {
                    if ($row['debit']) { 
                        Database::getConnection('external_db', 'external_db')
                            ->update('ek_journal')
                            ->fields([
                                'date' => $form_state->getValue('date'),
                                'value' => preg_replace('/[^\d.-]/', '', $row['debit']),
                                'aid' => $row['d-account'],
                                'comment' => Xss::filter($row['comment']),
                                'exchange' => $row['force_dt_ex'],
                            ])
                            ->condition('id', $form_state->getValue("dtid$key"))
                            ->execute();
                    }
                    
                    if ($row['credit']) {
                        Database::getConnection('external_db', 'external_db')
                            ->update('ek_journal')
                            ->fields([
                                'date' => $form_state->getValue('date'),
                                'value' => preg_replace('/[^\d.-]/', '', $row['credit']),
                                'aid' => $row['c-account'],
                                'comment' => Xss::filter($row['comment']),
                                'exchange' => $row['force_ct_ex'],
                            ])
                            ->condition('id', $form_state->getValue("ctid$key"))
                            ->execute();
                    }
                }
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            \Drupal::messenger()->addStatus(t('Data edited. Go to <a href="@url">journal</a>', ['@url' => $url]));
        }
    }

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
}