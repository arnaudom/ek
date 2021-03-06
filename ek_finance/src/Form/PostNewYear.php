<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\PostNewYear.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to post finance closing values to next year opening values
 */
class PostNewYear extends FormBase {

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
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
        $this->finance_settings = new FinanceSettings();
        $this->chart = $this->finance_settings->get('chart');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'post_new_accounting_year';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $year = date('Y');
        $month = date('m');

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
        $company = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':t' => 1, ':c' => $company))
                ->fetchAllKeyed();

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }



        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#title' => $this->t('company'),
            '#disabled' => $form_state->getValue('coid') ? true : false,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
        );

        if ($form_state->get('step') == 1) {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#submit' => array(array($this, 'get_accounts')),
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {

            //extract data and seetings
            $settings = new CompanySettings($form_state->getValue('coid'));

            if ($settings->get('fiscal_year') == null || $settings->get('fiscal_month') == null) {
                //finance settings for the company have not been set.
                //display warning
                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>" .
                    $this->t('Current fiscal year not set. Please update company financial settings first.') .
                    "</div>",
                );
            } elseif ($year == $settings->get('fiscal_year') && $month < $settings->get('fiscal_month')) {
                //already posted?
                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>" .
                    $this->t('Fiscal year is already set to current year @y - @m', array('@y' => $settings->get('fiscal_year'), '@m' => $settings->get('fiscal_month'))) .
                    "</div>",
                );
            } else {
                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>" .
                    $this->t('You are going to post accounting data to next fiscal year.') .
                    "</div>",
                );


                //display detail of posted data
                $journal = new Journal();
                $settings = new CompanySettings($form_state->getValue('coid'));
                $fiscal_year = $settings->get('fiscal_year');
                $fiscal_month = $settings->get('fiscal_month');
                $dates = $journal->getFiscalDates($form_state->getValue('coid'), $fiscal_year, $fiscal_month);
                $from = $dates['from'];
                $to = $dates['to'];
                $earn_date = $dates['fiscal_end'];
                $earning = $journal->current_earning($form_state->getValue('coid'), $from, $to);
                $display = '';
                $rows = '';

                //determine range of balance sheet items
                //other assets
                $other_assets_min = $this->chart['other_assets'] * 10000;
                $other_assets_max = $other_assets_min + 9999;

                //assets
                $assets_min = $this->chart['assets'] * 10000;
                $assets_max = $assets_min + 9999;

                //liabilities
                $liabilities_min = $this->chart['liabilities'] * 10000;
                $liabilities_max = $liabilities_min + 9999;

                //other liabilities
                $other_liabilities_min = $this->chart['liabilities'] * 10000;
                $other_liabilities_max = $other_liabilities_min + 9999;

                //equity
                $equity_min = $this->chart['equity'] * 10000;
                $equity_max = $equity_min + 9999;
                $earnings_account = $equity_min + 9001; //default
                $reserve_account = $equity_min + 8001; //default

                $q = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a')
                        ->fields('a')
                        ->condition('coid', $form_state->getValue('coid'))
                        ->orderBy('aid')
                        ->execute();

                while ($r = $q->fetchAssoc()) {
                    if (($r['aid'] >= $other_assets_min && $r['aid'] <= $other_assets_max) || ($r['aid'] >= $assets_min && $r['aid'] <= $assets_max) || ($r['aid'] >= $liabilities_min && $r['aid'] <= $liabilities_max) || ($r['aid'] >= $other_liabilities_min && $r['aid'] <= $other_liabilities_max) || ($r['aid'] >= $equity_min && $r['aid'] <= $equity_max)
                    ) {
                        if ($r['aid'] == $earnings_account) {
                            $r['balance_base'] = $earning[1];
                            $r['balance'] = $earning[0];
                        } elseif ($r['aid'] == $reserve_account) {
                            $e = $journal->opening(
                                    array(
                                        'aid' => $r['aid'],
                                        'coid' => $form_state->getValue('coid'),
                                        'from' => $to
                                    )
                            );
                            $b[1] = $e[1] + $earning[1];
                            $b[0] = $e[0] + $earning[0];
                        } else {
                            $b = $journal->opening(
                                    array(
                                        'aid' => $r['aid'],
                                        'coid' => $form_state->getValue('coid'),
                                        'from' => $to
                                    )
                            );
                        }

                        $rows .= "<tr class='detail'>
                          <td class='cursor' >" . $r['aid'] . " " . $r['aname'] . "</td>
                          <td align=center>" . $r['balance_date'] . "</td>"
                                . "<td align=right>" . number_format($r['balance_base'], 2) . "</td>"
                                . "<td align=right>" . number_format($r['balance'], 2) . "</td>
                          <td align=center>" . $dates['stop_date'] . "</td>"
                                . "<td align=right>" . number_format($b[1], 2) . "</td>"
                                . "<td align=right>" . number_format($b[0], 2) . "</td>
                          </tr>";
                    }
                }

                $display .= "<table>
                                <thead class='font24'>
                                  <tr>
                                    <td colspan='7'>" . $this->t('Current year start') . ": " . $from
                        . " , " . $this->t('New year start') . ": " . $dates['stop_date'] . "</td>
                                  </tr>
                                  <tr class=''>
                                    <td></td>
                                    <th colspan='3' align=center>Current</th>
                                    <th colspan='3' align=center>Next</th>
                                  </tr>
                                  <tr>
                                    <th>" . $this->t('Account') . "</th>
                                    <th>" . $this->t('Previous opening') . "</th>
                                    <th>" . $this->finance_settings->get('baseCurrency') . "</th>
                                    <th>" . $this->t('Local currency') . "</th>
                                    <th>" . $this->t('New opening') . "</th>
                                    <th>" . $this->finance_settings->get('baseCurrency') . "</th>
                                    <th>" . $this->t('Local currency') . "</th>
                                  </tr>
                                </thead>
                                <tbody >" . $rows . "</tbody></table>";


                //////////////////////////////

                $form['table'] = array(
                    '#type' => 'item',
                    '#markup' => $display,
                );

                $form['actions'] = array(
                    '#type' => 'actions',
                    '#attributes' => array('class' => array('container-inline')),
                );
                $form['actions']['submit'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Confirm New year posting'),
                );
            }
        }


        return $form;
    }

    /**
     * Callback
     */
    public function get_accounts(array &$form, FormStateInterface $form_state) {
        $form_state->set('step', 2);

        $form_state->setRebuild();

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
        $journal = new Journal();
        $settings = new CompanySettings($form_state->getValue('coid'));
        $fiscal_year = $settings->get('fiscal_year');
        $fiscal_month = $settings->get('fiscal_month');
        $fiscal_year = $settings->get('fiscal_year');
        $fiscal_month = $settings->get('fiscal_month');
        $dates = $journal->getFiscalDates($form_state->getValue('coid'), $fiscal_year, $fiscal_month);
        $from = $dates['from'];
        $to = $dates['to'];
        $earn_date = $dates['fiscal_end'];
        $earning = $journal->current_earning($form_state->getValue('coid'), $from, $earn_date);

        /* clone current tables as archives
         * name archive = table + fiscal year + coid
         */
        $name = "_" . $settings->get('fiscal_year') . '_' . $form_state->getValue('coid');
        $ek_accounts = "ek_accounts" . $name;
        $ek_journal = "ek_journal" . $name;

        $query = "DROP TABLE IF EXISTS " . $ek_accounts; /* if reset, to avoid query error */
        Database::getConnection('external_db', 'external_db')->query($query);
        $query = "CREATE TABLE " . $ek_accounts . " LIKE {ek_accounts}";
        Database::getConnection('external_db', 'external_db')->query($query);
        $query = "INSERT INTO " . $ek_accounts . " SELECT * FROM {ek_accounts} WHERE coid=:coid";
        Database::getConnection('external_db', 'external_db')
                ->query($query, [':coid' => $form_state->getValue('coid')]);

        $query = "DROP TABLE IF EXISTS " . $ek_journal; /* if reset, to avoid query error */
        Database::getConnection('external_db', 'external_db')->query($query);
        $query = "CREATE TABLE " . $ek_journal . " LIKE {ek_journal}";
        Database::getConnection('external_db', 'external_db')
                ->query($query);
        $query = "INSERT INTO " . $ek_journal . " SELECT * FROM {ek_journal} WHERE coid=:coid";
        Database::getConnection('external_db', 'external_db')
                ->query($query, [':coid' => $form_state->getValue('coid')]);

        $display = '';
        $rows = '';
        $report = array($form_state->getValue('coid'), $fiscal_year);
        $q = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 'a')
                ->fields('a')
                ->condition('coid', $form_state->getValue('coid'))
                ->orderBy('aid')
                ->execute();
        
        //other assets
        $other_assets_min = $this->chart['other_assets'] * 10000;
        $other_assets_max = $other_assets_min + 9999;

        //assets
        $assets_min = $this->chart['assets'] * 10000;
        $assets_max = $assets_min + 9999;

        //liabilities
        $liabilities_min = $this->chart['liabilities'] * 10000;
        $liabilities_max = $liabilities_min + 9999;

        //other liabilities
        $other_liabilities_min = $this->chart['liabilities'] * 10000;
        $other_liabilities_max = $other_liabilities_min + 9999;

        //equity
        $equity_min = $this->chart['equity'] * 10000;
        $equity_max = $equity_min + 9999;
        $earnings_account = $equity_min + 9001; //default
        $reserve_account = $equity_min + 8001; //default

        while ($r = $q->fetchAssoc()) {
            if ($r['aid'] == $earnings_account) {
                $r['balance_base'] = $earning[1];
                $r['balance'] = $earning[0];
            } elseif ($r['aid'] == $reserve_account) {
                $e = $journal->opening(
                        array(
                            'aid' => $r['aid'],
                            'coid' => $form_state->getValue('coid'),
                            'from' => $to
                        )
                );
                $b[1] = $e[1] + $earning[1];
                $b[0] = $e[0] + $earning[0];
            } else {
                $b = $journal->opening(
                        array(
                            'aid' => $r['aid'],
                            'coid' => $form_state->getValue('coid'),
                            'from' => $to
                        )
                );
            }

            if (($r['aid'] >= $other_assets_min && $r['aid'] <= $other_assets_max) 
                    || ($r['aid'] >= $assets_min && $r['aid'] <= $assets_max) 
                    || ($r['aid'] >= $liabilities_min && $r['aid'] <= $liabilities_max) 
                    || ($r['aid'] >= $other_liabilities_min && $r['aid'] <= $other_liabilities_max) 
                    || ($r['aid'] >= $equity_min && $r['aid'] <= $equity_max)
            ) {
                //balance sheet closing balance are reported to following year opening
                $fields = array('balance_date' => $dates['stop_date'], 'balance' => $b[0], 'balance_base' => $b[1]);
                array_push($report, array($r['aid'], $r['balance'], $r['balance_base'], $b[0], $b[1]));
            } else {
                $fields = array('balance_date' => $dates['stop_date'], 'balance' => 0, 'balance_base' => 0);
                array_push($report, array($r['aid'], $r['balance'], $r['balance_base'], 0, 0));
            }

            Database::getConnection('external_db', 'external_db')
                    ->update('ek_accounts')
                    ->condition('id', $r['id'])
                    ->fields($fields)
                    ->execute();
        }

        //save a report in the DB
        $report = json_encode($report);
        $fields = array(
            'type' => 0,
            'date' => date('Y-m-d'),
            'aid' => 0,
            'coid' => $form_state->getValue('coid'),
            'data' => $report
        );
        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_journal_reco_history')
                ->fields($fields)
                ->execute();

        //update the new fiscal year
        $settings->set('fiscal_year', $fiscal_year + 1);
        $settings->save();

        $form_state->setRedirect('ek_finance.extract.balance_sheet');
    }

}
