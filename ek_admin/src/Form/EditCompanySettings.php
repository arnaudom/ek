<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\EditCompanySettings.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides an company settings form.
 * Includes finance parameters
 */
class EditCompanySettings extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_edit_company_settings_form';
    }

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
    public function __construct(ModuleHandler $module_handler)
    {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
        $form['coid'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        //add settings used by accounts (finance).
        if ($this->moduleHandler->moduleExists('ek_finance') && !$id == null) {

            //check option to create chart if not exists (use case: module activated after company)
            $chart = null;
            if (isset($id)) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a');
                $query->fields('a', ['id']);
                $query->condition('coid', $id);
                $obj = $query->execute();

                if ($obj->fetchField()) {
                    $chart = true;
                }
            }

            if ($chart == null) {
                $option = [0 => $this->t('Default standard')];
                $t = $this->t('Copy from') . ":";
                $option[$t] = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT id,name from {ek_company} order by name")
                        ->fetchAllKeyed();

                $form['chart'] = array(
                    '#type' => 'select',
                    '#title' => $this->t('Select chart of accounts'),
                    '#options' => $option,
                    '#default_value' => null,
                    '#required' => true,
                    '#description' => $this->t('chart selection from other company will be copied into new entity'),
                );
                $form['edit_chart'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                );
            } else {
                $settings = new CompanySettings($id);
                $Currencies = CurrencyData::listcurrency(1);
                //list of accounts chart is defined in the general finance settings
                //The chart structure is as follow
                // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 'other_liabilities', 'other_income', 'other_expenses'

                $fsettings = new FinanceSettings();
                $chart = $fsettings->get('chart');
                //do a check on settings to display an update list to the user
                $miss = array();
                $holder = [];
                $i = 0;

                if ($settings->get('fiscal_year') == '') {
                    $miss['fiscal_year'] = $this->t('Fiscal year settings');
                }
                if ($settings->get('fiscal_month') == '') {
                    $miss['fiscal_month'] = $this->t('Fiscal month settings');
                }
                if ($settings->get('stax_collect_aid') == '') {
                    $miss['stax_collect_aid'] = $this->t('Collection account for sales tax');
                }
                if ($settings->get('stax_deduct_aid') == '') {
                    $miss['stax_deduct_aid'] = $this->t('Deduction account for sales tax');
                }
                if ($settings->get('stax_rate') == '') {
                    $miss['stax_rate'] = $this->t('Rate of sales tax');
                }
                if ($settings->get('stax_name') == '') {
                    $miss['stax_name'] = $this->t('Default name of sales tax');
                }
                if ($settings->get('wtax_collect_aid') == '') {
                    $miss['wtax_collect_aid'] = $this->t('Secondary collection account for tax');
                }
                if ($settings->get('wtax_deduct_aid') == '') {
                    $miss['wtax_deduct_aid'] = $this->t('Secondary deduction account for tax');
                }
                if ($settings->get('wtax_rate') == '') {
                    $miss['wtax_rate'] = $this->t('Secondary rate of tax');
                }
                if ($settings->get('ytax_name') == '') {
                    $miss['ytax_name'] = $this->t('Default name of third tax');
                }
                if ($settings->get('ytax_collect_aid') == '') {
                    $miss['ytax_collect_aid'] = $this->t('Third collection account for tax');
                }
                if ($settings->get('ytax_deduct_aid') == '') {
                    $miss['ytax_deduct_aid'] = $this->t('Third deduction account for tax');
                }
                if ($settings->get('ytax_rate') == '') {
                    $miss['ytax_rate'] = $this->t('Third rate of tax');
                }
                if ($settings->get('ytax_name') == '') {
                    $miss['ytax_name'] = $this->t('Default name of Third tax');
                }
                if ($settings->get('CurrencyGainLoss') == '') {
                    $miss['CurrencyGainLoss'] = $this->t('Account to compute currency gain or loss');
                }
                if ($settings->get('sales_liabilities') == '') {
                    $miss['sales_liabilities'] = $this->t('Display liabilities accounts in sales form');
                }


                foreach ($Currencies as $currency => $name) {
                    $i++;
                    if ($settings->get('cash_account', $currency) == '') {
                        $miss['cash' . $currency] = $this->t('Main cash account for @c', array('@c' => $currency));
                    } else {
                        $holder[1][$settings->get('cash_account', $currency)] = $currency;
                    }
                    if ($settings->get('cash2_account', $currency) == '') {
                        $miss['cash2' . $currency] = $this->t('Secondary cash account for @c', array('@c' => $currency));
                    } else {
                        $holder[2][$settings->get('cash2_account', $currency)] = $currency;
                    }
                    if ($settings->get('asset_account', $currency) == '') {
                        $miss['asset_account' . $currency] = $this->t('receivable account, debtor account for @c', array('@c' => $currency));
                    } else {
                        $holder[3][$settings->get('asset_account', $currency)] = $currency;
                    }
                    if ($settings->get('liability_account', $currency) == '') {
                        $miss['liability_account' . $currency] = $this->t('liability account, creditor account for @c', array('@c' => $currency));
                    } else {
                        $holder[4][$settings->get('liability_account', $currency)] = $currency;
                    }
                }

                $label = ['1' => $this->t('cash main'), '2' => $this->t('cash other'), '3' => $this->t('receivable'), '4' => $this->t('liability')];
                for ($n = 1; $n < 5; $n++) {
                    if (count($holder[$n]) <> $i) {
                        $miss[$n] = $this->t('You are using same account for different currencies') . " (<a href='#currency'>" . $label[$n] . "</a>)";
                    }
                }

                $list = '';
                foreach ($miss as $key => $value) {
                    $list .= "<li>" . $value . "</li>";
                }

                if ($list == '') {
                    $list = '<li>' . $this->t('All parameters are set') . '</li>';
                }

                $form['info'] = [
                    '#type' => 'details',
                    '#title' => $this->t('Settings verification'),
                    '#open' => (empty($miss)) ? false : true,
                ];
                $form['info']['miss'] = [
                    '#type' => 'item',
                    '#markup' => $this->t('Missing settings') . ':' . "<ul>" . $list . "</ul>",
                ];

                $fiscal_year = $settings->get('fiscal_year');
                $fiscal_month = $settings->get('fiscal_month');
                $year = date('Y');
                $options = [$year + 1, $year, $year - 1, $year - 2, $year - 3, $year - 4];
                $form['fiscal_year'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#disabled' => isset($fiscal_year) ? true : false,
                    '#options' => array_combine($options, $options),
                    '#default_value' => isset($fiscal_year) ? $fiscal_year : $year,
                    '#title' => $this->t('Fiscal year'),
                    '#prefix' => "<div class='container-inline'>",
                ];

                $form['fiscal_month'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => ['01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10', '11' => '11', '12' => '12'],
                    '#default_value' => isset($fiscal_month) ? $fiscal_month : 12,
                    '#title' => $this->t('Month'),
                    '#suffix' => '</div>',
                ];

                $form['g']['stax_collect'] = [
                    '#type' => 'select',
                    '#options' => ['0' => $this->t('no'), '1' => $this->t('yes')],
                    '#size' => 1,
                    '#default_value' => $settings->get('stax_collect'),
                    '#description' => $this->t('Collectible sales tax'),
                ];

                $form['g']['stax_collect_aid'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => AidList::listaid($id, [$chart['liabilities']], 1),
                    '#default_value' => $settings->get('stax_collect_aid'),
                    '#description' => $this->t('Sales tax collection account'),
                ];


                $form['g']['stax_deduct'] = [
                    '#type' => 'select',
                    '#options' => ['0' => $this->t('no'), '1' => $this->t('yes')],
                    '#size' => 1,
                    '#default_value' => $settings->get('stax_deduct'),
                    '#description' => $this->t('Deductible sales tax'),
                ];

                $form['g']['stax_deduct_aid'] = [
                    '#type' => 'select',
                    '#options' => AidList::listaid($id, [$chart['assets']], 1),
                    '#size' => 1,
                    '#default_value' => $settings->get('stax_deduct_aid'),
                    '#description' => $this->t('Sales tax deduction account'),
                ];

                $form['g']['stax_rate'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 255,
                    '#default_value' => $settings->get('stax_rate'),
                    '#attributes' => ['placeholder' => $this->t('Sales tax rate')],
                    '#description' => $this->t('Sales tax rate'),
                ];

                $form['g']['stax_name'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 50,
                    '#default_value' => $settings->get('stax_name'),
                    '#attributes' => ['placeholder' => $this->t('defaultname')],
                    '#description' => $this->t('Sales tax default name'),
                ];

                $form['g']['wtax_collect_aid'] = [
                    '#type' => 'select',
                    '#options' => AidList::listaid($id, [$chart['liabilities']], 1),
                    '#size' => 1,
                    '#default_value' => $settings->get('wtax_collect_aid'),
                    '#description' => $this->t('Other  tax collection account'),
                ];

                $form['g']['wtax_deduct_aid'] = [
                    '#type' => 'select',
                    '#options' => AidList::listaid($id, [$chart['assets']], 1),
                    '#size' => 1,
                    '#default_value' => $settings->get('wtax_deduct_aid'),
                    '#description' => $this->t('Other tax deduction account'),
                ];

                $form['g']['wtax_rate'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 255,
                    '#default_value' => $settings->get('wtax_rate'),
                    '#attributes' => ['placeholder' => $this->t('other tax rate')],
                    '#description' => $this->t('Other tax rate'),
                ];

                $form['g']['wtax_name'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 50,
                    '#default_value' => $settings->get('wtax_name'),
                    '#attributes' => ['placeholder' => $this->t('default name')],
                    '#description' => $this->t('Other tax default name'),
                ];

                $form['g']['ytax_collect_aid'] = [
                    '#type' => 'select',
                    '#options' => AidList::listaid($id, [$chart['liabilities']], 1),
                    '#size' => 1,
                    '#default_value' => $settings->get('ytax_collect_aid'),
                    '#description' => $this->t('Other  tax collection account'),
                ];

                $form['g']['ytax_deduct_aid'] = [
                    '#type' => 'select',
                    '#options' => AidList::listaid($id, [$chart['assets']], 1),
                    '#size' => 1,
                    '#default_value' => $settings->get('ytax_deduct_aid'),
                    '#description' => $this->t('Other tax deduction account'),
                ];

                $form['g']['ytax_rate'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 255,
                    '#default_value' => $settings->get('ytax_rate'),
                    '#attributes' => ['placeholder' => $this->t('other tax rate')],
                    '#description' => $this->t('Other tax rate'),
                ];

                $form['g']['ytax_name'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 50,
                    '#default_value' => $settings->get('ytax_name'),
                    '#attributes' => ['placeholder' => $this->t('default name')],
                    '#description' => $this->t('Other tax default name'),
                ];

                $form['g']['CurrencyGainLoss'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => AidList::listaid($id, [$chart['income'], $chart['other_income']], 1),
                    '#default_value' => $settings->get('CurrencyGainLoss') ? $settings->get('CurrencyGainLoss') : '49001',
                    '#description' => $this->t('currency gain, loss account'),
                ];

                $form['g']['sales_liabilities'] = [
                    '#type' => 'select',
                    '#options' => ['0' => $this->t('no'), '1' => $this->t('yes')],
                    '#size' => 1,
                    '#default_value' => $settings->get('sales_liabilities'),
                    '#description' => $this->t('List liabilities in sales form'),
                ];

                $header = [
                    'name' => '',
                    'cash_account' => $this->t('main cash account'),
                    'cash2_account' => $this->t('other cash account'),
                    'asset_account' => $this->t('receivable account, debtor'),
                    'liability_account' => $this->t('liability account, creditor'),
                ];

                $form['currency_settings'] = [
                    '#prefix' => '<div id="currency">',
                    '#suffix' => '</div>',
                    '#tree' => true,
                    '#theme' => 'table',
                    '#header' => $header,
                    '#rows' => [],
                ];

                $perm = \Drupal::currentUser()->hasPermission('administrate_finance') ? 0 : 1;
                foreach ($Currencies as $currency => $name) {
                    $cname = [
                        '#id' => 'name-' . $currency . "-$name",
                        '#type' => 'item',
                        '#markup' => $name . " (" . $currency . ")",
                        '#value' => $currency
                    ];

                    $cash_account = [
                        '#id' => 'name-' . $currency . '-cash_account',
                        '#type' => 'select',
                        '#options' => AidList::listaid($id, [$chart['assets']], 1),
                        '#default_value' => $settings->get('cash_account', $currency),
                        '#attributes' => ['style' => ['width:120px; white-space:nowrap']],
                        '#disabled' => ($settings->get('cash_account', $currency) && $perm) ? true : false,
                    ];


                    $cash2_account = [
                        '#id' => 'name-' . $currency . '-cash2_account',
                        '#type' => 'select',
                        '#options' => AidList::listaid($id, [$chart['assets']], 1),
                        '#default_value' => $settings->get('cash2_account', $currency),
                        '#attributes' => ['style' => ['width:120px; white-space:nowrap']],
                        '#disabled' => ($settings->get('cash2_account', $currency) && $perm) ? true : false,
                    ];

                    $asset_account = [
                        '#id' => 'name-' . $currency . '-asset_account',
                        '#type' => 'select',
                        '#options' => AidList::listaid($id, [$chart['assets']], 1),
                        '#default_value' => $settings->get('asset_account', $currency),
                        '#attributes' => ['style' => ['width:120px; white-space:nowrap']],
                        '#disabled' => ($settings->get('asset_account', $currency) && $perm) ? true : false,
                    ];

                    $liability_account = [
                        '#id' => 'name-' . $currency . '-liability_account',
                        '#type' => 'select',
                        '#options' => AidList::listaid($id, [$chart['liabilities']], 1),
                        '#default_value' => $settings->get('liability_account', $currency),
                        '#attributes' => ['style' => ['width:120px; white-space:nowrap']],
                        '#disabled' => ($settings->get('liability_account', $currency) && $perm) ? true : false,
                    ];

                    $form['currency_settings'][] = [
                        'cname' => &$cname,
                        'cash_account' => &$cash_account,
                        'cash2_account' => &$cash2_account,
                        'asset_account' => &$asset_account,
                        'liability_account' => &$liability_account,
                    ];

                    $form['currency_settings']['#rows'][] = [
                        ['data' => &$cname],
                        ['data' => &$cash_account],
                        ['data' => &$cash2_account],
                        ['data' => &$asset_account],
                        ['data' => &$liability_account],
                    ];

                    unset($cname);
                    unset($cash_account);
                    unset($cash2_account);
                    unset($asset_account);
                    unset($liability_account);
                }//for
            }//else chart exists

            $form['actions'] = ['#type' => 'actions'];
            $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Record')];
        } else {
            $form['alert'] = [
                '#type' => 'item',
                '#markup' => $this->t('Required finance module is not enabled. Please contact administrator'),
            ];
        }

        if ($this->moduleHandler->moduleExists('ek_finance') && !$id == null) {
            /* TODO */
        } else {
            $form['alert2'] = [
                '#type' => 'item',
                '#markup' => $this->t('Required sales module is not enabled. Please contact administrator'),
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->getValue('stax_rate') != '') {
            if (!is_numeric($form_state->getValue('stax_rate')) || $form_state->getValue('stax_rate') == 0) {
                $form_state->setErrorByName('stax_rate', $this->t('Wrong sales tax value input'));
            }
        }
        if ($form_state->getValue('wtax_rate') != '') {
            if (!is_numeric($form_state->getValue('wtax_rate')) || $form_state->getValue('wtax_rate') == 0) {
                $form_state->setErrorByName('wtax_rate', $this->t('Wrong tax value input'));
            }
        }
        if ($form_state->getValue('ytax_rate') != '') {
            if (!is_numeric($form_state->getValue('ytax_rate')) || $form_state->getValue('ytax_rate') == 0) {
                $form_state->setErrorByName('ytax_rate', $this->t('Wrong tax value input'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if (!null == $form_state->getValue('edit_chart')) {
            if ($form_state->getValue('chart') == 0) {
                // load standard accounts
                $file = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/ek_standard_accounts.sql';
                $query = file_get_contents($file);
                $acc = Database::getConnection('external_db', 'external_db')->query($query);
                $balance_date = date('Y') . '-01-01';
                $acc = Database::getConnection('external_db', 'external_db')->update('ek_accounts')
                                ->condition('coid', 'x')
                                ->fields(['coid' => $form_state->getValue('coid'), 'balance_date' => $balance_date])
                                ->execute();
            } else {
                // copy chart from other account
                $query = "SELECT * from {ek_accounts} WHERE coid=:c ORDER by aid";
                $acc = Database::getConnection('external_db', 'external_db')
                            ->query($query, [':c' => $form_state->getValue('chart')]);
                $date = date('Y') . '-01-01';
                while ($a = $acc->fetchObject()) {
                    $fields = [
                            'aid' => $a->aid,
                            'aname' => $a->aname,
                            'atype' => $a->atype,
                            'astatus' => $a->astatus,
                            'coid' => $id,
                            'link' => '',
                            'balance' => 0,
                            'balance_base' => 0,
                            'balance_date' => $date,

                        ];
                    Database::getConnection('external_db', 'external_db')
                             ->insert('ek_accounts')
                                ->fields($fields)
                                ->execute();
                }
            }
                
            \Drupal::messenger()->addStatus(t('Chart structure saved'));
        } else {
            $settings = new CompanySettings($form_state->getValue('coid'));

            $settings->set('fiscal_year', $form_state->getValue('fiscal_year'));
            $settings->set('fiscal_month', $form_state->getValue('fiscal_month'));
            $settings->set('stax_collect_aid', $form_state->getValue('stax_collect_aid'));
            $settings->set('stax_deduct_aid', $form_state->getValue('stax_deduct_aid'));
            $settings->set('stax_collect', $form_state->getValue('stax_collect'));
            $settings->set('stax_deduct', $form_state->getValue('stax_deduct'));
            $settings->set('wtax_collect_aid', $form_state->getValue('wtax_collect_aid'));
            $settings->set('wtax_deduct_aid', $form_state->getValue('wtax_deduct_aid'));
            $settings->set('stax_rate', $form_state->getValue('stax_rate'));
            $settings->set('stax_name', $form_state->getValue('stax_name'));
            $settings->set('wtax_rate', $form_state->getValue('wtax_rate'));
            $settings->set('wtax_name', $form_state->getValue('wtax_name'));
            $settings->set('ytax_collect_aid', $form_state->getValue('ytax_collect_aid'));
            $settings->set('ytax_deduct_aid', $form_state->getValue('ytax_deduct_aid'));
            $settings->set('ytax_rate', $form_state->getValue('ytax_rate'));
            $settings->set('ytax_name', $form_state->getValue('ytax_name'));
            $settings->set('CurrencyGainLoss', $form_state->getValue('CurrencyGainLoss'));
            $settings->set('sales_liabilities', $form_state->getValue('sales_liabilities'));

            foreach ($form_state->getValue('currency_settings') as $c) {
                $currency = $c['cname'];
                array_shift($c);

                foreach ($c as $name => $value) {
                    $settings->set($name, $value, $currency);
                }
            }


            $settings->save();
            \Drupal\Core\Cache\Cache::invalidateTags(['ek_admin.settings']);
            $h = Url::fromRoute('ek_admin.company.list')->toString();
            \Drupal::messenger()->addStatus(t('Settings saved. Go back to <a href="@h">list</a>', ['@h' => $h]));
        }
    }
}
