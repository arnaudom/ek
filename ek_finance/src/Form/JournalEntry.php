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

/**
 * Provides a form to create a manual journal entry
 */
class JournalEntry extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'journal_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        if (!$id == NULL) {
            //pull edit data
        }

        if ($form_state->get('step') == '') {

            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
            '#title' => t('company'),
            '#disabled' => $form_state->getValue('coid') ? TRUE : FALSE,
            '#required' => TRUE,
            '#prefix' => "<div class='container-inline'>",
        );

        if ($form_state->getValue('coid') == NULL) {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
                '#suffix' => '</div> ',
            );
        } else {
            $form['item'] = array(
                '#type' => 'item',
                '#markup' => '</div><br/> ',
            );
        }

        if ($form_state->getValue('coid')) {

            $form_state->set('step', 2);

            if ($form_state->get('num_items') == '') {
                $form_state->set('num_items', 1);
            }

            $CurrencyOptions = CurrencyData::listcurrency(1);
            $accountOptions = array('0' => '');
            $accountOptions += AidList::listaid($form_state->getValue('coid'), array(0,1, 2, 3, 4, 5, 6, 7, 8, 9), 1);


            $form['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CurrencyOptions,
                '#required' => TRUE,
                '#default_value' => ($form_state->getValue('currency')) ? $form_state->getValue('currency') : NULL,
                '#title' => t('Currency'),
                '#ajax' => array(
                    'callback' => array($this, 'fx_rate'),
                    'wrapper' => 'credit',
                ),
                '#prefix' => "<div class='container-inline'>",
            );

            $form['fx_rate'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 15,
                '#default_value' => ($form_state->getValue('fx_rate')) ? $form_state->getValue('fx_rate') : NULL,
                '#required' => FALSE,
                '#title' => t('Exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='credit'>",
                '#suffix' => '</div></div>',
            );

            $form["date"] = array(
                '#type' => 'date',
                '#size' => 12,
                '#required' => TRUE,
                '#default_value' => ($form_state->getValue("date")) ? $form_state->getValue("date") : date('Y-m-d'),
                '#title' => t('Record date'),
                '#prefix' => "",
                '#suffix' => '',
            );


            $headerline = "<div class='table'><div class='row'><div class='cell cellborder'>"
                    . t("Debit account") . "</div><div class='cell cellborder'>"
                    . t("Debit") . "</div><div class='cell cellborder'>"
                    . t("Credit") . "</div><div class='cell cellborder'>"
                    . t("Credit account") . "</div><div class='cell cellborder'>" . t("Comment") . "</div>";

            $totalCT = 0;
            $totalDT = 0;

            $form['items']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $form['items']["d-account1"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => ($form_state->getValue('d-account1')) ? $form_state->getValue('d-account1') : NULL,
                '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']['debit1'] = array(
                '#type' => 'textfield',
                '#id' => 'debit1',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => ($form_state->getValue('debit1')) ? $form_state->getValue('debit1') : NULL,
                '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']['credit1'] = array(
                '#type' => 'textfield',
                '#id' => 'credit1',
                '#size' => 12,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => ($form_state->getValue('credit1')) ? $form_state->getValue('credit1') : NULL,
                '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["c-account1"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $accountOptions,
                '#default_value' => ($form_state->getValue('c-account')) ? $form_state->getValue('c-account') : NULL,
                '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["comment1"] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => ($form_state->getValue('comment1')) ? $form_state->getValue('comment1') : NULL,
                '#attributes' => array('placeholder' => t('comment'),),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );

            //$totalCT += str_replace(",", "", $form_state->getValue('credit1'));
            //$totalDT += str_replace(",", "", $form_state->getValue('debit1'));
            //loop added rows
            if (isset($n)) {
                // reset the new rows items in edit mode
                $max = $form_state->get('num_items') + $n;
                $form_state->set('num_items', $max);
            } else {
                //new entry
                $max = $form_state->get('num_items');
                $n = 2;
            }

            for ($i = $n; $i <= $max; $i++) {

                $form['items']["d-account$i"] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $accountOptions,
                    '#default_value' => ($form_state->getValue("d-account$i")) ? $form_state->getValue("d-account$i") : NULL,
                    '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["debit$i"] = array(
                    '#type' => 'textfield',
                    '#id' => "debit$i",
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->getValue("debit$i")) ? $form_state->getValue("debit$i") : NULL,
                    '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["credit$i"] = array(
                    '#type' => 'textfield',
                    '#id' => "credit$i",
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->getValue("credit$i")) ? $form_state->getValue("credit$i") : NULL,
                    '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["c-account$i"] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $accountOptions,
                    '#default_value' => ($form_state->getValue("c-account$i")) ? $form_state->getValue("c-account$i") : NULL,
                    '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["comment$i"] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => ($form_state->getValue("comment$i")) ? $form_state->getValue("comment$i") : NULL,
                    '#attributes' => array('placeholder' => t('comment'),),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                );


                //$totalCT += $form_state->getValue("credit$i");
                //$totalDT += $form_state->getValue("debit$i");
            }

//loop added rows

            if ($form_state->get('totalCT') == $form_state->get('totalDT')) {
                $style = "";
            } else {
                $style = "delete";
            }

