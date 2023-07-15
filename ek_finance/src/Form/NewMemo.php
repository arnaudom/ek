<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\NewMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to create and edit finance memo.
 */
class NewMemo extends FormBase {

        
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
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
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
        return 'ek_finance_new_memo';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $category = null, $tempSerial = null, $clone = false) {
        $CurrencyOptions = CurrencyData::listcurrency(1);
        $baseCurrency = $this->settings->get('baseCurrency');

        if (isset($id) && $id != null) {

            //edit existing memo
            $chart = $this->settings->get('chart');
            $baseCurrency = $this->settings->get('baseCurrency');
            $n = 0;

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'memo');
            $query->fields('memo');
            $query->condition('id', $id);
            $data = $query->execute()->fetchObject();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo_list', 'details');
            $query->fields('details');
            $query->condition('serial', $data->serial);
            $query->orderBy('id');
            $detail = $query->execute();

            if ($clone == false) {
                $date = $data->date;
                $form['edit_memo'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('Memo ref. @p', array('@p' => $data->serial)),
                );
                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
                $form['id'] = array(
                    '#type' => 'hidden',
                    '#value' => $id,
                );
            } else {
                $date = date('Y-m-d');
                $form['edit_memo'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('Clone ref. @p', array('@p' => $data->serial)),
                );
                $form['new_memo'] = array(
                    '#type' => 'hidden',
                    '#value' => 2,
                );
                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
                $form['id'] = array(
                    '#type' => 'hidden',
                    '#value' => $id,
                );
            }

            if (!$form_state->get('num_items')) {
                $form_state->set('num_items', 0);
            }

            if (!$form_state->getValue('currency')) {
                $form_state->setValue('currency', $data->currency);
            }

            if ($category == 'internal') {
                $AidOptions = AidList::listaid($data->entity, array($chart['expenses'], $chart['other_expenses']), 1);
            } else {
                $AidOptions = AidList::listaid($data->entity_to, array($chart['expenses'], $chart['other_expenses']), 1);
            }

            if ($category == 'personal' && $this->settings->get('authorizeMemo') == 1) {
                $auth_user = explode('|', $data->auth);
                $this->authorizer = $auth_user[1];
                //use flag to control form display options when opened by authorizer. Authorizer can't edit all data
                if (\Drupal::currentUser()->id() == $auth_user[1]) {
                    $authorizer = true;
                } else {
                    $authorizer = false;
                }
            } else {
                $authorizer = false;
            }
        } else {
            //new
            $form['new_memo'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );
            $date = date('Y-m-d');
            $grandtotal = 0;
            $n = 0;
            $AidOptions = $form_state->get('AidOptions');
            $detail = null;
            $data = null;
        }


        $form['tempSerial'] = array(
            //used for file uploaded
            '#type' => 'hidden',
            '#default_value' => $tempSerial,
            '#id' => 'tempSerial'
        );

        $url = Url::fromRoute('ek_finance_manage_list_memo_' . $category, array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        );

//
        //Options
