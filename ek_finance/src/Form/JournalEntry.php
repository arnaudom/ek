<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\JournalEntry.
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
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to create a manual journal entry
 */
class JournalEntry extends FormBase {

    public function __construct() {
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
    }
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'journal_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if (!$id == null) {
            //pull edit data
        }

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => $form_state->getValue('coid') ? true : false,
            '#required' => true,
            '#prefix' => "<div class='container-inline'>",
        ];

        if ($form_state->getValue('coid') == null) {
            $form['next'] = [
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#states' => [
                    // Hide data fieldset when class is empty.
                    'invisible' => [
                        "select[name='coid']" => ['value' => ''],
                    ],
                ],
                '#suffix' => '</div> ',
            ];
        } else {
            $form['item'] = [
                '#type' => 'item',
                '#markup' => '</div><br/> ',
            ];
        }

        if ($form_state->getValue('coid')) {
        //if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);

            if ($form_state->get('num_items') == '') {
                $form_state->set('num_items', 1);
            }

            $CurrencyOptions = CurrencyData::listcurrency(1);
            $accountOptions = ['0' => ''];
            $accountOptions += AidList::listaid($form_state->getValue('coid'), [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], 1);


            $form['currency'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CurrencyOptions,
                '#required' => true,
                '#default_value' => ($form_state->getValue('currency')) ? $form_state->getValue('currency') : null,
                '#title' => $this->t('Currency'),
                '#ajax' => [
                    'callback' => [$this, 'fx_rate'],
                    'wrapper' => 'credit',
                ],
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['fx_rate'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 15,
                '#default_value' => ($form_state->getValue('fx_rate')) ? $form_state->getValue('fx_rate') : null,
                '#required' => false,
                '#title' => $this->t('Exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='credit'>",
                '#suffix' => '</div></div>',
            ];

            $form["date"] = [
                '#type' => 'date',
                '#size' => 12,
                '#required' => true,
                '#default_value' => ($form_state->getValue("date")) ? $form_state->getValue("date") : date('Y-m-d'),
                '#title' => $this->t('Record date'),
                '#prefix' => "",
                '#suffix' => '',
            ];

            $form['rows'] = [
                '#type' => 'hidden',
                '#attributes' => ['id' => 'rows'],
                '#value' => $form_state->get('num_items'),
            ];

            /*$form['items'] = [
                '#type' => 'details',
                '#title' => $this->t('Items'),
                '#open' => true,
            ];*/

            $header = [
                'account' => [
                    'data' => $this->t('Debit account'),
                ],
                'description' => [
                    'data' => $this->t('Debit'),
                ],
                'amount' => [
                    'data' => $this->t('Credit'),
                ],
                'receipt' => [
                    'data' => $this->t('Credit account'),
                ],
                'delete' => [
                    'data' => $this->t('Comment'),
                ]
            ];

            $form['itemTable'] = [
                '#tree' => true,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => [],
                '#attributes' => ['id' => 'itemTable'],
                '#empty' => '',
            ];

            // build 1st row
            $form["d-account"] = [
                '#type' => 'select',
                '#id' => 'd-account1',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => isset($form_state->getValue('itemTable')[1]['d-account']) ? $form_state->getValue('itemTable')[1]['d-account'] : null,
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
            ];

            $form['debit'] = [
                '#type' => 'textfield',
                '#id' => 'debit1',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' =>  isset($form_state->getValue('itemTable')[1]['debit']) ? $form_state->getValue('itemTable')[1]['debit'] : null,
                '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'ondblclick' => "this.value=''", 'onKeyPress' => "return(number_format(this,',','.', event))"],
            ];

            $form['credit'] = [
                '#type' => 'textfield',
                '#id' => 'credit1',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => isset($form_state->getValue('itemTable')[1]['credit']) ? $form_state->getValue('itemTable')[1]['credit'] : null,
                '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'ondblclick' => "this.value=''", 'onKeyPress' => "return(number_format(this,',','.', event))"],
            ];

            $form["c-account"] = [
                '#type' => 'select',
                '#id' => 'c-account1',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => isset($form_state->getValue('itemTable')[1]['c-account']) ? $form_state->getValue('itemTable')[1]['c-account'] : null,
                '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
            ];

            $form["comment"] = [
                '#type' => 'textfield',
                '#id' => 'comment1',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => isset($form_state->getValue('itemTable')[1]['comment']) ? $form_state->getValue('itemTable')[1]['comment'] : null,
                '#attributes' => ['placeholder' => $this->t('comment'),],
            ];

            $form['itemTable'][1] = [
                'd-account' => &$form['d-account'],
                'debit' => &$form['debit'],
                'credit' => &$form['credit'],
                'c-account' => &$form['c-account'],
                'comment' => &$form['comment']
            ];

            $form['itemTable']['#rows'][1] = [
                'data' => [
                    ['data' => &$form['d-account']],
                    ['data' => &$form['debit']],
                    ['data' => &$form['credit']],
                    ['data' => &$form['c-account']],
                    ['data' => &$form['comment']],
                ],
                'id' => [1],
                'class' => '',
            ];

            unset($form['d-account']);
            unset($form['debit']);
            unset($form['credit']);
            unset($form['c-account']);
            unset($form['comment']);

         // added rows
            if (isset($n)) {
                // reset the new rows items in edit mode
                $max = $form_state->get('num_items') + $n;
                $form_state->set('num_items', $max);
            } else {
                // new entry
                $max = $form_state->get('num_items');
                $n = 2;
            }
            
            for ($i = $n; $i <= $max; $i++) {
                $form["d-account"] = [
                    '#type' => 'select',
                    '#id' => "d-account$i",
                    '#size' => 1,
                    '#options' => $accountOptions,
                    '#default_value' => ($form_state->getValue("d-account$i")) ? $form_state->getValue("d-account$i") : null,
                    '#attributes' => ['style' =>['width:150px;white-space:nowrap']],
                ];

                $form["debit"] = [
                    '#type' => 'textfield',
                    '#id' => "debit$i",
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->getValue("debit$i")) ? $form_state->getValue("debit$i") : null,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'onKeyPress' => "return(number_format(this,',','.', event))"],
                ];

                $form["credit"] = [
                    '#type' => 'textfield',
                    '#id' => "credit$i",
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->getValue("credit$i")) ? $form_state->getValue("credit$i") : null,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'onKeyPress' => "return(number_format(this,',','.', event))"],
                ];

                $form["c-account"] = [
                    '#type' => 'select',
                    '#id' => "c-account$i",
                    '#size' => 1,
                    '#options' => $accountOptions,
                    '#default_value' => ($form_state->getValue("c-account$i")) ? $form_state->getValue("c-account$i") : null,
                    '#attributes' => ['style' => ['width:150px;white-space:nowrap']],
                ];

                $form["comment"] = [
                    '#type' => 'textfield',
                    '#id' => "comment$i",
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => ($form_state->getValue("comment$i")) ? $form_state->getValue("comment$i") : null,
                    '#attributes' => ['placeholder' => $this->t('comment'),],
                ];

                $form['itemTable'][$i] = [
                    'd-account' => &$form['d-account'],
                    'debit' => &$form['debit'],
                    'credit' => &$form['credit'],
                    'c-account' => &$form['c-account'],
                    'comment' => &$form['comment']
                ];
    
                $form['itemTable']['#rows'][$i] = [
                    'data' => [
                        ['data' => &$form['d-account']],
                        ['data' => &$form['debit']],
                        ['data' => &$form['credit']],
                        ['data' => &$form['c-account']],
                        ['data' => &$form['comment']],
                    ],
                    'id' => [$i],
                    'class' => '',
                ];
    
                unset($form['d-account']);
                unset($form['debit']);
                unset($form['credit']);
                unset($form['c-account']);
                unset($form['comment']);

            }

            // footer
            $form["footer1"] = [
                '#type' => 'item',
            ];

            $form['footer2'] = [
                '#type' => 'textfield',
                '#id' => 'totald',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => ($form_state->get('totalDT')) ? number_format($form_state->get('totalDT'), 2) : 0,
                '#attributes' => ['placeholder' => $this->t('total'),'class' => ['amount'], 'readonly' => 'readonly'],
            ];

            $form['footer3'] = [
                '#type' => 'textfield',
                '#id' => 'totalc',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => ($form_state->get('totalCT')) ? number_format($form_state->get('totalCT'), 2) : 0,
                '#attributes' => ['placeholder' => $this->t('total'),'class' => ['amount'], 'readonly' => 'readonly'],
            ];

            $form["footer4"] = [
                '#type' => 'item',
            ];

            $form["footer5"] = [
                '#type' => 'item',
            ];

            $form['itemTable']['foot'] = [
                'd-account' => &$form['footer1'],
                'debit' => &$form['footer2'],
                'credit' => &$form['footer3'],
                'c-account' => &$form['footer4'],
                'comment' => &$form['footer5']
            ];

            $form['itemTable']['#rows']['foot'] = [
                'data' => [
                    ['data' => &$form['footer1']],
                    ['data' => &$form['footer2']],
                    ['data' => &$form['footer3']],
                    ['data' => &$form['footer4']],
                    ['data' => &$form['footer5']],
                ],
                'id' => ['foot'],
                'class' => '',
            ];

            unset($form['footer1']);
            unset($form['footer2']);
            unset($form['footer3']);
            unset($form['footer4']);
            unset($form['footer5']);

            if (!null == $form_state->getValue('confirm_box')) {
                $form['confirm'] = [
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#description' => $this->t('You are transferring value between accounts that use different currencies. Please confirm.'),
                    '#prefix' => '<div class="messages messages--warning">',
                    '#suffix' => '</div>'
                ];
            } else {
                $form['confirm'] = ['#markup' => ''];
            }

            $form['actions'] = [
                '#type' => 'actions',
                '#attributes' => ['class' => ['container-inline']],
            ];

            $form['actions']['add'] = [
                '#type' => 'submit',
                '#value' => $this->t('Add line'),
                '#submit' => [[$this, 'addForm']],
                '#prefix' => "",
                '#suffix' => '',
            ];

            if ($form_state->get('num_items') > 1) {
                $form['actions']['remove'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('remove last line'),
                    '#submit' => [[$this, 'removeForm']],
                ];
            }

            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            ];


            $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';
        }
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
     * Callback to add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('num_items') == '') {
            $form_state->set('num_items', 2);
        } else {
            $i = $form_state->get('num_items') + 1;
            $form_state->set('num_items', $i);
        }

        $form_state->setRebuild();
    }

    /**
     * Callback to remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) { 
        if ($form_state->get('num_items') > 1) {
            $i = $form_state->get('num_items') - 1;
            $form_state->set('num_items', $i);
        }
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $form_state->setValue('coid', $form_state->getValue('coid'));
            $form_state->setRebuild();
        }

        if ($form_state->get('step') == 2 && $form_state->getTriggeringElement()['#id'] != 'edit-remove' && $form_state->getTriggeringElement()['#id'] != 'edit-add') {
            
            if ($form_state->getValue('fx_rate') == '') {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate must be indicated'));
            }
            if (!is_numeric($form_state->getValue('fx_rate'))) {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate input is not correct'));
            }

            $rebuild = 0;
            $totalCT = 0;
            $totalDT = 0;
            $accounts = [];
            $rows = $form_state->getValue('itemTable');
            if (!empty($rows)) {
                foreach ($rows as $key => $row) {
                    if($key != 'foot') {
                        $debit = preg_replace('/[^0-9.]/', '',  $row['debit']);
                        $credit = preg_replace('/[^0-9.]/', '',  $row['credit']);
                        if($row['d-account'] == 0 && $debit > 0) {
                            $form_state->setErrorByName("itemTable][$key][d-account", $this->t('debit account @n not selected', ['@n' => $key]));
                            $rebuild =  true;  
                        }
                        if($row['c-account'] == 0 && $credit > 0) {
                            $form_state->setErrorByName("itemTable][$key][c-account", $this->t('credit account @n not selected', ['@n' => $key]));  
                            $rebuild =  true; 
                        }
                        if(!null == $debit && !is_numeric($debit)) {
                            $form_state->setErrorByName("itemTable][$key][debit", $this->t('input value error'));
                            $rebuild =  true;                              
                        } elseif(is_numeric($debit)) {
                            $totalDT += (double)round($debit,$this->rounding);
                        }
                        if(!null == $credit && !is_numeric($credit)) {
                            $form_state->setErrorByName("itemTable][$key][credit", $this->t('input value error'));
                            $rebuild =  true;                              
                        } elseif(is_numeric($credit)) {
                            $totalCT += (double)round($credit,$this->rounding);
                        }

                        array_push($accounts, $row["d-account"]);
                        array_push($accounts, $row["c-account"]);
                    }
                }
            }

            if($totalDT == 0  && $totalCT == 0) {
                $form_state->setErrorByName("itemTable][foot][debit", $this->t('total is null')); 
                $form_state->setErrorByName("itemTable][foot][credit"); 
                $rebuild =  true;
            }
            if($totalDT <> $totalCT) {
                $form_state->setErrorByName("itemTable][foot][debit", $this->t('total not balanced')); 
                $form_state->setErrorByName("itemTable][foot][credit"); 
                $rebuild =  true;
            }


            // filter accounts transfer with different currencies
            // @TODO : add verification with accounts linked to bank
            $companysettings = new CompanySettings($form_state->getValue('coid'));
            $cash1 = $companysettings->get('cash_account', $form_state->getValue('currency'));
            $cash2 = $companysettings->get('cash2_account', $form_state->getValue('currency'));

            if ((in_array($cash1, $accounts) || in_array($cash2, $accounts)) && $form_state->getValue('confirm') == 0) {
                $form_state->setValue('confirm_box', 1);
                $rebuild =  true;
            }

        }
        if(isset($rebuild) && $rebuild == true) {
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) { 
        if ($form_state->get('step') == 2) {
            $journal = new Journal();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j')
                    ->fields('j', ['reference'])
                    ->condition('source','general')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(1)
                    ->orderBy('id', 'DESC')
                    ->execute();

            $ref = $query->fetchField();
            $ref++;
            $rows = $form_state->getValue('itemTable');
            
                foreach ($rows as $key => $row) { 
                    if($key != 'foot') {
                        $debit = preg_replace('/[^0-9.]/', '',  $row['debit']);
                        $credit = preg_replace('/[^0-9.]/', '',  $row['credit']);
                        if ($debit <> '') {
                            $a = [
                                'source' => "general",
                                'coid' => $form_state->getValue('coid'),
                                'aid' => $row["d-account"],
                                'type' => 'debit',
                                'reference' => $ref,
                                'date' => $form_state->getValue('date'),
                                'value' => $debit,
                                'currency' => $form_state->getValue('currency'),
                                'comment' => Xss::filter($row["comment"]),
                                'fxRate' => $form_state->getValue('fx_rate')
                            ];
                            $rec[$key] = $journal->record($a);
                        }
                        if ($credit <> '') {
                            $a = [
                                'source' => "general",
                                'coid' => $form_state->getValue('coid'),
                                'aid' => $row["c-account"],
                                'type' => 'credit',
                                'reference' => $ref,
                                'date' => $form_state->getValue('date'),
                                'value' => $credit,
                                'currency' => $form_state->getValue('currency'),
                                'comment' => Xss::filter($row["comment"]),
                                'fxRate' => $form_state->getValue('fx_rate')
                            ];
                            $journal->record($a);
                        }
                    }
                }
            
            if (round($journal->credit,$this->rounding) <> round($journal->debit,$this->rounding)) {
                $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            $url = Url::fromRoute('ek_finance.manage.journal_edit', ['id' => $rec[1]], [])->toString();
            \Drupal::messenger()->addStatus(t('Data updated. <a href="@url">Edit</a>', ['@url' => $url]));
        }
    }

}
