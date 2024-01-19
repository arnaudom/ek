<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\ChartAccounts.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to create or edit finance accounts chart
 */
class ChartAccounts extends FormBase {

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
        return 'chart_accounts';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        if ($form_state->getValue('step') == '') {
            $form_state->setValue('step', 1);
        }

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $company = AccessCheck::CompanyListByUid();

        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => $this->t('company'),
            '#required' => true,
            '#ajax' => [
                'callback' => [$this, 'get_class'],
                'wrapper' => 'accounts_class',
            ],
            '#prefix' => "<div class='container-inline'>",
        ];

        if ($form_state->getValue('coid')) {
            $coid = $form_state->getValue('coid');
            $Extract = ['pdf' => $this->t("Print in Pdf"), 'excel' => $this->t('Excel download')];
            $classoptions = AidList::listclass($coid, array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), null);
            array_unshift($classoptions, 'Pdf');
            array_unshift($classoptions, 'Excel');
        }

        $form['class'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($classoptions) ? $classoptions : [],
            '#required' => true,
            '#title' => $this->t('Class'),
            '#prefix' => "<div id='accounts_class'>",
            '#suffix' => '</div>',
            '#validated' => true,
        ];

        $form['next'] = [
            '#type' => 'submit',
            '#value' => $this->t('List accounts'),
            '#submit' => [[$this, 'get_accounts']],
            '#suffix' => '</div>',
        ];


        if ($form_state->getValue('step') == 2 & ($form_state->getValue('class') != '0' && $form_state->getValue('class') != '1')) {
            $form['step'] = [
                '#type' => 'hidden',
                '#value' => 2,
            ];

            $form['list'] = [
                '#type' => 'fieldset',
                '#title' => 'list',
                '#prefix' => "<div id='list'>",
                '#suffix' => '</div>',
                '#collapsible' => true,
                '#open' => true,
                '#validated' => true,
                '#tree' => true,
            ];

            $header = "<div class='table' id='accounts_list'>
                <div class='row'>
                  <div class='cell cell50'></div>
                  <div class='cell cell150'></div>
                  <div class='cell cell100'>" . $this->t('Balance') . "</div>
                  <div class='cell cell100'>" . $this->t('Balance') . " " . $baseCurrency . "</div>
                  <div class='cell cell100'>" . $this->t('Balance date') . "</div>
                  <div class='cell cell50'>" . $this->t('Active') . "</div>
              ";

            $form['list']['head'] = [
                '#type' => 'item',
                '#markup' => $header,
            ];

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a')
                    ->condition('atype', 'class')
                    ->condition('aid', $form_state->getValue('class'), 'LIKE')
                    ->condition('coid',$form_state->getValue('coid'))
                    ->orderBy('aid')
                    ->execute();

            $thisclass = $query->fetchObject();
            $id = $thisclass->id;

            $form['list']['c'][$id]['aid'] = [
                '#type' => 'item',
                '#markup' => $thisclass->aid,
                '#prefix' => "<div class='row'><div class='cell cell50 cellborder'>",
                '#suffix' => '</div>',
            ];

            $form['list']['c'][$id]['aname'] = [
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 50,
                '#default_value' => $thisclass->aname,
                '#prefix' => "<div class='cell cell150 cellborder'>",
                '#suffix' => '</div>',
            ];

            $form['list']['c'][$id]['col3'] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            ];

            $form['list']['c'][$id]['col4'] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            ];

            $form['list']['c'][$id]['col5'] = [
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            ];

            $form['list']['c'][$id]['astatus'] = [
                '#type' => 'checkbox',
                '#default_value' => $thisclass->astatus,
                '#prefix' => "<div class='cell cell50 cellborder '>",
                '#suffix' => '</div></div>',
            ];

            $class = substr($form_state->getValue('class'), 0, 2) . '%';
            $list = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a', ['id','aid','aname','astatus','balance', 'balance_base','balance_date'])
                    ->condition('atype', 'detail')
                    ->condition('aid', $class, 'LIKE')
                    ->condition('coid',$form_state->getValue('coid'))
                    ->orderBy('aid')
                    ->execute();
            
            while ($row = $list->fetchObject()) {
                $id = $row->id;
                if ($row->astatus == '1') {
                    $css = 'grey';
                } else {
                    $css = '';
                }

                // check if the account is used
                $query = Database::getConnection('external_db', 'external_db')
                         ->select('ek_journal', 'j')
                         ->fields('j', ['id'])
                         ->condition('coid', $form_state->getValue('coid'))
                         ->condition('aid', $row->aid)
                         ->extend('Drupal\Core\Database\Query\TableSortExtender')
                         ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                         ->limit(1)
                         ->execute();
                $check = $query->fetchField();
                if ($check > 0) {
                    $markup = "<b title='" . $this->t('account used in journal') . "'>" . $row->aid . '</b>';
                } else {
                    $markup = $row->aid;
                }

                 // Force edit name for Site admin
                $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
                if ($user->hasRole('administrator')) {
                    $check = false;
                }

                $form['list']['d'][$id]['aid'] = [
                    '#type' => 'item',
                    '#markup' => $markup,
                    '#prefix' => "<div class='row " . $css . "' id='a" . $id . "' ><div class='cell cell50'>",
                    '#suffix' => '</div>',
                ];

                $form['list']['d'][$id]['aname'] = [
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 50,
                    '#default_value' => $row->aname,
                    '#disabled' => $check ? true : false,
                    '#prefix' => "<div class='cell cell150'>",
                    '#suffix' => '</div>',
                ];


                $form['list']['d'][$id]['balance'] = [
                    '#type' => 'textfield',
                    '#default_value' => number_format($row->balance, 2),
                    '#size' => 15,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"],
                    '#prefix' => "<div class='cell cell100'>",
                    '#suffix' => '</div>',
                ];

                $form['list']['d'][$id]['balance_base'] = [
                    '#type' => 'textfield',
                    '#default_value' => number_format($row->balance_base, 2),
                    '#size' => 15,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"],
                    '#prefix' => "<div class='cell cell100'>",
                    '#suffix' => '</div>',
                ];

                $form['list']['d'][$id]['balance_date'] = [
                    '#type' => 'date',
                    '#default_value' => $row->balance_date,
                    '#size' => 14,
                    '#prefix' => "<div class='cell cell150'>",
                    '#suffix' => '</div>',
                ];

                $form['list']['d'][$id]['astatus'] = [
                    '#type' => 'checkbox',
                    '#default_value' => $row->astatus,
                    '#attributes' => array('onclick' => "jQuery('#a" . $id . "' ).toggleClass('grey');"),
                    '#prefix' => "<div class='cell cell50 cellcenter'>",
                    '#suffix' => '</div></div>',
                ];
            }

            $param = $form_state->getValue('coid') . '-' . $class;
            $url = Url::fromRoute('ek_finance.admin.modal_charts_accounts', array('param' => $param))->toString();
            $new = $this->t('<a href="@url" class="@c" >+ new account</a>', array('@url' => $url, '@c' => 'use-ajax red'));

            $form['list']['close'] = [
                '#type' => 'item',
                '#markup' => $new . '</div></div>',
            ];


            $form['actions'] = [
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            ];

            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            ];

        } elseif ($form_state->getValue('step') == 2) {
            $form['actions'] = [
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            ];

            $form['actions']['submit'] =[
                '#type' => 'submit',
                '#value' => $this->t('Extract'),
                '#suffix' => ''
            ];
        }

        $form['#tree'] = true;
        $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';
        return $form;
    }

    /**
     * Callback
     */
    public function get_class(array &$form, FormStateInterface $form_state) {
        return [$form['class']]; //, $form['pdf'], $form['excel']
    }

    /**
     * Callback
     */
    public function get_accounts(array &$form, FormStateInterface $form_state) {
        $form_state->setValue('step', 2);
        $form_state->setRebuild();
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('step') == 2 & ($form_state->getValue('class') != '0' && $form_state->getValue('class') != '1')) {
            $formValues = $form_state->getValues();
            //check validity of input values
            $d = $formValues['list']['d'];

            foreach ($d as $key => $value) {
                $val = str_replace(',', '', $value['balance']);
                if (!is_numeric($val)) {
                    $n = "list][d][" . $key . "][balance";
                    $form_state->setErrorByName($n, $this->t('balance value not numeric'));
                }
                $val = str_replace(',', '', $value['balance_base']);
                if (!is_numeric($val)) {
                    $n = "list][d][" . $key . "][balance_base";
                    $form_state->setErrorByName($n, $this->t('balance base value not numeric'));
                }
                if (!\DateTime::createFromFormat('Y-m-d', $value['balance_date'])) {
                    $n = "list][d][" . $key . "][balance_date";
                    $form_state->setErrorByName($n, $this->t('balance date error'));
                }
            }
        }

        /**/
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('class') == '0' || $form_state->getValue('class') == '1') {
            if ($form_state->getValue('class') == '1') {
                $form_state->setRedirect('ek_finance.admin.charts_accounts_download', ['coid' => $form_state->getValue('coid')], ['target' => '_blank']);
            } else {
                $form_state->setRedirect('ek_finance.admin.charts_accounts_excel_export', ['coid' => $form_state->getValue('coid')]);
            }
        }

        if ($form_state->getValue('step') == 2) {
            $formValues = $form_state->getValues();
            $c = $formValues['list']['c'];
            $d = $formValues['list']['d'];

            foreach ($c as $key => $value) {
                $fields = array('aname' => \Drupal\Component\Utility\Xss::filter($value['aname']), 'astatus' => $value['astatus']);
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_accounts')
                        ->condition('id', $key)
                        ->fields($fields)
                        ->execute();
            }

            foreach ($d as $key => $value) {
                $balance = (float) str_replace(',', '', $value['balance']);
                $balance_base = (float) str_replace(',', '', $value['balance_base']);

                if (is_numeric($balance) && is_numeric($balance_base)) {
                    $fields = array(
                        'aname' => \Drupal\Component\Utility\Xss::filter($value['aname']),
                        'balance' => str_replace(',', '', $value['balance']),
                        'balance_base' => str_replace(',', '', $value['balance_base']),
                        'balance_date' => $value['balance_date'],
                        'astatus' => $value['astatus']);
                    $update = Database::getConnection('external_db', 'external_db')
                            ->update('ek_accounts')
                            ->condition('id', $key)
                            ->fields($fields)
                            ->execute();
                }
            }

            \Drupal::messenger()->addStatus(t('Data updated'));
        }//step 2
    }

}
