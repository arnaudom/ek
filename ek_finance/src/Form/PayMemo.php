<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\PayMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;


/**
 * Provides a form to record memo payment.
 */
class PayMemo extends FormBase {
   /**
     * {@inheritdoc}
     */
    public function __construct() {
      $this->settings = new FinanceSettings();
      $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding'):2;
    }
  
  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'ek_finance_pay_memo';
    }

  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        $data = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * FROM {ek_expenses_memo} where id=:id", array(':id' => $id))
                ->fetchObject();
        $list = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * FROM {ek_expenses_memo_list} where serial=:s", array(':s' => $data->serial));
        
        $attachments = Database::getConnection('external_db', 'external_db')
                ->query("SELECT id,uri FROM {ek_expenses_memo_documents} where serial=:s", array(':s' => $data->serial))
                ->fetchAllKeyed();
        $attachment = ["0" => t("No attachment")];
        foreach($attachments as $key => $val) {
            $str = explode("/", $val);
            $str = array_reverse($str);
            $attachment[$val] = $str[0];
        }

        $form['ref'] = array(
            '#type' => 'item',
            '#markup' => t('Memo ref. @p', array('@p' => $data->serial)),
        );

        $form['pay'] = array(
            '#type' => 'item',
            '#markup' => t('Total value @p', array('@p' => number_format($data->value, 2) . ' ' . $data->currency)),
        );

        $form['baseValue'] = array(
            '#type' => 'hidden',
            '#value' => $data->value,
        );
        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        $form['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => date('Y-m-d'),
            '#title' => t('Payment date'),
        );



        $form['close'] = array(
            '#type' => 'checkbox',
            '#default_value' => 0,
            '#title' => t('Close'),
            '#description' => t('Set status as closed or paid on partial payment.'),
        );



        //bank account

        $settings = new CompanySettings($data->entity_to);
        $aid = $settings->get('cash_account', $data->currency);
        if ($aid <> '') {
            $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
            $name = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => $data->entity_to, ':a' => $aid))
                    ->fetchField();
            $key = $data->currency . "-" . $aid;
            $cash = array($key => $name);
        }
        $aid = $settings->get('cash2_account', $data->currency);
        if ($aid <> '') {
            $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
            $name = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => $data->entity_to, ':a' => $aid))
                    ->fetchField();
            $key = $data->currency . "-" . $aid;
            $cash += array($key => $name);
        }

        if($data->category == 5) {
            //add option to pay from user cash account
            $query = "SELECT name from {users_field_data} WHERE uid=:uid";
            $name = db_query($query, array(':uid' => $data->entity))
                    ->fetchField();
            $options[(string)t('user cash account')] = array('user' => $name);
        }
        $options[(string)t('cash')] = $cash;
        $options[(string)t('bank')] = BankData::listbankaccountsbyaid($data->entity_to);

        $form['bank_account'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $options,
            '#required' => TRUE,
            '#default_value' => NULL,
            '#title' => t('Account payment'),
            '#ajax' => array(
                'callback' => array($this, 'fx_rate'),
                'wrapper' => 'fx',
            ),
        );

        $form['fx_rate'] = array(
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 255,
            '#default_value' => '',
            '#required' => FALSE,
            '#title' => t('Exchange rate'),
            '#description' => '',
            '#prefix' => "<div id='fx'>",
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'credit_amount'),
                'wrapper' => 'fx',
                'event' => 'change',
            ),
        );
        if ($data->status == 1) {
            $form['info'] = array(
                '#type' => 'item',
                '#markup' => '<div class="orangedot floatleft"></div><b>' . t('Balance not paid : @p @c', ['@p' => $data->value - $data->amount_paid, '@c' => $data->currency]) . '</b>',
            );
        }
        
        $chart = $this->settings->get('chart');
        $AidOptions = AidList::listaid($data->entity_to, array($chart['liabilities'],$chart['expenses'], $chart['other_expenses']), 1);
        $i = 0;

        $form['items']['table'] = array(
            '#type' => 'item',
            '#prefix' => "<div class='table'>"
        );


        While ($l = $list->fetchObject()) {
            $i++;

            $form['items']["aid$i"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $AidOptions,
                '#required' => TRUE,
                '#default_value' => $l->aid,
                '#attributes' => array('style' => array('width:130px;')),
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["description$i"] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => $data->serial . ' ' . $l->description,
                '#attributes' => array('placeholder' => t('description')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["amount$i"] = array(
                '#type' => 'textfield',
                '#id' => 'amount' . $i,
                '#size' => 12,
                '#maxlength' => 255,
                '#default_value' => $l->amount,
                '#attributes' => array('placeholder' => t('amount'), 'class' => array('amount')),
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div>',
            );
            
            $form['items']["attachment$i"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $attachment,
                '#required' => TRUE,
                '#default_value' => NULL,
                '#attributes' => array('style' => array('width:130px;')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );
        }




        $form['items']['1'] = array(
            '#type' => 'item',
            '#markup' => t('Total'),
            '#prefix' => "<div class='row' id='memo_form_footer'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['items']['2'] = array(
            '#type' => 'item',
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['items']["grandtotal"] = array(
            '#type' => 'textfield',
            '#id' => 'grandtotal',
            '#size' => 12,
            '#maxlength' => 255,
            '#default_value' => number_format($data->value, 2),
            '#attributes' => array('placeholder' => t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
            '#prefix' => "<div class='cell right'> ",
            '#suffix' => $data->currency. '</div></div></div>',
        );


        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $i,
            '#attributes' => array('id' => 'itemsCount'),
        );

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );
        $form['#attached']['library'][] = 'ek_finance/ek_finance.memo_pay_form';

        return $form;
    }

  /**
   * Callback
   */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        /* if selected bank account is not in the memo currency, provide a choice for exchange rate
         */
        $query = "SELECT currency from {ek_expenses_memo} where id=:id";
        $currency = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchField();

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "user")) {
            //use user cash account
            $currency2 = $currency;
        } elseif (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value
            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency2 = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('bank_account')))
                    ->fetchField();
        }

        if($currency2 == '0'){
            $message = t('<span class="red">Warning: you are paying from a user account. '
                    . 'Verify that user cash account is credited.</span>');
            $form['fx_rate']['#description'] = $message;
            $form['fx_rate']['#value'] = 1;
        }
        elseif ($currency <> $currency2) {

            $form['fx_rate']['#required'] = TRUE;
            $memo_rate = CurrencyData::rate($currency);
            $pay_rate = CurrencyData::rate($currency2);
            if ($pay_rate && $memo_rate) {
                $fx_rate = round($pay_rate / $memo_rate, 4);
                $form['fx_rate']['#value'] = $fx_rate;
                $credit = round($form_state->getValue('baseValue') * $fx_rate, $this->rounding);
                $message = t('<span class="red">Warning: you are not paying from a <b>@p</b> '
                        . 'account</span>.<br/>Amount credited @c @a', 
                        array('@c' => $currency2, '@a' => $credit, '@p' => $currency));
                $form['fx_rate']['#description'] = $message;
            } else {
                $form['fx_rate']['#value'] = 0;
                $form['fx_rate']['#description'] = '';
            }
        } else {
            $form['fx_rate']['#required'] = False;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

  /**
   * Callback
   */
    public function credit_amount(array &$form, FormStateInterface $form_state) {
        /* update credit amount estimated when manual fx change
         */
        $query = "SELECT currency from {ek_expenses_memo} where id=:id";
        $currency = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $form['for_id']['#value']))->fetchField();

        // FILTER cash account
        if ($form_state->getValue('bank_account') == "user"){
            //use user cash account
            $currency2 = $currency;
        } elseif (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value

            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('bank_account')))->fetchField();
        }


        if ($currency <> $currency2) {
            if ($form_state->getValue('fx_rate')) {
                
                $credit = round($form_state->getValue('baseValue') * $form_state->getValue('fx_rate'), $this->rounding);
                $message = t('<span class="red">Warning: you are not paying from a <b>@p</b> account.</span>'
                        . '<br/>Amount credited @c @a', 
                        array('@c' => $currency2, '@a' => $credit, '@p' => $currency));
                $form['fx_rate']['#description'] = $message;
            
                
            } else {
                $form['fx_rate']['#description'] = '';
            }
        } else {
            $form['fx_rate']['#required'] = False;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

  /**
   * {@inheritdoc}
   */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('fx_rate') <= 0 ) {
            $form_state->setErrorByName("fx_rate", $this->t('the exchange rate cannot be null or negative'));
        }
        if ( !is_numeric($form_state->getValue('fx_rate'))) {
            $form_state->setErrorByName("fx_rate", $this->t('the exchange must be a number > 0'));
        }
        $this_pay = 0;
        for ($i = 1; $i <= $form_state->getValue('count'); $i++) {

            $value = $form_state->getValue('amount' . $i);
            $value = str_replace(',', '', $value);
            if (!is_numeric($value)) {
                $form_state->setErrorByName('amount' . $i, $this->t('The input value is wrong'));
            } else {
                $this_pay += $value;
            }
        }

        //verify amount paid does not exceed amount due or partially paid
        $query = "SELECT value,amount_paid,currency FROM {ek_expenses_memo} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();
        $max_pay = $data->value - $data->amount_paid;
        
        if (($this_pay - $data->value) > 0.000001) {
            $form_state->setErrorByName("amount", $this->t('Payment exceeds memo amount.') . ": " . number_format($this_pay,6) . ' > ' . number_format($data->value,6));
        }

        if (( ( $this_pay - $max_pay ) > 0.000001)) {
            $form_state->setErrorByName("amount", $this->t('Partial payment exceeds memo amount.') . ": " . number_format($this_pay,6) . ' > ' . number_format($data->value,6));
        }
    }

  /**
   * {@inheritdoc}
   */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $journal = new Journal();
        
        $query = "SELECT * from {ek_expenses_memo} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();
        (float) $max_pay = $data->value - $data->amount_paid;
        $memo_rate = CurrencyData::rate($data->currency);

        // FILTER payment account
        if ($form_state->getValue('bank_account') == "user") {
            //use user cash account
            $query = "SELECT name from {users_field_data} WHERE uid=:uid";
            $employee = db_query($query, array(':uid' => $data->entity))
                    ->fetchField();
            $currency2 = $data->currency;
            $cash = 'Y';
            $settings = new CompanySettings($data->entity_to);
            $aid = $settings->get('cash_account', $data->currency);
            $credit = $currency2 . '-' . $aid;
        } elseif (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value
            $employee = 'n/a';
            $bk = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $bk[0];
            $cash = 'Y';
            $credit = $form_state->getValue('bank_account');
            $settings = new CompanySettings($data->entity_to);
        } else {
            $employee = 'n/a';
            $cash = $form_state->getValue('bank_account');
            $credit = $form_state->getValue('bank_account');
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency2 = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('bank_account')))
                    ->fetchField();
            
            
        }
        
        $pay_rate = $form_state->getValue('fx_rate');
        $rate2 = CurrencyData::rate($currency2);
        (float) $this_pay = 0;

        $allocation = $data->entity_to;

        for ($i = 1; $i <= $form_state->getValue('count'); $i++) {
            
            $localcurrency = round($form_state->getValue('amount' . $i) * $pay_rate , $this->rounding);
            $rate = round($pay_rate * $memo_rate, 4);
            $amount = round($localcurrency/$rate, $this->rounding);
            $attachment = ($form_state->getValue('attachment' . $i) == '0') ?
                    "" : $form_state->getValue('attachment' . $i);

            $fields = array(
                'class' => substr($form_state->getValue('aid' . $i), 0, 2),
                'type' => $form_state->getValue('aid' . $i),
                'allocation' => $allocation,
                'company' => $data->entity_to,
                'localcurrency' => $localcurrency,
                'rate' => $rate,
                'amount' => $amount,
                'currency' => $currency2,
                'amount_paid' => $amount,
                'year' => date('Y'),
                'month' => date('m'),
                'comment' => $form_state->getValue('description' . $i),
                'pcode' => $data->pcode,
                'clientname' => $data->client,
                'suppliername' => 'not supplier related',
                'receipt' => 'yes',
                'employee' => $employee,
                'status' => 'paid',
                'cash' => $cash,
                'pdate' => $form_state->getValue('date'),
                'reconcile' => 0,
                'attachment' => $attachment,
            );


            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_expenses')
                    ->fields($fields)
                    ->execute();

            $journal->record(
                    array(
                        'source' => "expense",
                        'coid' => $data->entity_to,
                        'aid' => $form_state->getValue('aid' . $i),
                        'bank' => $credit,
                        'reference' => $insert,
                        'date' => $form_state->getValue('date'),
                        'value' => $localcurrency,
                        'currency' => $currency2,
                        'fx_rate' => $rate,
                    )
            );

            $this_pay += $form_state->getValue('amount' . $i);
        }

        $post = 1;

        if (round($this_pay,$this->rounding) == round($max_pay,$this->rounding)) {
            $paid = 2; //full payment
        } else {
            if ($form_state->getValue('close') == '1') {
                $paid = 2; //force close
            } else {
                $paid = 1; // partial payment
            }
        }

        $fields = array(
            'status' => $paid,
            'amount_paid' => $this_pay,
            'amount_paid_base' => round($this_pay / $memo_rate, $this->rounding),
            'pdate' => $form_state->getValue('date'),
            'post' => $post,
        );


        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_expenses_memo')
                ->fields($fields)
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();


        if ($update) {
            $url = Url::fromRoute('ek_finance.manage.edit_expense', ['id' => $insert])->toString();
            \Drupal::messenger()->addStatus(t('Payment recorded for @id. Go to <a href="@url">expense</a> if you need to edit record.', ['@id' => $data->serial, '@url' => $url]));
            $action = array( 1 => t('Partially paid'), 2 => t('Paid'));
            
                //notify for payment
                if($data->category < 5) {
                    $query = Database::getConnection('external_db', 'external_db')
                              ->select('ek_company', 'c');
                    $query->fields('c', ['name','email', 'contact']);
                    $query->condition('id', $data->entity, '=');
                    $entity = $query->execute()->fetchObject();
                    $entity_mail = $entity->email;
                } else {
                    $query = "SELECT name,mail from {users_field_data} WHERE uid=:u";
                    $entity = db_query($query, array(':u' => $data->entity))
                            ->fetchObject();
                    $entity_mail = $entity->mail;
                }
                
                $query = Database::getConnection('external_db', 'external_db')
                          ->select('ek_company', 'c');
                $query->fields('c', ['name','email', 'contact']);
                $query->condition('id', $data->entity_to, '=');
                $entity_to = $query->execute()->fetchObject();  
                

                if (isset($entity_mail) && isset($entity_to->email)) {
                      $params['subject'] = t('Payment information') . ': ' . $data->serial;
                      $url = $GLOBALS['base_url'] . Url::fromRoute('ek_finance_manage_print_html', array('id' => $data->id))->toString();
                      $params['options']['url'] = "<a href='". $url ."'>" . $data->serial . "</a>";
                      $params['options']['user'] = $entity->name;

                      $params['body'] = t('Memo ref. @p',['@p' => $data->serial]) . "." . t('Issued to') . ": " . $entity_to->name; 
                      $params['body'] .= '<br/>' .  t('Status') . ": " . $action[$paid];
                      $error = [];

                      $send = \Drupal::service('plugin.manager.mail')->mail(
                          'ek_finance',
                          'key_memo_note',
                          $entity_mail,
                          \Drupal::languageManager()->getDefaultLanguage()->getId(),
                          $params,
                          $entity_to->email,
                          TRUE
                        );

                        if($send['result'] == FALSE) {
                          $error[] = $entity_to->email;
                        }   

                      $params['subject'] = t('Payment information') . ': ' . $data->serial . " (" . t('copy') . ")";
                      $send = \Drupal::service('plugin.manager.mail')->mail(
                          'ek_finance',
                          'key_memo_note',
                          $entity_to->email,
                          \Drupal::languageManager()->getDefaultLanguage()->getId(),
                          $params,
                          $entity_mail,
                          TRUE
                        );

                        if($send['result'] == FALSE) {
                          $error[] = $entity->email;
                        }  

                        if(!empty($error)) {
                          $errors = implode(',', $error);
                          \Drupal::messenger()->addError(t('Error sending notification to :t', [':t' => $errors]));
                        }

                      }
            
            
            
            
            if ($data->category < 5) {
                $form_state->setRedirect('ek_finance_manage_list_memo_internal');
            } else {
                $form_state->setRedirect('ek_finance_manage_list_memo_personal');
            }
        }

 
    }

}
