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
        
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => t('company'),
            '#required' => TRUE,
            '#ajax' => array(
                'callback' => array($this, 'get_class'),
                'wrapper' => 'accounts_class',
            ),
            '#prefix' => "<div class='container-inline'>",
        );

        if ($form_state->getValue('coid')) {
            $coid = $form_state->getValue('coid');
            $Extract = ['pdf' => t("Print in Pdf"), 'excel' => t('Excel download')];
            $classoptions = AidList::listclass($coid, array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), NULL);
            array_unshift($classoptions, 'Pdf');
            array_unshift($classoptions, 'Excel');
        }

        $form['class'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($classoptions) ? $classoptions : array(),
            '#required' => TRUE,
            '#title' => t('Class'),
            '#prefix' => "<div id='accounts_class'>",
            '#suffix' => '</div>',
            '#validated' => TRUE,
        );

        $form['next'] = array(
            '#type' => 'submit',
            '#value' => $this->t('List accounts'),
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'get_accounts')),
            '#states' => array(
                // Hide data fieldset when class is empty.
                'invisible' => array(
                    "select[name='class']" => array('value' => ''),
                ),
            ),
            '#suffix' => '</div>',
        );

/*
        if( $form_state->getValue('coid') ) {
            $form['pdf'] = array(
                '#title' => $this->t('Pdf'),
                '#type' => 'link',
                '#url' => Url::fromRoute('ek_finance.admin.charts_accounts_download', ['coid' => $form_state->getValue('coid')]),
                '#attributes' => ['target' => '_blank'],
                '#prefix' => "<br/><div>",
                '#suffix' => ' </div>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => NULL),
                    ),
                ),
            );
            $form['excel'] = array(
                '#title' => $this->t('Excel'),
                '#type' => 'link',
                '#url' => Url::fromRoute('ek_finance.admin.charts_accounts_excel_export', ['coid' => $form_state->getValue('coid')]),
                '#attributes' => ['target' => '_blank'],
                '#prefix' => "<div>",
                '#suffix' => ' </div>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
         }   

*/
        if ($form_state->getValue('step') == 2 
                & ($form_state->getValue('class') != '0' && $form_state->getValue('class') != '1')) {

            $form['step'] = array(
                '#type' => 'hidden',
                '#value' => 2,
            );

            $form['list'] = array(
                '#type' => 'fieldset',
                '#title' => 'list',
                '#prefix' => "<div id='list'>",
                '#suffix' => '</div>',
                '#collapsible' => TRUE,
                '#open' => TRUE,
                '#validated' => TRUE,
                '#tree' => TRUE,
            );

            $header = "<div class='table' id='accounts_list'>
                <div class='row'>
                  <div class='cell cell50'></div>
                  <div class='cell cell150'></div>
                  <div class='cell cell100'>" . t('Balance') . "</div>
                  <div class='cell cell100'>" . t('Balance') . " " . $baseCurrency . "</div>
                  <div class='cell cell100'>" . t('Balance date') . "</div>
                  <div class='cell cell50'>" . t('Active') . "</div>
              ";

            $form['list']['head'] = array(
                '#type' => 'item',
                '#markup' => $header,
            );

            $query = "SELECT * from {ek_accounts} where atype=:atype and aid like :aid and coid=:coid ORDER BY aid";
            $a = array(':atype' => 'class', ':aid' => $form_state->getValue('class'), ':coid' => $form_state->getValue('coid'));
            $thisclass = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

            $id = $thisclass->id;

            $form['list']['c'][$id]['aid'] = array(
                '#type' => 'item',
                '#markup' => $thisclass->aid,
                '#prefix' => "<div class='row'><div class='cell cell50 cellborder'>",
                '#suffix' => '</div>',
            );

            $form['list']['c'][$id]['aname'] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 50,
                '#default_value' => $thisclass->aname,
                '#prefix' => "<div class='cell cell150 cellborder'>",
                '#suffix' => '</div>',
            );

            $form['list']['c'][$id]['col3'] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            );

            $form['list']['c'][$id]['col4'] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            );

            $form['list']['c'][$id]['col5'] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cell100'>",
                '#suffix' => '</div>',
            );

            $form['list']['c'][$id]['astatus'] = array(
                '#type' => 'checkbox',
                '#default_value' => $thisclass->astatus,
                '#prefix' => "<div class='cell cell50 cellborder '>",
                '#suffix' => '</div></div>',
            );


            $query = "SELECT id,aid,aname,astatus,balance,balance_base,balance_date "
                    . "FROM {ek_accounts} "
                    . "WHERE atype=:atype "
                    . "AND aid like :aid and coid=:coid ORDER BY aid";

            $class = substr($form_state->getValue('class'), 0, 2) . '%';
            $a = array(':atype' => 'detail', ':aid' => $class, ':coid' => $form_state->getValue('coid'));


            $list = Database::getConnection('external_db', 'external_db')->query($query, $a);

            while ($row = $list->fetchObject()) {

                $id = $row->id;
                if ($row->astatus == '1') {
                    $css = 'grey';
                } else {
                    $css = '';
                }

                //check if the account is used
                $query = "SELECT id FROM {ek_journal} WHERE coid=:c AND aid=:a LIMIT 1";
                $a = array(':a' => $row->aid, ':c' => $form_state->getValue('coid'));
                $check = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)
                        ->fetchField();
                if ($check > 0) {
                    $markup = "<b title='" . t('account used in journal') . "'>" . $row->aid . '</b>';
                } else {
                    $markup = $row->aid;
                }

                $form['list']['d'][$id]['aid'] = array(
                    '#type' => 'item',
                    '#markup' => $markup,
                    '#prefix' => "<div class='row " . $css . "' id='a" . $id . "' ><div class='cell cell50'>",
                    '#suffix' => '</div>',
                );

                $form['list']['d'][$id]['aname'] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 50,
                    '#default_value' => $row->aname,
                    '#disabled' => $check ? TRUE : FALSE,
                    '#prefix' => "<div class='cell cell150'>",
                    '#suffix' => '</div>',
                );


                $form['list']['d'][$id]['balance'] = array(
                    '#type' => 'textfield',
                    '#default_value' => number_format($row->balance, 2),
                    '#size' => 15,
                    '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                    '#prefix' => "<div class='cell cell100'>",
                    '#suffix' => '</div>',
                );

                $form['list']['d'][$id]['balance_base'] = array(
                    '#type' => 'textfield',
                    '#default_value' => number_format($row->balance_base, 2),
                    '#size' => 15,
                    '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                    '#prefix' => "<div class='cell cell100'>",
                    '#suffix' => '</div>',
                );

                $form['list']['d'][$id]['balance_date'] = array(
                    '#type' => 'date',
                    '#default_value' => $row->balance_date,
                    '#size' => 14,
                    '#prefix' => "<div class='cell cell150'>",
                    '#suffix' => '</div>',
                );

                $form['list']['d'][$id]['astatus'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => $row->astatus,
                    '#attributes' => array('onclick' => "jQuery('#a" . $id . "' ).toggleClass('grey');"),
                    '#prefix' => "<div class='cell cell50 cellcenter'>",
                    '#suffix' => '</div></div>',
                );
            }

            $param = $form_state->getValue('coid') . '-' . $class;
            $url = Url::fromRoute('ek_finance.admin.modal_charts_accounts', array('param' => $param))->toString();
            $new = t('<a href="@url" class="@c" >+ new account</a>', array('@url' => $url, '@c' => 'use-ajax red'));

            $form['list']['close'] = array(
                '#type' => 'item',
                '#markup' => $new . '</div></div>',
            );


            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );
        } elseif ($form_state->getValue('step') == 2) {
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Extract'),
                '#suffix' => '');
        }

        $form['#tree'] = TRUE;
        $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';
        return $form;
    }

    /**
     * Callback 
     */
    public function get_class(array &$form, FormStateInterface $form_state) {
        return [$form['class']];//, $form['pdf'], $form['excel']
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

        if ($form_state->getValue('step') == 2 
                & ($form_state->getValue('class') != '0' && $form_state->getValue('class') != '1')) {
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

              
        if($form_state->getValue('class') == '0' || $form_state->getValue('class') == '1') {
            if($form_state->getValue('class') == '1') {
                $form_state->setRedirect('ek_finance.admin.charts_accounts_download',['coid' => $form_state->getValue('coid')],['target' => '_blank']);
            } else {
                $form_state->setRedirect('ek_finance.admin.charts_accounts_excel_export',['coid' => $form_state->getValue('coid')]);
            }
        }
        
        if ($form_state->getValue('step') == 2) {

                $formValues = $form_state->getValues();
                $c = $formValues['list']['c'];
                $d = $formValues['list']['d'];

                foreach ($c as $key => $value) {

                    $fields = array('aname' => $value['aname'], 'astatus' => $value['astatus']);
                    $update = Database::getConnection('external_db', 'external_db')
                            ->update('ek_accounts')
                            ->condition('id', $key)
                            ->fields($fields)
                            ->execute();
                }

                foreach ($d as $key => $value) {



                    $fields = array(
                        'aname' => $value['aname'],
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

               \Drupal::messenger()->addStatus(t('Data updated'));
            
        }//step 2
    }

}