//
        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => isset($id) || ($form_state->get('num_items') > 0) ? false : true,
        );

        if ($category == 'internal') {
            $type = array(1 => "Internal invoice", 2 => "Purchase", 3 => "Claim", 4 => "Advance");
            $type_select = null;
        } else {
            $type = array(5 => "Personal claim");
            $type_select = 5;
        }

        $form['options']['category'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $type,
            '#required' => true,
            '#default_value' => isset($data->category) ? $data->category : $type_select,
            '#title' => $this->t('Memo type'),
        );

        if ($this->settings->get('companyMemo') == 1) {
            $company = AccessCheck::CompanyListByUid();
        } else {
            $company = AccessCheck::CompanyList();
        }
        if ($category != 'internal') {
            if (\Drupal::currentUser()->hasPermission('admin_memos')) {
                $entity = \Drupal\ek_admin\Access\AccessCheck::listUsers();
            } else {
                $entity = array(
                    \Drupal::currentUser()->id() => \Drupal::currentUser()->getAccountName()
                );
                $data->entity = \Drupal::currentUser()->id();
            }
        } else {
            $entity = $company;
        }


        $form['options']['entity'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $entity,
            '#required' => true,
            '#default_value' => isset($data->entity) ? $data->entity : null,
            '#title' => $this->t('From entity'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['options']['entity_to'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#default_value' => isset($data->entity_to) ? $data->entity_to : null,
            '#title' => $this->t('To entity'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );



        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = array(0 => $this->t('not applicable'));
            $client += \Drupal\ek_address_book\AddressBookData::addresslist();

            if (!empty($client)) {
                $form['options']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($data->client) ? $data->client : null,
                    '#title' => $this->t('Client or supplier'),
                    '#attributes' => array('style' => array('width:300px;')),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();

                $form['options']['client'] = array(
                    '#markup' => $this->t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    '#default_value' => 0,
                );
            }
        } else {
            $form['options']['client'] = array(
                '#markup' => $this->t('You do not have any client or supplier list.'),
                '#default_value' => 0,
            );
        }

        $form['options']['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => true,
            '#default_value' => $date,
            '#title' => $this->t('date'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['options']['mission'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#required' => true,
            '#default_value' => isset($data->mission) ? $data->mission : null,
            '#title' => $this->t('Memo object'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            // project

            $form['options']['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => ProjectData::listprojects(0),
                '#required' => true,
                '#default_value' => isset($data->pcode) ? $data->pcode : null,
                '#title' => $this->t('Project'),
                '#attributes' => array('style' => array('width:250px;')),
            );
        } else {
            $form['options']['pcode'] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#required' => false,
                '#default_value' => isset($data->pcode) ? $data->pcode : null,
                '#title' => $this->t('Project tag'),
            );
        }
        $form['options']['currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $CurrencyOptions,
            '#required' => true,
            '#default_value' => isset($data->currency) ? $data->currency : null,
            '#title' => $this->t('currency'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );




        $form['options']['budget'] = array(
            '#type' => 'radios',
            '#options' => array('1' => $this->t('yes'), '0' => $this->t('no')),
            '#default_value' => isset($data->budget) ? $data->budget : null,
            '#attributes' => array('title' => $this->t('budget')),
            '#title' => $this->t('Budgeted'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['options']['refund'] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($data->refund) ? $data->refund : 0,
            '#attributes' => array('title' => $this->t('action')),
            '#description' => $this->t('Refund'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['options']['invoice'] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($data->invoice) ? $data->invoice : 0,
            '#attributes' => array('title' => $this->t('action')),
            '#description' => $this->t('Invoice client'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );

        $form['options']['comment'] = array(
            '#type' => 'textarea',
            '#rows' => 1,
            '#default_value' => isset($data->comment) ? $data->comment : null,
            '#attributes' => array('placeholder' => $this->t('comment')),
        );

//
        // Authorization
//

        if ($category == 'personal' && $this->settings->get('authorizeMemo') == 1) {
            $user_name = '';
            if (isset($auth_user)) {
                $query = Database::getConnection()
                        ->select('users_field_data', 'users');
                $query->fields('users', ['name']);
                $query->condition('uid', $auth_user[1]);
                $user_name = $query->execute()->fetchField();
            }

            $form['autho'] = array(
                '#type' => 'details',
                '#title' => $this->t('Authorization'),
                '#open' => true,
            );

            $form['autho']['user'] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#required' => true,
                '#default_value' => $user_name,
                '#title' => $this->t('Authorizer'),
                '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
            );

            if (isset($id) && $id != null) {
                //implement authorization

                if ($auth_user[1] == \Drupal::currentUser()->id() || \Drupal::currentUser()->hasPermission('admin_memos')) {
                    //authorizer need to approve
                    $form['autho']['action'] = array(
                        '#type' => 'radios',
                        '#options' => array('read' => $this->t('read only (no action taken)'), 1 => $this->t('request more data or receipts'), 2 => $this->t('authorize'), 3 => $this->t('reject')),
                        '#default_value' => $auth_user[0],
                        '#attributes' => array('title' => $this->t('action')),
                        '#title' => $this->t('Authorization'),
                    );
                } elseif (\Drupal::currentUser()->id() == $data->entity) {
                    //display status to owner
                    $action = array(0 => $this->t('not required'), 1 => $this->t('pending approval'), 2 => $this->t('authorized'), 3 => $this->t('rejected'));
                    $form['autho']['info'] = array(
                        '#type' => 'item',
                        '#markup' => $this->t('Authorization status') . ': ' . $action[$auth_user[0]],
                    );

                    $form['autho']['action'] = array(
                        '#type' => 'hidden',
                        '#value' => $auth_user[0],
                    );
                }
            }
        }

//
        // Items
//

        $form['items'] = array(
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => true,
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            '#submit' => array(array($this, 'addForm')),
            '#limit_validation_errors' => [['category'], ['entity'], ['entity_to'], ['itemTable']],
            '#attributes' => array('class' => array('button--add')),
        );


        $header = array(
            'account' => array(
                'data' => $this->t('Account'),
            ),
            'description' => array(
                'data' => $this->t('Description'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'amount' => array(
                'data' => $this->t('Amount'),
            ),
            'receipt' => array(
                'data' => $this->t('Receipt'),
            ),
            'delete' => array(
                'data' => $this->t('Delete'),
            )
        );

        $form['items']['itemTable'] = array(
            '#tree' => true,
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => array(),
            '#attributes' => array('id' => 'itemTable'),
            '#empty' => '',
        );


        if (isset($detail)) {
            //edition mode
            //list current items

            $grandtotal = 0;
            $rows = $form_state->getValue('itemTable');
            while ($d = $detail->fetchObject()) {
                $n++;
                $grandtotal += $d->amount;
                $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';

                $form['account'] = array(
                    '#id' => 'account-' . $n,
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $AidOptions,
                    '#attributes' => array('style' => array('width:110px;')),
                    '#default_value' => $d->aid,
                    '#required' => true,
                );
                $form['description'] = array(
                    '#id' => 'description-' . $n,
                    '#type' => 'textfield',
                    '#size' => 38,
                    '#maxlength' => 200,
                    '#attributes' => array('placeholder' => $this->t('description')),
                    '#default_value' => $d->description,
                    '#required' => true,
                );
                $form['amount'] = array(
                    '#id' => 'amount' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 30,
                    '#attributes' => array('placeholder' => $this->t('amount'), 'class' => array('amount')),
                    '#default_value' => number_format($d->amount, $this->rounding),
                    '#required' => true,
                );
                $form['receipt'] = array(
                    '#id' => 'receipt-' . $n,
                    '#type' => 'textfield',
                    '#size' => 10,
                    '#maxlength' => 100,
                    '#attributes' => array('placeholder' => $this->t('ref')),
                    '#default_value' => $d->receipt,
                );
                $form['delete'] = array(
                    '#id' => 'del' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#attributes' => array(
                        'title' => $this->t('delete'),
                        'onclick' => "jQuery('#" . $n . "').toggleClass('delete');",
                        'class' => array('amount')
                    ),
                );

                //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                    'account' => &$form['account'],
                    'description' => &$form['description'],
                    'amount' => &$form['amount'],
                    'receipt' => &$form['receipt'],
                    'delete' => &$form['delete']
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                    'data' => array(
                        array('data' => &$form['account']),
                        array('data' => &$form['description']),
                        array('data' => &$form['amount']),
                        array('data' => &$form['receipt']),
                        array('data' => &$form['delete']),
                    ),
                    'id' => array($n),
                    'class' => $rowClass,
                );

                unset($form['account']);
                unset($form['description']);
                unset($form['amount']);
                unset($form['receipt']);
                unset($form['delete']);
            }
        } //details of current records


        if (isset($detail)) {
            // reset the new rows items
            $max = $form_state->get('num_items') + $n;
            $n++;
        } else {
            $max = $form_state->get('num_items');
            $n = 1;
        }

        for ($i = $n; $i <= $max; $i++) {
            $grandtotal += $rows[$i]['amount'];
            $n++;
            $form['account'] = array(
                '#id' => 'account-' . $i,
                '#type' => 'select',
                '#size' => 1,
                '#options' => $AidOptions,
                '#attributes' => array('style' => array('width:110px;')),
                '#required' => true,
            );
            $form['description'] = array(
                '#id' => 'description-' . $i,
                '#type' => 'textfield',
                '#size' => 38,
                '#maxlength' => 200,
                '#attributes' => array('placeholder' => $this->t('description')),
                '#required' => true,
            );
            $form['amount'] = array(
                '#id' => 'amount' . $i,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 30,
                '#attributes' => array('placeholder' => $this->t('amount'), 'class' => array('amount')),
                '#required' => true,
            );
            $form['receipt'] = array(
                '#id' => 'receipt-' . $i,
                '#type' => 'textfield',
                '#size' => 10,
                '#maxlength' => 100,
                '#attributes' => array('placeholder' => $this->t('ref')),
            );
            $form['delete'] = array(
                '#type' => 'item',
            );

            //built rows for table
            $form['items']['itemTable'][$i] = array(
                'account' => &$form['account'],
                'description' => &$form['description'],
                'amount' => &$form['amount'],
                'receipt' => &$form['receipt'],
                'delete' => &$form['delete']
            );

            $form['items']['itemTable']['#rows'][$i] = array(
                'data' => array(
                    array('data' => &$form['account']),
                    array('data' => &$form['description']),
                    array('data' => &$form['amount']),
                    array('data' => &$form['receipt']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($i)
            );

            unset($form['account']);
            unset($form['description']);
            unset($form['amount']);
            unset($form['receipt']);
            unset($form['delete']);
        }



        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $n - 1,
            '#attributes' => array('id' => 'itemsCount'),
        );



        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {
            if (isset($id) && $baseCurrency != $data->currency) {
                $c = CurrencyData::currencyRates();
                $converted = round($grandtotal / $c[$data->currency], $this->rounding) . " " . $baseCurrency;
            } else {
                $converted = '';
            }
            $n++;
            $form['account'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Total')
            );
            $form['description'] = array(
                '#type' => 'hidden',
                '#value' => 'total'
            );
            $form['amount'] = array(
                '#id' => 'grandTotal',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 50,
                '#value' => isset($grandtotal) ? number_format($grandtotal, $this->rounding) : 0,
                '#attributes' => array('placeholder' => $this->t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
            );
            $form['receipt'] = array(
                '#type' => 'item',
                '#markup' => "<div id='convertedValue' class='badge'>" . $converted . "</div>",
            );
            $form['delete'] = array(
                '#type' => 'item',
            );

            //built rows for table total
            $form['items']['itemTable'][$n] = array(
                'account' => &$form['account'],
                'description' => &$form['description'],
                'amount' => &$form['amount'],
                'receipt' => &$form['receipt'],
                'delete' => &$form['delete']
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['account']),
                    array('data' => &$form['description']),
                    array('data' => &$form['amount']),
                    array('data' => &$form['receipt']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n)
            );

            unset($form['account']);
            unset($form['description']);
            unset($form['amount']);
            unset($form['receipt']);
            unset($form['delete']);

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                    '#attributes' => array('class' => array('button--remove')),
                );
            }
        }

//
        // Attachments
//
        $form['attach'] = array(
            '#type' => 'details',
            '#title' => $this->t('Attachments'),
            '#open' => true,
        );
        $form['attach']['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#prefix' => '<div class="container-inline">',
        );

        $form['attach']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'button',
            '#value' => $this->t('Attach'),
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'uploadFile'),
                'wrapper' => 'new_attachments',
                'effect' => 'fade',
                'method' => 'append',
            ),
        );

        $form['attach']['attach_new'] = array(
            '#type' => 'container',
            '#attributes' => array(
                'id' => 'attachments',
                'class' => 'table'
            ),
        );

        $form['attach']['attach_error'] = array(
            '#type' => 'container',
            '#attributes' => array(
                'id' => 'error',
            ),
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
            '#attributes' => array('class' => array('button--record')),
        );

        if ($clone == true) {
            //do not display current cloned memo attachements
            $form['#attached'] = array(
                'drupalSettings' => array('id' => 0, 'serial' => $tempSerial, 'currencies' => CurrencyData::currencyRates(), 'baseCurrency' => $baseCurrency),
                'library' => array('ek_finance/ek_finance.memo_form'),
            );
        } else {
            $form['#attached'] = array(
                'drupalSettings' => array('id' => $id, 'serial' => $tempSerial, 'currencies' => CurrencyData::currencyRates(), 'baseCurrency' => $baseCurrency),
                'library' => array('ek_finance/ek_finance.memo_form'),
            );
        }

        return $form;
    }

//

    /**
     * Callback to Add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {
        if (!$form_state->get('num_items')) {
            $form_state->set('num_items', 1);
        } else {
            $c = $form_state->get('num_items') + 1;
            $form_state->set('num_items', $c);
        }

        $chart = $this->settings->get('chart');
        if ($form_state->getValue('category') < 5) {
            $form_state->set('AidOptions', AidList::listaid($form_state->getValue('entity'), array($chart['expenses'], $chart['other_expenses']), 1));
        } else {
            $form_state->set('AidOptions', AidList::listaid($form_state->getValue('entity_to'), array($chart['expenses'], $chart['other_expenses']), 1));
        }

        $form_state->setRebuild();
    }

    /**
     * Callback to Remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) {
        $c = $form_state->get('num_items') - 1;
        $form_state->set('num_items', $c);
        $form_state->setRebuild();
    }

    /**
     * Callback for the ajax upload file
     *
     */
    public function uploadFile(array &$form, FormStateInterface $form_state) {

        //upload
        $extensions = 'png jpg jpeg';
        $max_bytes = floatval(\Drupal::VERSION) < 8.7
            ? file_upload_max_size() : Environment::getUploadMaxSize();
        $max_filesize = Bytes::toInt($max_bytes);
        $validators = array('file_validate_extensions' => [$extensions], 'file_validate_size' => [$max_filesize]);
        $file = file_save_upload("upload_doc", $validators, false, 0);

        if ($file) {
            $dir = "private://finance/memos";
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $dest = $dir . '/' . $file->getFilename();
            $filename = \Drupal::service('file_system')->copy($file->getFileUri(), $dest);

            $fields = array(
                'serial' => $form_state->getValue('tempSerial'),
                'uri' => $filename,
                'doc_date' => time(),
            );
            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_expenses_memo_documents')->fields($fields)->execute();

            $response = new AjaxResponse();
            if ($insert) {
                return $response->addCommand(new HtmlCommand('#error', ""));
            } else {
                $msg = "<div aria-label='Error message' class='messages messages--error'>"
                        . $this->t('Error') . "</div>";
                return $response->addCommand(new HtmlCommand('#error', $msg));
            }
        } else {
            $m = \Drupal::messenger()->messagesByType('error');
            $e = '';
            if(!empty($m)){
                foreach ($m as $k){
                    $e .= "<p>". (string) $k . "</p>";
                }
                \Drupal::messenger()->deleteByType('error');
            }
            
            $size = round($max_filesize / 1000000, 0);
            $msg = "<div aria-label='Error message' class='messages messages--error'>"
                    . $this->t('Allowed extensions') . ": " . 'png jpg jpeg'
                    . ', ' . $this->t('maximum size') . ": " . $size . 'Mb.'
                    . $e
                    . "</div>";
            $response = new AjaxResponse();
            return $response->addCommand(new HtmlCommand('#error', $msg));
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        //input used to update values set by user
        //$input = $form_state->getUserInput();
        // validate authorizer
        if ($form_state->getValue('user')) {
            $query = Database::getConnection()
                    ->select('users_field_data', 'users');
            $query->fields('users', ['uid']);
            $query->condition('name', $form_state->getValue('user'));
            $uid = $query->execute()->fetchField();

            if (!$uid || ($uid == \Drupal::currentUser()->id() && $uid != $this->authorizer)) {
                $form_state->setErrorByName("user", $this->t('Authorizer is not valid or unknowned'));
            } else {
                //save data for submission
                $form_state->setValue('user_uid', $uid);
            }
        }
        $triggering_element = $form_state->getTriggeringElement();
        //enforce data input

        if ($triggering_element['#id'] != 'edit-add' && $form_state->getValue('new_memo') == '1' &&
                !$form_state->get('num_items')) {
            $form['options']['#open'] = false;
            $form_state->setErrorByName("add", $this->t('No data'));
        }

        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                /**/
                if ($row['description'] != 'total') {
                    if (!is_numeric(str_replace(',', '', $row["amount"]))) {
                        $form['options']['#open'] = false;
                        $form_state->setErrorByName("itemTable][$key][amount", $this->t('there is no value for item @n', array('@n' => $key)));
                    }

                    // validate account
                    // @TODO
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('new_memo') == 1 || $form_state->getValue('new_memo') == 2) {
            //create new serial No
            $iid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_expenses_memo}")
                    ->fetchField();
            $iid++;

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'co');
            $query->fields('co', ['short']);
            $query->condition('id', $form_state->getValue('entity_to'));
            $short = $query->execute()->fetchField();

            $date = substr($form_state->getValue('date'), 2, 5);
            $serial = ucwords(str_replace('-', '', $short)) . "-EM-" . $date . "-" . $iid;
        } else {
            //edit
            $edit = true;
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_expenses_memo_list')->condition('serial', $serial)
                    ->execute();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'memo');
            $query->fields('memo', ['id']);
            $query->condition('serial', $serial);
            $iid = $query->execute()->fetchField();
        }

        $currencyRate = CurrencyData::rate($form_state->getValue('currency'));

        // Items

        $line = 0;
        $total = 0;
        $rows = $form_state->getValue('itemTable');
        foreach ($rows as $key => $row) {
            if ($row["delete"] != 1 && $row['description'] != 'total') {
                $item = Xss::filter($row['description']);
                $amount = str_replace(',', '', $row['amount']);
                $linebase = (round($amount / $currencyRate, $this->rounding));
                $total = $total + $amount;

                if ($row['account'] == null) {
                    $aid = 0;
                } else {
                    $aid = $row['account'];
                }

                $fields = array('serial' => $serial,
                    'aid' => $aid,
                    'description' => $item,
                    'amount' => $amount,
                    'value_base' => $linebase,
                    'receipt' => Xss::filter($row['receipt']),
                );

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_expenses_memo_list')
                        ->fields($fields)
                        ->execute();
            }//if not delete
        }//for
//main

        if ($form_state->getValue('pcode') == '') {
            $pcode = 'n/a';
        } else {
            $pcode = $form_state->getValue('pcode');
        }

        if ($form_state->getValue('category') == 5 && $this->settings->get('authorizeMemo') == 1) {
            $uid = $form_state->getValue('user_uid');

            if (!$form_state->getValue('action')) {
                $auth = '1|' . $uid;
            } else {
                switch ($form_state->getValue('action')) {
                    case 'read':
                        $auth = Database::getConnection('external_db', 'external_db')
                                ->query('SELECT auth from {ek_expenses_memo} where serial=:s', array(':s' => $serial))
                                ->fetchField();
                        break;

                    case 1:
                    case 2:
                    case 3:
                        $auth = $form_state->getValue('action') . '|' . $uid;
                        break;
                }
            }
        } else {
            $auth = '0|0';
        }


        $fields1 = array(
            'serial' => $serial,
            'category' => $form_state->getValue('category'),
            'entity' => $form_state->getValue('entity'),
            'entity_to' => $form_state->getValue('entity_to'),
            'client' => $form_state->getValue('client'),
            'pcode' => $pcode,
            'mission' => Xss::filter($form_state->getValue('mission')),
            'budget' => $form_state->getValue('budget'),
            'refund' => $form_state->getValue('refund'),
            'invoice' => $form_state->getValue('invoice'),
            'date' => $form_state->getValue('date'),
            'status' => '0',
            'value' => $total,
            'currency' => $form_state->getValue('currency'),
            'value_base' => round($total / $currencyRate, $this->rounding),
            'amount_paid' => 0,
            'amount_paid_base' => 0,
            'comment' => Xss::filter($form_state->getValue('comment')),
            'reconcile' => 0,
            'post' => 0,
            'auth' => $auth
        );

        if ($form_state->getValue('new_memo') && ($form_state->getValue('new_memo') == 1 || $form_state->getValue('new_memo') == 2)) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_expenses_memo')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_expenses_memo')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $iid;
        }



        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t('The memo is recorded'));
        }

        // update the documents table
        Database::getConnection('external_db', 'external_db')->update('ek_expenses_memo_documents')
                ->fields(array('serial' => $serial))
                ->condition('serial', $form_state->getValue('tempSerial'))
                ->execute();
        $params = [];
        if ($form_state->getValue('category') == 5 && $this->settings->get('authorizeMemo') == 1) {
            // send a notification
            $user_memo = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            $authorizer_mail = \Drupal\user\Entity\User::load($uid);
            $entity_mail = \Drupal\user\Entity\User::load($form_state->getValue('entity'));
            $action = [1 => $this->t('pending approval'), 2 => $this->t('authorized'), 3 => $this->t('rejected')];
            $params['subject'] = $this->t("Authorization notification") . ' - ' . $action[$form_state->getValue('action')];
            $params['options']['link'] = \Drupal::request()->getSchemeAndHttpHost() . Url::fromRoute('ek_finance_manage_personal_memo', ['id' => $reference])->toString();
            $params['options']['url'] = "<a href='" . $params['options']['link'] . "'>" . $serial . "</a>";
            $params['options']['user'] = $user_memo->getAccountName();
            $params['options']['serial'] = $serial;
            $body = '';
            $body .= "<p>" . $this->t('Memo ref. @p', ['@p' => $serial]) . "</p>";
            $body .= "<p>" . $this->t('Status') . ': ' . $action[$form_state->getValue('action')] . "</p>";
            $error = [];
            if (\Drupal::currentUser()->id() == $form_state->getValue('entity')) {
                //current user is accessing his/her own claim
                $body .= "<p>" . $this->t('Authorization action is required. Thank you.') . "</p>";
                $params['body'] = $body;
                if ($authorizer_mail) {
                    $target_langcode = $authorizer_mail->getPreferredLangcode();
                } else {
                    $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                }
                if ($authorizer_mail) {
                    $send = \Drupal::service('plugin.manager.mail')->mail(
                            'ek_finance', 'key_memo_note', $authorizer_mail->getEmail(), $target_langcode, $params, $user_memo->getEmail(), true
                    );

                    if ($send['result'] == false) {
                        $error[] = $authorizer_mail->getEmail();
                    }
                }
            } elseif (\Drupal::currentUser()->id() == $uid) {
                //authorizer editing

                $body .= "<p>" . $this->t('Authorization has been reviewed. Thank you.') . "</p>";
                $params['body'] = $body;
                if ($entity_mail) {
                    $target_langcode = $entity_mail->getPreferredLangcode();
                } else {
                    $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                }
                if ($entity_mail) {
                    $send = \Drupal::service('plugin.manager.mail')->mail(
                            'ek_finance', 'key_memo_note', $entity_mail->getEmail(), $target_langcode, $params, $user_memo->getEmail(), true
                    );

                    if ($send['result'] == false) {
                        $error[] = $user_memo->getEmail();
                    }
                }
            }

            if (!empty($error)) {
                $errors = implode(',', $error);
                \Drupal::messenger()->addError(t('Error sending notification to :t', [':t' => $errors]));
            } else {
                \Drupal::messenger()->addStatus(t('Notification message sent'));
            }
        } else {
            // notify for new memo
            if ($form_state->getValue('category') < 5) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_company', 'c');
                $query->fields('c', ['name', 'email', 'contact']);
                $query->condition('id', $form_state->getValue('entity'), '=');
                $entity = $query->execute()->fetchObject();
                $entity_mail = $entity->email;
                $entity_name = $entity->name;
            } else {
                $query = \Drupal\user\Entity\User::load($form_state->getValue('entity'));
                if ($query) {
                    $entity_mail = $query->getEmail();
                    $entity_name = $query->getAccountName();
                }
            }

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c');
            $query->fields('c', ['name', 'email', 'contact']);
            $query->condition('id', $form_state->getValue('entity_to'), '=');
            $entity_to = $query->execute()->fetchObject();

            if (isset($insert) && isset($entity_mail) && isset($entity_to->email)) {
                
                $params['subject'] = ($edit == false) ? $this->t("New memo") . ': ' . $serial : $this->t("Edited memo") . ': ' . $serial;
                $link = Url::fromRoute('ek_finance_manage_print_html', ['id' => $reference])->toString();
                $url = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $link]])->toString();
                $params['options']['link'] = $url;
                $params['options']['serial'] = $serial;
                $params['options']['url'] = "<a title='" . $serial . "' href='" . $params['options']['link'] . "'>" . $this->t('Open') . "</a>";
                $params['options']['user'] = $entity_name;
                $params['body'] = "<p>" . $this->t('Memo ref. @p', ['@p' => $serial]) . "." . $this->t('Issued to') . ": " . $entity_to->name . "</p>";
                //send notification email to central email queue;
                $data['module'] = 'ek_finance';
                $data['key'] = 'key_memo_note';
                $data['email'] = $entity_to->email;
                $data['subject'] = $params['subject'];
                $data['message'] = '';
                $data['params'] = $params;
                $data['lang'] = \Drupal::languageManager()->getDefaultLanguage()->getId();
                $queue = \Drupal::queue('ek_email_queue');
                $queue->createQueue();
                $queue->createItem($data);
                $data['subject'] = ($edit == false) ? $this->t("New memo") . ': ' . $serial . " (" . $this->t('copy') . ")" : $this->t("Edited memo") . ': ' . $serial . " (" . $this->t('copy') . ")";
                $data['email'] = $entity_mail;
                $queue->createItem($data);
            }
        }

        \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);

        if ($form_state->getValue('category') < 5) {
            $form_state->setRedirect('ek_finance_manage_list_memo_internal');
        } else {
            $form_state->setRedirect('ek_finance_manage_list_memo_personal');
        }
    }

}
