<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_projects\ProjectData;

/**
 * Controller routines for ek module routes.
 */
class MemoExpensesController extends ControllerBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a MemoExpensesController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service 
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  form to create internal memo
     * 
     *  @param int $id
     *  memo id
     * 
     *  @return Object
     *  form
     *
     */
    public function createinternalmemo(Request $request, $id) {

        //the attachment are uploded before the main memo form is submitted
        //therefore, a temporary serial ref. is attributed
        //once the main form is submitted, the temporary serial is updated with the permanent serial ref.
        //a cleanup of temporary file must be done for file uploaded but main form not submitted  

        $query = "SELECT id,uri,doc_date FROM {ek_expenses_memo_documents} WHERE serial like :s";
        $list = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => 'temp%'));

        while ($l = $list->fetchObject()) {

            if ((time() - $l->doc_date) > 172800) {
                unset($l->uri);
                Database::getConnection('external_db', 'external_db')->delete('ek_expenses_memo_documents')
                        ->condition('id', $l->id)
                        ->execute();
            }
        }

        // filter edition access 
        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $coid = Database::getConnection('external_db', 'external_db')
                ->query('SELECT entity FROM {ek_expenses_memo} WHERE id=:id AND category<:c ', array(':id' => $id, ':c' => 5))
                ->fetchField();
        if (\Drupal::currentUser()->hasPermission('admin_memos') || in_array($coid, $access)) {

            $tempSerial = 'temp' . mt_rand();
            $build['memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewMemo', $id, 'internal', $tempSerial);
        } elseif ($id == NULL) {
            $build['memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewMemo', $id, 'personal', $tempSerial);
        } else {
            throw new AccessDeniedHttpException();
            $build['content'] = t('You are not authorized to edit this memo');
        }

        return $build;
    }

    /**
     *  form to create personal memo
     * 
     *  @param int $id
     *  memo id
     * 
     *  @return Object
     *  form
     *
     */
    public function createpersonalmemo($id) {

        $query = "SELECT id,uri,doc_date FROM {ek_expenses_memo_documents} WHERE serial like :s";
        $list = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':s' => 'temp%'));

        while ($l = $list->fetchObject()) {

            if ((time() - $l->doc_date) > 172800) {
                unset($l->uri);
                Database::getConnection('external_db', 'external_db')->delete('ek_expenses_memo_documents')
                        ->condition('id', $l->id)
                        ->execute();
            }
        }
        $tempSerial = 'temp' . mt_rand();

        // filter edition access 
        $uid = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT entity FROM {ek_expenses_memo} WHERE id=:id AND category=:c ', array(':id' => $id, ':c' => 5))
                        ->fetchField();

        if (\Drupal::currentUser()->hasPermission('admin_memos') || $uid == \Drupal::currentUser()->id()) {

            $build['memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewMemo', $id, 'personal', $tempSerial);
        } elseif ($id == NULL) {
            $build['memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\NewMemo', $id, 'personal', $tempSerial);
        } else {
            throw new AccessDeniedHttpException();
            $build['content'] = t('You are not authorized to edit this memo');
        }

        return $build;
    }

    /**
     *  Attach image file to any memo
     *  @param int $id
     *  memo id
     * 
     *  @return Object
     *  form
     */
    public function attachmemo($id) {

// filter edition access 
        $m1 = FALSE;
        $m2 = FALSE;

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $coid = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT entity FROM {ek_expenses_memo} WHERE id=:id AND category<:c ', array(':id' => $id, ':c' => 5))
                        ->fetchField();
        if (\Drupal::currentUser()->hasPermission('admin_memos') || in_array($coid, $access)) {
            $m1 = TRUE;
        }

        $uid = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT entity FROM {ek_expenses_memo} WHERE id=:id AND category=:c ', array(':id' => $id, ':c' => 5))->fetchField();

        if (\Drupal::currentUser()->hasPermission('admin_memos') || $uid == \Drupal::currentUser()->id()) {
            $m2 = TRUE;
        }


        if ($m1 || $m2) {
            $tempSerial = 'temp' . mt_rand();
            $build['memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\AttachFileMemo', $id, $tempSerial);
        } else {
            throw new AccessDeniedHttpException();
            $build['content'] = t('You are not authorized to edit this memo');
        }

        return $build;
    }

    /**
     *  build a list of current internal memos filtered
     * 
     *  @return array 
     *  rendered html
     */
    public function listmemoInternal(Request $request) {


        $build['filter_imemos'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterMemo', 'internal');

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $header = array(
            'company' => array(
                'data' => $this->t('Issuer / payor'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'date',
            ),
            'value' => array(
                'data' => $this->t('Amount'),
                'field' => 'value',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'basecurrency' => array(
                'data' => $this->t('in base currency') . " " . $baseCurrency,
                'field' => 'basecurrency',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'status' => array(
                'data' => $this->t('Status'),
            ),
            'attach' => array(
                'data' => $this->t('Attachments'),
            ),
            'operations' => $this->t('Operations'),
        );


        /*
         * Table - query data
         */

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);


        if (isset($_SESSION['memfilter']['keyword']) && $_SESSION['memfilter']['keyword'] != '' && $_SESSION['memfilter']['keyword'] <> '%') {

            $keyword1 = '%' . $_SESSION['memfilter']['keyword'];


            $query = "SELECT * from {ek_expenses_memo}  
                WHERE (FIND_IN_SET (entity, :coid ) or FIND_IN_SET (entity_to, :coid ) OR auth like :a) 
                AND category < :c AND serial like :s
                ";
            $a = array(
                ':s' => $keyword1,
                ':coid' => $company,
                ':c' => 5,
                ':a' => '%|' . \Drupal::currentUser()->id(), //used for authorization by user
            );
        } else {
            //not keyword

            $query = "SELECT * from {ek_expenses_memo}  
          WHERE (FIND_IN_SET (entity, :cy ) or FIND_IN_SET (entity_to, :cy ) OR auth like :a) 
          AND category < :c 
          AND date >= :d1
          AND date <= :d2
          AND entity like :coid
          AND entity_to like :coid2
          AND pcode like :p
          AND status like :s
          ORDER by :order :sort 
          ";

            $order = $request->get('order') ? $request->get('order') : 'id';
            $sort = $request->get('sort') ? $request->get('sort') : 'ASC';

            if (isset($_SESSION['memfilter']['filter']) && $_SESSION['memfilter']['filter'] == 1) {

                if ($_SESSION['memfilter']['pcode'] == 'Any')
                    $_SESSION['memfilter']['pcode'] = '%';

                $a = array(
                    ':cy' => $company,
                    ':a' => '%|' . \Drupal::currentUser()->id(), //used for authorization by user
                    ':coid' => $_SESSION['memfilter']['coid'],
                    ':coid2' => $_SESSION['memfilter']['coid2'],
                    ':p' => $_SESSION['memfilter']['pcode'],
                    ':s' => $_SESSION['memfilter']['status'],
                    ':d1' => $_SESSION['memfilter']['from'],
                    ':d2' => $_SESSION['memfilter']['to'],
                    ':c' => 5,
                    ':sort' => $sort,
                    ':order' => $order
                );
            } else {

                $a = array(':cy' => $company,
                    ':a' => '%|' . \Drupal::currentUser()->id(), //used for authorization by user
                    ':coid' => '%',
                    ':coid2' => '%',
                    ':p' => '%',
                    ':s' => '%',
                    ':d1' => date('Y-m') . '-01',
                    ':d2' => date('Y-m-d'),
                    ':c' => 5,
                    ':sort' => $sort,
                    ':order' => $order
                );
            }
        }


        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
        $total = 0;
        $row = 0;
        $status = array('0' => t('not paid'), '1' => t('partial paid'), '2' => t('paid'),);

        while ($r = $data->fetchObject()) {
            $links = array();
            $row++;

            $query = "SELECT name from {ek_company} WHERE id=:id";
            $entity = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r->entity))->fetchField();
            $entity_to = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r->entity_to))->fetchField();
            $ref = '<a href="' . Url::fromRoute('ek_finance_manage_print_html', ['id' => $r->id])->toString() . '">' . $r->serial . '</a>';
            $query = "SELECT count(id) FROM {ek_expenses_memo_documents} WHERE serial=:s";
            $attach = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $r->serial))->fetchField();

            if ($r->pcode != 'not project related' && $r->pcode != '' && $r->pcode != 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $pcode = str_replace('/', '-', $r->pcode);
                    $ref .= '<br/>' . ProjectData::geturl($pcode, 0, 0, 1);
                }
            }

            if ($r->status == '0') {
                $dot = "<div class='reddot right'></div>";
            } elseif ($r->status == '1') {
                $dot = "<div class='orangedot right'></div>";
            } else {
                $dot = "<div class='greendot right'></div>";
            }

            if ($attach > 0) {
                $url = Url::fromRoute('ek_finance_manage_modal_memo', ['id' => $r->id])->toString();
                $docs = t('<a href="@url" class="@c" >attachments</a>', array('@url' => $url, '@c' => 'use-ajax blue'));
            } else {
                $docs = '';
            }

            $options[$row] = array(
                'company' => ['data' => ['#markup' => $entity . ' /<br>' . $entity_to]],
                'reference' => ['data' => ['#markup' => $ref], 'title' => ['#markup' => $r->mission]],
                'date' => $r->date,
                'value' => number_format($r->value, 2) . " " . $r->currency,
                'basecurrency' => number_format($r->value_base, 2) . " " . $baseCurrency,
                'status' => ['data' => ['#markup' => $status[$r->status] . $dot]],
                'attach' => ['data' => ['#markup' => $docs]],
            );

            $total = $total + $r->value_base;

            if ($r->status == '0') {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_finance_manage_internal_memo', ['id' => $r->id]),
                );

                $links['upload'] = array(
                    'title' => $this->t('Add attachment'),
                    'url' => Url::fromRoute('ek_finance_manage_memo_attach', ['id' => $r->id]),
                );

                $links['del'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_finance_manage_delete_memo', ['id' => $r->id]),
                );
            }

            if (($r->post == 0 || $r->post == 1) && $r->status < '2') {
                $links['pay'] = array(
                    'title' => $this->t('Pay'),
                    'url' => Url::fromRoute('ek_finance_manage_pay_memo', ['id' => $r->id]),
                );
            }

            if ($r->post == 0 && $r->status == '2') {


                /* this is done a psyment stage
                 * todo : remove route

                  $links['pay'] = array(
                  'title' => $this->t('Post expense'),
                  'url' => Url::fromRoute('ek_finance_manage_record_memo', ['id' => $r->id] ),
                  );
                 */
            }

            if ($r->post == 1 && $r->status == '2') {
                $links['pay'] = array(
                    'title' => $this->t('Receive'),
                    'url' => Url::fromRoute('ek_finance_manage_receive_memo', ['id' => $r->id]),
                );
            }

            $links['print'] = array(
                'title' => $this->t('Print'),
                'url' => Url::fromRoute('ek_finance_manage_print_memo', ['id' => $r->id]),
            );

            $options[$row]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }// while




        $options[$row + 1] = array(
            'company' => t('Total'),
            'reference' => '',
            'date' => '',
            'value' => '',
            'basecurrency' => array('data' => ['#markup' => number_format($total, 2) . " " . $baseCurrency]),
            'status' => '',
            'operations' => '',
        );



        $build['memos_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'memos_table'),
            '#empty' => $this->t('No memo available.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.dialog'),
            ),
        );




        return $build;
    }

    /**
     *  build list of personal claim
     * 
     *  @return array 
     *  rendered html
     */
    public function listmemoPersonal(Request $request) {

        $build['filter_imemos'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterMemo', 'personal');

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $header = array(
            'company' => array(
                'data' => $this->t('Issuer / payor'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'date',
            ),
            'value' => array(
                'data' => $this->t('Amount'),
                'field' => 'value',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'basecurrency' => array(
                'data' => $this->t('in base currency') . " " . $baseCurrency,
                'field' => 'basecurrency',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'status' => array(
                'data' => $this->t('Status'),
            ),
            'attach' => array(
                'data' => $this->t('Attachments'),
            ),
            'autho' => array(
                'data' => $this->t('Authorization'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => $this->t('Operations'),
        );


        /*
         * Table - query data
         */

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);


        if (isset($_SESSION['memfilter']['keyword']) && $_SESSION['memfilter']['keyword'] != '' && $_SESSION['memfilter']['keyword'] <> '%') {

            $keyword1 = '%' . $_SESSION['memfilter']['keyword'];

            if (\Drupal::currentUser()->hasPermission('admin_memos')) {
                $query = "SELECT SQL_CACHE * from {ek_expenses_memo}  
                WHERE (entity =:e  OR FIND_IN_SET (entity_to, :coid )) 
                AND category = :c AND serial like :s";
            } else {
                $query = "SELECT SQL_CACHE * from {ek_expenses_memo}  
                WHERE (entity =:e  AND FIND_IN_SET (entity_to, :coid )) 
                AND category = :c AND serial like :s";
            }

            $a = array(
                ':e' => \Drupal::currentUser()->id(),
                ':s' => $keyword1,
                ':coid' => $company,
                ':c' => 5,
            );
        } else {
            //not keyword

            if (\Drupal::currentUser()->hasPermission('admin_memos')) {
                $query = "SELECT * from {ek_expenses_memo}  
          WHERE (entity =:e OR FIND_IN_SET (entity_to, :cy )) 
          AND category = :c 
          AND date >= :d1
          AND date <= :d2
          AND entity like :coid
          AND entity_to like :coid2
          AND pcode like :p
          AND status like :s
          ORDER by :order :sort 
          ";
            } else {
                $query = "SELECT * from {ek_expenses_memo}  
          WHERE (entity =:e AND FIND_IN_SET (entity_to, :cy ) ) 
          AND category = :c 
          AND date >= :d1
          AND date <= :d2
          AND entity like :coid
          AND entity_to like :coid2
          AND pcode like :p
          AND status like :s
          ORDER by :order :sort 
          ";
            }

            $order = $request->get('order') ? $request->get('order') : 'id';
            $sort = $request->get('sort') ? $request->get('sort') : 'ASC';

            if (isset($_SESSION['memfilter']['filter']) && $_SESSION['memfilter']['filter'] == 1) {

                if ($_SESSION['memfilter']['pcode'] == 'Any')
                    $_SESSION['memfilter']['pcode'] = '%';

                $a = array(':e' => \Drupal::currentUser()->id(),
                    ':cy' => $company,
                    ':coid' => $_SESSION['memfilter']['coid'],
                    ':coid2' => $_SESSION['memfilter']['coid2'],
                    ':p' => $_SESSION['memfilter']['pcode'],
                    ':s' => $_SESSION['memfilter']['status'],
                    ':d1' => $_SESSION['memfilter']['from'],
                    ':d2' => $_SESSION['memfilter']['to'],
                    ':c' => 5,
                    ':sort' => $sort,
                    ':order' => $order
                );
            } else {

                $a = array(':e' => \Drupal::currentUser()->id(),
                    ':cy' => $company,
                    ':coid' => '%',
                    ':coid2' => '%',
                    ':p' => '%',
                    ':s' => '%',
                    ':d1' => date('Y-m') . '-01',
                    ':d2' => date('Y-m-d'),
                    ':c' => 5,
                    ':sort' => $sort,
                    ':order' => $order
                );
            }
        }


        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
        $total = 0;
        $row = 0;
        $status = array('0' => t('not paid'), '1' => t('partial paid'), '2' => t('paid'));

        while ($r = $data->fetchObject()) {
            $links = array();
            $row++;
            $query = "SELECT name from {users_field_data} WHERE uid=:id";
            $entity = db_query($query, array(':id' => $r->entity))->fetchField();
            $query = "SELECT name from {ek_company} WHERE id=:id";
            $entity_to = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r->entity_to))->fetchField();
            $query = "SELECT count(id) FROM {ek_expenses_memo_documents} WHERE serial=:s";
            $attach = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $r->serial))->fetchField();

            $ref = '<a href="' . Url::fromRoute('ek_finance_manage_print_html', ['id' => $r->id])->toString() . '">' . $r->serial . '</a>';

            if ($r->pcode != 'not project related' && $r->pcode != '' && $r->pcode != 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $pcode = str_replace('/', '-', $r->pcode);
                    $ref .= '<br/>' . ProjectData::geturl($pcode, 0, 0, 1);
                }
            }

            if ($r->status == '0') {
                $dot = "<div class='reddot right'></div>";
            } elseif ($r->status == '1') {
                $dot = "<div class='orangedot right'></div>";
            } else {
                $dot = "<div class='greendot right'></div>";
            }
            if ($attach > 0) {
                $url = Url::fromRoute('ek_finance_manage_modal_memo', ['id' => $r->id])->toString();
                $docs = t('<a href="@url" class="@c" >attachments</a>', array('@url' => $url, '@c' => 'use-ajax blue'));
            } else {
                $docs = '';
            }

            if ($r->auth == '0|0') {

                $autho = t('n/a');
            } else {
                $auth_status = array(0 => t('not required'), 1 => t('pending'), 2 => t('authorized'), 3 => t('rejected'));
                $query = "SELECT name from {users_field_data} WHERE uid=:u";
                $auth = explode('|', $r->auth);
                $user_name = db_query($query, array(':u' => $auth[1]))->fetchField();
                $autho = $user_name . '<br/>' . $auth_status[$auth[0]];
            }

            $options[$row] = array(
                'company' => ['data' => ['#markup' => $entity . ' /<br>' . $entity_to]],
                'reference' => ['data' => ['#markup' => $ref], 'title' => ['#markup' => $r->mission]],
                'date' => $r->date,
                'value' => number_format($r->value, 2) . " " . $r->currency,
                'basecurrency' => number_format($r->value_base, 2) . " " . $baseCurrency,
                'status' => ['data' => ['#markup' => $status[$r->status] . $dot]],
                'attach' => ['data' => ['#markup' => $docs]],
                'autho' => ['data' => ['#markup' => $autho]],
            );

            $total = $total + $r->value_base;

            if ($r->status == 0 && $auth[0] < 2) {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_finance_manage_personal_memo', ['id' => $r->id]),
                );
            }

            if ($r->post == 0 && $r->status < 2 && ($auth[0] == 0 || $auth[0] == 2)) {
                $links['pay'] = array(
                    'title' => $this->t('Pay'),
                    'url' => Url::fromRoute('ek_finance_manage_pay_memo', ['id' => $r->id]),
                );
            }
            if ($r->status == 0) {

                $links['del'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_finance_manage_delete_memo', ['id' => $r->id]),
                );

                $links['upload'] = array(
                    'title' => $this->t('Add attachment'),
                    'url' => Url::fromRoute('ek_finance_manage_memo_attach', ['id' => $r->id]),
                );
            }


            $links['print'] = array(
                'title' => $this->t('Print'),
                'url' => Url::fromRoute('ek_finance_manage_print_memo', ['id' => $r->id]),
            );

            $options[$row]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }// while



        $total = '<h4>' . number_format($total, 2) . " " . $baseCurrency . '</h4>';
        $options[$row + 1] = array(
            'company' => t('Total'),
            'reference' => '',
            'date' => '',
            'value' => '',
            'basecurrency' => array('data' => ['#markup' => $total]),
            'status' => '',
            'operations' => '',
        );



        $build['memos_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'memos_table'),
            '#empty' => $this->t('No memo available.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.dialog'),
            ),
        );




        return $build;
    }

    /**
     *  Record a memo payment by payor
     * 
     *  @param int $id
     *  memo id
     * 
     *  @return Object
     *  form
     *
     */
    public function paymemo(Request $request, $id = NULL) {

        $query = "SELECT entity_to FROM {ek_expenses_memo} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        $del = 1;
        $access = AccessCheck::GetCompanyByUser();

        if (!in_array($data->entity_to, $access)) {
            $del = 0;
        } elseif (\Drupal::currentUser()->hasPermission('pay_memos') == FALSE) {
            $del = 0;
        }

        if ($del == 1) {
            $build['pay'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\PayMemo', $id);
            return $build;
        } else {
            return array('#markup' => t('You cannot record payment for this memo.'));
        }
    }

    /**
     *  Record a memo receipt by payee
     * 
     *  @param int $id
     *  memo id
     * 
     *  @return Object
     *  form
     *
     */
    public function receivememo(Request $request, $id = NULL) {

        $query = "SELECT entity FROM {ek_expenses_memo} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        $del = 1;
        $access = AccessCheck::GetCompanyByUser();
        /**/
        if (!in_array($data->entity, $access)) {
            $del = 0;
        } elseif (\Drupal::currentUser()->hasPermission('receive_memos') == FALSE) {
            $del = 0;
        }

        if ($del == 1) {
            $build['pay'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\ReceiveMemo', $id);
            return $build;
        } else {
            return array('#markup' => t('You cannot record receipt for this memo.'));
        }
    }

    /**
     *  Page to print memo in Pdf and send by email
     *  @param int $id 
     *      id of the memo
     * 
     *  @return mixed
     */
    public function printmemo(Request $request, $id = NULL) {

        $format = 'pdf';
        $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterPrint', $id, 'expenses_memo', $format);

        if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {
            $id = explode('-', $_SESSION['printfilter']['for_id']);

            $param = serialize(
                    array(
                        $id[0], //id
                        $id[1], //source
                        $_SESSION['printfilter']['signature'],
                        $_SESSION['printfilter']['stamp'],
                        $_SESSION['printfilter']['template'],
                    )
            );

            $build['filter_mail'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $param);

            $path = $GLOBALS['base_url'] . "/finance/memo/print/pdf/" . $param;

            $iframe = Xss::filter("<iframe src ='" . $path . "' width='100%' height='1000px' id='view' name='view'></iframe>", ['iframe']);

            $build['iframe'] = $iframe;
        }

        return array(
            '#items' => $build,
            '#theme' => 'iframe',
        );
    }

    /**
     *  Print a range of memos per date and company
     * 
     *  @param string $category
     *  category of memo set to 'internal' (default)
     * 
     *  @return mixed
     *  rendered html
     *  
     *
     */
    public function printmemorange($category = 'internal') {
        $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterPrintRange', $category);
        if ($_SESSION['memrgfilter']['filter'] == 1) {


            $param = serialize(
                    array(
                        'memo_range',
                    )
            );

            $path = $GLOBALS['base_url'] . "/finance/memo/print/pdf/" . $param;

            $iframe = Xss::filter("<iframe src ='" . $path . "' width='100%' height='1000px' id='view' name='view'></iframe>", ['iframe']);
            $build['iframe'] = $iframe;
        }

        return array(
            '#items' => $build,
            '#theme' => 'iframe',
            '#attached' => array(
                'library' => array('ek_finance/ek_finance_css'),
            ),
        );
    }

    /**
     * a display of memo in html format
     *
     * @param array $param
     *  document id int, source string, signature bool,
     *   stamp bool, template string, mode string 
     * @retun mixed
     *  rendered html
     */    
    
    public function printmemopdf(Request $request, $param) {
        $markup = array();
        $format = 'pdf';
        include_once drupal_get_path('module', 'ek_finance') . '/manage_print_output.inc';
        return $markup;
    }

    /**     
     * display of memo in html format
     * @param INT $id 
     *  document id
     * 
     * @retun rendered Html
     *  
     *
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `serial`, `category`, `entity`, `entity_to`, `auth` FROM {ek_expenses_memo} "
                . "WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $companies = implode(',', $access);
        $flag = 0;

        //for internal memos
        if ($data->category < 5) {
            if (in_array($data->entity, $access) || in_array($data->entity_to, $access)) {
                $flag = 1;
                //user has access to companies
            }
            //for personal memos
        } else {

            $auth = explode("|", $data->auth);
            if ($data->entity == \Drupal::currentUser()->id() || \Drupal::currentUser()->hasPermission('admin_memos') || $auth[1] == \Drupal::currentUser()->id()) {
                $flag = 1;
                //user has access this personal data
            }
        }

        if ($flag == 1) {
            $format = 'html';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterPrint', $id, 'expenses_memo', $format);
            $document = '';

            if (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id) {

                $id = explode('-', $_SESSION['printfilter']['for_id']);
                $doc_id = $id[0];
                $param = serialize(
                        array(
                            $id[0], //id
                            $id[1], //source
                            $_SESSION['printfilter']['signature'],
                            $_SESSION['printfilter']['stamp'],
                            $_SESSION['printfilter']['template'],
                        )
                );

                include_once drupal_get_path('module', 'ek_finance') . '/manage_print_output.inc';

                if ($data->category < 5) {
                    $url = Url::fromRoute('ek_finance_manage_list_memo_internal')->toString();
                } else {
                    $url = Url::fromRoute('ek_finance_manage_list_memo_personal')->toString();
                }

                $build['button'] = [
                    '#markup' => "<a class='button button-action' href='"
                    . $url . "' >"
                    . t('List') . "</a>"
                    . "<a class='button button-action' href='"
                    . Url::fromRoute('ek_finance_manage_print_memo', ['id' => $doc_id], [])->toString() . "' >"
                    . t('Pdf') . "</a>"
                        ,
                ];

                $build['html_memo'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance_html_documents_css'),
                    ),
                ];
            }
            return array($build);
        } else {

            if ($data->category < 5) {
                $url = Url::fromRoute('ek_finance_manage_list_memo_internal')->toString();
            } else {
                $url = Url::fromRoute('ek_finance_manage_list_memo_personal')->toString();
            }
            $message = t('Access denied') . '<br/>' . t("<a href=\"@c\">List</a>", ['@c' => $url]);
            return [
                '#markup' => $message,
            ];
        }
    }

    /**
     * delete memo 
     * 
     * @param INT $id 
     *  document id
     * 
     * @return Object
     *  form
     */
    public function deletememo(Request $request, $id = NULL) {

        $build['delete_memo'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\DeleteMemo', $id);

        return $build;
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function modal($id) {
        return $this->dialog(TRUE, $id);
    }

    /**
     * Util to render dialog in ajax callback.
     *
     * @param bool $is_modal
     *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = FALSE, $id = NULL) {

        $query = "SELECT d.id,uri FROM {ek_expenses_memo_documents} d "
                . " INNER JOIN {ek_expenses_memo} m ON d.serial=m.serial WHERE m.id=:s";
        $attach = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $id));
        $markup = '<ul>';
        $content = array();

        while ($l = $attach->fetchObject()) {

            $parts = explode('/', $l->uri);
            $fname = array_pop($parts);
            $markup .= "<li><a href='" . file_create_url($l->uri) . "' target='_blank'>" . $fname . "</a></li>";
        }

        $markup .= '</ul>';

        $response = new AjaxResponse();
        $title = $this->t('Attachments');
        $content['#markup'] = $markup;
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $html));
        }
        return $response;
    }

    /**
     * function to display internal cross transactions between companies.
     *
     * @param int $id
     *   (optional) Null for total transactions, Int for company id
     *
     */
    public function transactions($id = NULL) {

        $form = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterTransactions');

        if ($_SESSION['transfilter']['filter'] == 1) {

            $companyList = AccessCheck::CompanyList(1);
            $companyAccess = AccessCheck::CompanyListByUid();
            $year = $_SESSION['transfilter']['year'];
            $settings = new FinanceSettings();


            if ($id == NULL) {
                //display global transactions summary
                $cross_tbl = array();
                $due = array();
                $sum = array();

                foreach ($companyAccess as $coid1 => $name1) {

                    foreach ($companyList as $coid2 => $name2) {

                        if ($coid1 <> $coid2) {

                            $k1 = '_' . $coid2 . '-' . $coid1;
                            $k2 = '_' . $coid1 . '-' . $coid2;
                            //calculate total credit between entities
                            $query = "SELECT sum(value_base) FROM {ek_expenses_memo} WHERE
                                    entity = :coid1 AND
                                    entity_to = :coid2 AND
                                    date like :y AND
                                    category < :c";
                            $a = [':coid1' => $coid1, ':coid2' => $coid2, ':y' => $year . '%', ':c' => 5];


                            $credit = Database::getConnection('external_db', 'external_db')
                                    ->query($query, $a)
                                    ->fetchField();

                            //calculate total debit between entities
                            $a = [':coid1' => $coid2, ':coid2' => $coid1, ':y' => $year . '%', ':c' => 5];
                            $debit = Database::getConnection('external_db', 'external_db')
                                    ->query($query, $a)
                                    ->fetchField();


                            $sum[$k1] = $credit;
                            $sum[$k2] = $debit;

                            //calculate not paid transactions credit between entities
                            $query = "SELECT sum(value_base) FROM {ek_expenses_memo} WHERE
                                    entity = :coid1 AND
                                    entity_to = :coid2 AND
                                    date like :y AND
                                    status = :s AND
                                    category < :c";
                            $a = [':coid1' => $coid1, ':coid2' => $coid2, ':y' => $year . '%', ':s' => 0, ':c' => 5];

                            $credit = Database::getConnection('external_db', 'external_db')
                                    ->query($query, $a)
                                    ->fetchField();

                            //calculate not paid transactions debit between entities
                            $a = [':coid1' => $coid2, ':coid2' => $coid1, ':y' => $year . '%', ':s' => 0, ':c' => 5];
                            $debit = Database::getConnection('external_db', 'external_db')
                                    ->query($query, $a)
                                    ->fetchField();

                            $due[$k1] = $credit - $debit;
                            $due[$k2] = $debit - $credit;
                        }
                    } // next supplier
                }


                return array(
                    '#theme' => 'ek_finance_memo_transactions',
                    '#title' => t('Summary of internal transactions for year @y', ['@y' => $year]),
                    '#form' => $form,
                    '#companies' => $companyList,
                    '#company_access' => $companyAccess,
                    '#sum' => $sum,
                    '#due' => $due,
                    '#baseCurrency' => $settings->get('baseCurrency'),
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance'),
                    ),
                );
            }//global
            else {
                //transaction per company 
                $sumCredit = [];
                $sumDebit = [];
                $transactions = array();
                foreach ($companyAccess as $coid2 => $name2) {

                    if ($id != $coid2) {
                        $query = "SELECT id,serial,value_base,pcode,mission,date FROM {ek_expenses_memo} WHERE
                        entity = :id AND
                        entity_to = :coid2 AND
                        date like :y AND
                        category < :c ORDER by date";

                        $c = [':id' => $id, ':coid2' => $coid2, ':y' => $year . '%', ':c' => 5];
                        $data1 = Database::getConnection('external_db', 'external_db')
                                ->query($query, $c);
                        $d = [':id' => $coid2, ':coid2' => $id, ':y' => $year . '%', ':c' => 5];
                        $data2 = Database::getConnection('external_db', 'external_db')
                                ->query($query, $d);


                        while ($d = $data1->fetchObject()) {
                            $transactions[$name2][] = [
                                'id' => $d->id,
                                'pcode' => $d->pcode,
                                'serial' => $d->serial,
                                'value_base' => $d->value_base,
                                'date' => $d->date,
                                'mission' => $d->mission,
                                'type' => 'credit'
                            ];
                            $sumCredit[$name2] += $d->value_base;
                        }

                        while ($d = $data2->fetchObject()) {
                            $transactions[$name2][] = [
                                'id' => $d->id,
                                'pcode' => $d->pcode,
                                'serial' => $d->serial,
                                'value_base' => $d->value_base,
                                'date' => $d->date,
                                'mission' => $d->mission,
                                'type' => 'debit',
                            ];
                            $sumDebit[$name2] += $d->value_base;
                        }
                    }
                }


                return array(
                    '#theme' => 'ek_finance_memo_transactions_bycoid',
                    '#title' => t('Internal transactions for year @y - @c', ['@y' => $year, '@c' => $companyList[$id]]),
                    '#form' => $form,
                    '#coid' => $id,
                    '#companies' => $companyAccess,
                    '#transactions' => $transactions,
                    '#sumCredit' => $sumCredit,
                    '#sumDebit' => $sumDebit,
                    '#baseCurrency' => $settings->get('baseCurrency'),
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance'),
                    ),
                );
            } //per coid
        }//apply filter
        else {
            return $form;
        }
    }

}