//footer


            $form['items']["footer1"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='row'><div class='cell cellborder'>",
                '#suffix' => '</div>',
            );
            $form['items']["footer2"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder $style' id='totald'>"
                . number_format($form_state->get('totalDT'), 2) . "",
                '#suffix' => '</div>',
            );
            $form['items']["footer3"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder $style' id='totalc'>"
                . number_format($form_state->get('totalCT'), 2) . "",
                '#suffix' => '</div>',
            );
            $form['items']["footer4"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div>',
            );
            $form['items']["footer5"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div></div></div>',
            );



            $form['rows'] = array(
                '#type' => 'hidden',
                '#attributes' => array('id' => 'rows'),
                '#value' => $form_state->get('num_items'),
            );

            if (!NULL == $form_state->getValue('confirm_box')) {
                $form['confirm'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#description' => $this->t('You are transferring value between accounts that use different currencies. Please confirm.'),
                    '#prefix' => '<div class="messages messages--warning">',
                    '#suffix' => '</div>'
                );
            } else {
                $form['confirm'] = ['#markup' => ''];
            }

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['add'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Add line'),
                //'#limit_validation_errors' => array(),
                '#submit' => array(array($this, 'addForm')),
                '#prefix' => "",
                '#suffix' => '',
            );

            if ($form_state->get('num_items') > 1) {
                $form['actions']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last line'),
                    //'#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                );
            }


            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );


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
            $form['fx_rate']['#required'] = TRUE;
            $form['credit']['fx_rate']['#description'] = '';
        } else {
            $form['fx_rate']['#required'] = False;
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

        if ($form_state->get('step') == 2) {

            if ($form_state->getValue('fx_rate') == '') {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate must be indicated'));
            }
            if (!is_numeric($form_state->getValue('fx_rate'))) {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate input is not correct'));
            }


            $totalCT = 0;
            $totalDT = 0;
            $accounts = [];

            for ($i = 1; $i <= $form_state->getValue('rows'); $i++) {

                $debit = str_replace(',', '', $form_state->getValue("debit$i"));
                if ($debit == '')
                    $debit = 0;
                $credit = str_replace(',', '', $form_state->getValue("credit$i"));
                if ($credit == '')
                    $credit = 0;

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


                $totalCT += $credit;
                $totalDT += $debit;
                array_push($accounts, $form_state->getValue("d-account$i"));
                array_push($accounts, $form_state->getValue("c-account$i"));
            }

            $form_state->set('totalDT', $totalDT);
            $form_state->set('totalCT', $totalCT);

            if ($totalCT <> $totalDT) {

                $form['items']["footer2"] = array(
                    '#type' => 'item',
                    '#prefix' => "<div class='cell cellborder delete' id='totald'>"
                    . number_format($totalDT, 2) . "",
                    '#suffix' => '</div>',
                );
                $form['items']["footer3"] = array(
                    '#type' => 'item',
                    '#prefix' => "<div class='cell cellborder delete' id='totalc'>"
                    . number_format($totalCT, 2) . "",
                    '#suffix' => '</div>',
                );
                $form_state->setErrorByName("items][footer2", $this->t('entry is not balanced') . ' ' . $totalCT . ' => ' . $totalDT);

                $form_state->setRebuild();
            } else {
                $form['items']["footer2"] = array(
                    '#type' => 'item',
                    '#prefix' => "<div class='cell cellborder record' id='totald'>"
                    . number_format($totalDT, 2) . "",
                    '#suffix' => '</div>',
                );
                $form['items']["footer3"] = array(
                    '#type' => 'item',
                    '#prefix' => "<div class='cell cellborder record' id='totalc'>"
                    . number_format($totalCT, 2) . "",
                    '#suffix' => '</div>',
                );
            }

            //filter accounts transfer with different currencies
            //@TODO : add verification with accounts linked to bank
            $companysettings = new CompanySettings($form_state->getValue('coid'));
            $cash1 = $companysettings->get('cash_account', $form_state->getValue('currency'));
            $cash2 = $companysettings->get('cash2_account', $form_state->getValue('currency'));

            if ((in_array($cash1, $accounts) || in_array($cash2, $accounts)) && $form_state->getValue('confirm') == 0) {
                $form_state->setValue('confirm_box', 1);
                $form_state->setRebuild();
            }
        }
        /**/
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



        if ($form_state->get('step') == 2) {
            $journal = new Journal();

            $query = "SELECT reference FROM {ek_journal} WHERE source=:s ORDER  by id DESC limit 1";

            $ref = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => 'general'))
                    ->fetchField();
            $ref++;
            for ($i = 1; $i <= $form_state->getValue('rows'); $i++) {


                $debit = str_replace(',', '', $form_state->getValue("debit$i"));
                if ($debit <> '') {
                    $rec[$i] = $journal->record(
                            array(
                                'source' => "general",
                                'coid' => $form_state->getValue('coid'),
                                'aid' => $form_state->getValue("d-account$i"),
                                'type' => 'debit',
                                'reference' => $ref,
                                'date' => $form_state->getValue('date'),
                                'value' => $debit,
                                'currency' => $form_state->getValue('currency'),
                                'comment' => Xss::filter($form_state->getValue("comment$i")),
                                'fxRate' => $form_state->getValue('fx_rate')
                            )
                    );
                }
                $credit = str_replace(',', '', $form_state->getValue("credit$i"));
                if ($credit <> '') {
                    $journal->record(
                            array(
                                'source' => "general",
                                'coid' => $form_state->getValue('coid'),
                                'aid' => $form_state->getValue("c-account$i"),
                                'type' => 'credit',
                                'reference' => $ref,
                                'date' => $form_state->getValue('date'),
                                'value' => $credit,
                                'currency' => $form_state->getValue('currency'),
                                'comment' => Xss::filter($form_state->getValue("comment$i")),
                                'fxRate' => $form_state->getValue('fx_rate')
                            )
                    );
                }
            }

            $url = Url::fromRoute('ek_finance.manage.journal_edit', array('id' => $rec[1]), array())->toString();
            drupal_set_message(t('Data updated. <a href="@url">Edit</a>', ['@url' => $url]), 'status');
        }//step 2
    }

}
