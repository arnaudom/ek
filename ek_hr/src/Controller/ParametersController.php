<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_hr\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Controller routines for ek module routes.
 */
class ParametersController extends ControllerBase {
    /* The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */

    protected $moduleHandler;

    /**
     * The database service.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

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
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * Return list of employees with filter
     *
     */
    public function parameters(Request $request) {
        $build['filter_hr_list'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\FilterEmployeeList');

        $header = array(
            'id' => [
                'data' => $this->t('ID'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'sort' => 'asc',
                'field' => 'id',
                'id' => 'eid',
            ],
            'name' => [
                'data' => $this->t('Name'),
                'field' => 'name',
                'id' => 'ename',
            ],
            'status' => [
                'data' => $this->t('Status'),
                'id' => 'estatus',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ],
            'operations' => [
                'data' => $this->t('Operations'),
                'id' => 'operations',
            ]
        );

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce', 'w');

        $or = $query->orConditionGroup();
        $or->condition('company_id', $access, 'IN');

        if (isset($_SESSION['hrlfilter']['filter']) && $_SESSION['hrlfilter']['filter'] == 1) {
            $coid = $_SESSION['hrlfilter']['coid'];
            $status = $_SESSION['hrlfilter']['status'];

            $data = $query
                    ->fields('w')
                    ->condition($or)
                    ->condition('company_id', $access, 'IN')
                    ->condition('w.active', $status, '=')
                    ->condition('w.company_id', $coid, '=')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(30)->orderByHeader($header)
                    ->execute();


            while ($r = $data->fetchObject()) {
                if ($r->archive == 'yes') {
                    $archive = $this->t("(archived)");
                } else {
                    $archive = '';
                }
                $eid = ($r->custom_id != '') ? $r->custom_id : $r->id;
                $options[$r->id] = array(
                    'id' => ['data' => ['#markup' => "<span class='badge'>" . $eid . "</span>"]],
                    'name' => array('data' => $r->name, 'title' => $r->name, 'class' => ['tip'], 'id' => $r->id),
                    'status' => $r->active . ' ' . $archive,
                );

                $links = array();
                if ($r->administrator == '0' || in_array(\Drupal::currentUser()->id(), explode(',', $r->administrator))) {
                    $links['view'] = array(
                        'title' => $this->t('View'),
                        'url' => Url::fromRoute('ek_hr.employee.view', ['id' => $r->id]),
                        'route_name' => 'ek_hr.employee.view',
                    );
                    $links['edit'] = array(
                        'title' => $this->t('Edit'),
                        'url' => Url::fromRoute('ek_hr.employee.edit', ['id' => $r->id]),
                        'route_name' => 'ek_hr.employee.edit',
                    );
                }

                $options[$r->id]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => $links,
                );
            }//loop

            $param = serialize(['coid' => $coid, 'status' => $status]);
            $excel = Url::fromRoute('ek_hr.parameters-excel', array('param' => $param), array())->toString();
            $build['excel'] = array(
                '#markup' => "<a href='" . $excel . "' title='" . $this->t('Excel download') . "'><span class='ico excel green'></span></a>"
            );

            $build['hr_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'hr_table'),
                '#empty' => $this->t('No employee'),
                '#attached' => array(
                    'library' => array('ek_hr/ek_hr_css', 'ek_hr/ek_hr_help', 'ek_hr/ek_hr_tip', 'ek_admin/ek_admin_css'),
                ),
            );
            $build['pager'] = array(
                '#type' => 'pager',
            );
        }

        return $build;
    }

    /**
     * Extract list of employees with filter
     * Excel
     */
    public function extraList($param = null) {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);

            $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w');
            $or = $query->orConditionGroup();
            $or->condition('company_id', $access, 'IN');
            $data = $query
                    ->fields('w')
                    ->condition('company_id', $access, 'IN')
                    ->condition('w.active', $param['status'], '=')
                    ->condition('w.company_id', $param['coid'], '=')
                    ->execute();


            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_hr') . '/excel_employee_list.inc';
        }

        return ['#markup' => $markup];
    }

    /**
     * Return employee data
     *
     */
    public function employeeView(Request $request, $id) {
        $query = 'SELECT * FROM {ek_hr_workforce}  WHERE id=:id';
        $data['hr'] = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchAll();

        if ($data['hr'][0]->administrator == '0' || in_array(\Drupal::currentUser()->id(), explode(',', $data['hr'][0]->administrator))) {
            $query = 'SELECT name FROM {ek_company} WHERE id=:id';
            $data['hr'][0]->company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data['hr'][0]->company_id))
                    ->fetchField();

            $category = new HrSettings($data['hr'][0]->company_id);
            $origin = $category->HrCat[$data['hr'][0]->company_id];
            $data['hr'][0]->origin = $origin[$data['hr'][0]->origin];
            if ($data['hr'][0]->picture) {
                $data['hr'][0]->pictureUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($data['hr'][0]->picture);
                $data['hr'][0]->picture = "<img class='thumbnail' src='"
                        . \Drupal::service('file_url_generator')->generateAbsoluteString($data['hr'][0]->picture) . "'>";
            } else {
                $pic = '../../' . \Drupal::service('extension.path.resolver')->getPath('module', 'ek_hr') . '/art/default.jpeg';
                $data['hr'][0]->picture = "<img class='thumbnail' src='" . $pic . "'>";
                $data['hr'][0]->pictureUrl = $pic;
            }

            if ($data['hr'][0]->administrator != '0') {
                $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);
                $adm = explode(',', $data['hr'][0]->administrator);
                $list = [];
                foreach ($adm as $k => $v) {
                    $list[] = $users[$v];
                }
                $data['hr'][0]->administrators = implode(',', $list);
            } else {
                $data['hr'][0]->administrators = 0;
            }

            $query = "SELECT service_name FROM {ek_hr_service} "
                    . "INNER JOIN {ek_company} ON ek_company.id=ek_hr_service.coid "
                    . "WHERE ek_company.id=:id AND sid=:s order by service_name";
            $a = array(':id' => $data['hr'][0]->company_id, ':s' => $data['hr'][0]->service);
            $data['hr'][0]->service_name = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            $query = "SELECT description FROM {ek_hr_location} l "
                    . "INNER JOIN {ek_hr_workforce} w ON l.location=w.location "
                    . "WHERE w.id=:id";
            $a = array(':id' => $data['hr'][0]->id);
            $data['hr'][0]->location_description = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            //filter contract expiration
            $stamp = date('U');
            if ($data['hr'][0]->contract_expiration != null) {
                $delta = round((strtotime($data['hr'][0]->contract_expiration) - $stamp) / (24 * 60 * 60), 0);

                if ($delta <= 0) {
                    \Drupal::messenger()->addError(t('Contract is expired'));
                }
                if ($delta > 0 && $delta <= 31) {
                    \Drupal::messenger()->addWarning(t('Contract will expire in @d day(s)', ['@d' => $delta]));
                }
            }
            //filter birthday
            if ($data['hr'][0]->birth != null) {
                $next = date('Y') . '-' . date('m-d', strtotime($data['hr'][0]->birth));
                $delta = round((strtotime($next) - $stamp) / (24 * 60 * 60), 0);

                if ($delta <= 0 && $delta > -8) {
                    //TODO add flag if birthday is valid
                    //\Drupal::messenger()->addWarning(t('You missed employee birthday on @d', ['@d' => $next]));
                }
                if ($delta > 0 && ($delta < 16)) {
                    \Drupal::messenger()->addStatus(t('Employee birthday in @d day(s)', ['@d' => $delta]));
                }
            }

            return array(
                '#theme' => 'ek_hr_data',
                '#items' => $data,
                '#title' => $this->t('Employee data'),
                '#attached' => array(
                    'library' => array('ek_hr/ek_hr_data_css'),
                ),
                '#cache' => [
                    'tags' => ['employee_data_view'],
                ],
            );
        } else {
            $url = Url::fromRoute("ek_hr.parameters", [], [])->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => $this->t('<a href="@url" >Back</a>.', ['@url' => $url])];
            return $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0],
            ];
        }
    }

    /**
     * employee history data
     *
     */
    public function employeeHistory(Request $request, $id) {
        $query = 'SELECT * FROM {ek_hr_workforce}  WHERE id=:id';
        $data['hr'] = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchAll();

        if ($data['hr'][0]->administrator == '0' || in_array(\Drupal::currentUser()->id(), explode(',', $data['hr'][0]->administrator))) {
            $data['form_leave'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditLeave', $id);
            $start = date('Y', strtotime($data['hr'][0]->start));
            $year = date('Y');


            //LEAVES TAKEN
            $data['leave'] = array();

            for ($y = $start; $y <= $year; $y++) {
                $query = 'SELECT sum(tleave) as l, sum(mc_day) as m FROM {ek_hr_post_data} WHERE month like :month AND emp_id=:e';
                $a = array(':e' => $id, ':month' => $y . '%');
                $h = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

                $data['leave'][$y] = array($h->l, $h->m);
            }


            //CONTRIBUTIONS
            $data['contribution'] = array();

            for ($y = $start; $y <= $year; $y++) {
                $query = 'SELECT sum(epf_yee) as epf_yee , sum(epf_er) as epf_er, sum(socso_yee) as socso_yee, sum(socso_er) as socso_er, sum(with_yee) as with_yee, sum(with_yer) as with_yer, sum(incometax) as incometax FROM {ek_hr_post_data} WHERE month like :month AND emp_id=:e';
                $a = array(':e' => $id, ':month' => $y . '%');
                $h = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

                $data['contribution'][$y] = array($h->epf_yee, $h->epf_er, $h->socso_yee, $h->socso_er, $h->with_yee, $h->with_yer, $h->incometax);
            }


            //SALARIES
            $data['salary'] = array();

            $query = 'SELECT * FROM {ek_hr_post_data} WHERE emp_id=:e ORDER by id';
            $a = array(':e' => $id);
            $history = Database::getConnection('external_db', 'external_db')->query($query, $a);

            while ($h = $history->fetchObject()) {
                $variable = $h->n_ot_val + $h->r_day_val + $h->ph_day_val + $h->mc_day_val + $h->xr_hours_val;
                $allowance = $h->custom_aw1 + $h->custom_aw2 + $h->custom_aw3 + $h->custom_aw4 + $h->custom_aw5 + $h->custom_aw6 + $h->custom_aw7 + $h->custom_aw8 + $h->custom_aw9 + $h->custom_aw10 + $h->custom_aw11 + $h->custom_aw12 + $h->custom_aw13;
                $deductions = $h->less_hours_val + $h->custom_d2 + $h->custom_d3 + $h->custom_d4 + $h->custom_d5 + $h->custom_d6 + $h->custom_d7;
                $link = Url::fromRoute('ek_hr.employee.history_pay', ['id' => $h->id])->toString();
                $view = '<a href="' . $link . '">' . $this->t('view') . '</a>';
                $data['salary'][$h->month] = array(
                    $h->gross,
                    $h->nett,
                    $h->basic,
                    $variable,
                    $allowance,
                    $h->commission,
                    $deductions,
                    $view,
                );
            }



            return array(
                '#theme' => 'ek_hr_history',
                '#items' => $data,
                '#title' => $this->t('Employee history'),
                '#attached' => array(
                    'library' => array('ek_hr/ek_hr_data_css'),
                ),
            );
        } else {
            return array('#markup' => $this->t('Restricted access'));
        }
    }

    /**
     * employee payment history details
     *
     */
    public function employeeHistoryPay(Request $request, $id) {
        $data = array();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_post_data', 'p');
        $query->fields('p');
        $query->innerJoin('ek_hr_workforce', 'w', 'w.id=p.emp_id');
        $query->fields('w')
                ->condition('p.id', $id, '=');
        $data['salary'] = $query->execute()->fetchObject();

        $condition = $data['salary']->administrator == '0' || in_array(\Drupal::currentUser()->id(), explode(',', $data['salary']->administrator));
        if ($condition) {
            if ($invoke = $this->moduleHandler()->invokeAll('hr_history', [$data])) {
                $data = $invoke;
            }
            $link = Url::fromRoute('ek_hr.employee.history', ['id' => $data['salary']->emp_id])
                    ->toString();
            $data['back'] = '<a href="' . $link . '">' . $this->t('back') . '</a>';
            // get allowance aparameters for the coid
            $param = new HrSettings($data['salary']->company_id);
            $ad = $param->HrAd[$data['salary']->company_id];


            $c = $data['salary']->origin;

            $data['param'] = array(
                'LAF1' => $param->get('ad', 'LAF1-' . $c, 'description'),
                'LAF2' => $param->get('ad', 'LAF2-' . $c, 'description'),
                'LAF3' => $param->get('ad', 'LAF3-' . $c, 'description'),
                'LAF4' => $param->get('ad', 'LAF4-' . $c, 'description'),
                'LAF5' => $param->get('ad', 'LAF5-' . $c, 'description'),
                'LAF6' => $param->get('ad', 'LAF6-' . $c, 'description'),
                'LAF6_x' => $param->get('ad', 'LAF6-' . $c, 'value'),
                'custom_a_val' => array(
                    1 => $param->get('ad', 'LAC1-' . $c, 'description'),
                    2 => $param->get('ad', 'LAC2-' . $c, 'description'),
                    3 => $param->get('ad', 'LAC3-' . $c, 'description'),
                    4 => $param->get('ad', 'LAC4-' . $c, 'description'),
                    5 => $param->get('ad', 'LAC5-' . $c, 'description'),
                    6 => $param->get('ad', 'LAC6-' . $c, 'description'),
                    7 => $param->get('ad', 'LAC7-' . $c, 'description'),
                    8 => $param->get('ad', 'LAC8-' . $c, 'description'),
                    9 => $param->get('ad', 'LAC9-' . $c, 'description'),
                    10 => $param->get('ad', 'LAC10-' . $c, 'description'),
                    11 => $param->get('ad', 'LAC11-' . $c, 'description'),
                    12 => $param->get('ad', 'LAC12-' . $c, 'description'),
                    13 => $param->get('ad', 'LAC13-' . $c, 'description'),
                ),
                'custom_a_for' => array(
                    1 => $param->get('ad', 'LAC1-' . $c, 'formula'),
                    2 => $param->get('ad', 'LAC2-' . $c, 'formula'),
                    3 => $param->get('ad', 'LAC3-' . $c, 'formula'),
                    4 => $param->get('ad', 'LAC4-' . $c, 'formula'),
                    5 => $param->get('ad', 'LAC5-' . $c, 'formula'),
                    6 => $param->get('ad', 'LAC6-' . $c, 'formula'),
                    7 => $param->get('ad', 'LAC7-' . $c, 'formula'),
                    8 => $param->get('ad', 'LAC8-' . $c, 'formula'),
                    9 => $param->get('ad', 'LAC9-' . $c, 'formula'),
                    10 => $param->get('ad', 'LAC10-' . $c, 'formula'),
                    11 => $param->get('ad', 'LAC11-' . $c, 'formula'),
                    12 => $param->get('ad', 'LAC12-' . $c, 'formula'),
                    13 => $param->get('ad', 'LAC13-' . $c, 'formula'),
                ),
                'custom_d_val' => array(
                    1 => $param->get('ad', 'LDC1-' . $c, 'description'),
                    2 => $param->get('ad', 'LDC2-' . $c, 'description'),
                    3 => $param->get('ad', 'LDC3-' . $c, 'description'),
                    4 => $param->get('ad', 'LDC4-' . $c, 'description'),
                    5 => $param->get('ad', 'LDC5-' . $c, 'description'),
                    6 => $param->get('ad', 'LDC6-' . $c, 'description'),
                    7 => $param->get('ad', 'LDC7-' . $c, 'description'),
                ),
                'LDF1' => $param->get('ad', 'LDF1-' . $c, 'description'),
                'LDF2' => $param->get('ad', 'LDF2-' . $c, 'description'),
                'LDF1_f' => $param->get('ad', 'LDF1-' . $c, 'formula'),
                'LDF2_f' => $param->get('ad', 'LDF2-' . $c, 'formula'),
                'custom_d_for' => array(
                    1 => $param->get('ad', 'LDC1-' . $c, 'formula'),
                    2 => $param->get('ad', 'LDC2-' . $c, 'formula'),
                    3 => $param->get('ad', 'LDC3-' . $c, 'formula'),
                    4 => $param->get('ad', 'LDC4-' . $c, 'formula'),
                    5 => $param->get('ad', 'LDC5-' . $c, 'formula'),
                    6 => $param->get('ad', 'LDC6-' . $c, 'formula'),
                    7 => $param->get('ad', 'LDC7-' . $c, 'formula'),
                ),
                'fund1' => $param->get('param', 'fund_1', ['name', 'value']),
                'fund2' => $param->get('param', 'fund_2', ['name', 'value']),
                'fund3' => $param->get('param', 'fund_3', ['name', 'value']),
                'fund4' => $param->get('param', 'fund_4', ['name', 'value']),
                'fund5' => $param->get('param', 'fund_5', ['name', 'value']),
                'incometax' => $param->get('param', 'tax', ['name', 'value']),
            );

            return array(
                '#theme' => 'ek_hr_history_pay',
                '#items' => $data,
                '#title' => $this->t('Employee pay history'),
                '#attached' => array(
                    'library' => array('ek_hr/ek_hr_data_css'),
                ),
            );
        } else {
            return array('#markup' => $this->t('Restricted access'));
        }
    }

    /**
     * Return new employee form
     *
     */
    public function employeeNew(Request $request) {
        $build['new_employee'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditEmployee');
        return $build;
    }

    public function employeeEdit(Request $request, $id) {
        $query = 'SELECT administrator FROM {ek_hr_workforce}  WHERE id=:id';
        $administrator = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();

        if ($administrator == '0' || in_array(\Drupal::currentUser()->id(), explode(',', $administrator))) {
            $build['edit_employee'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditEmployee', $id);
            return $build;
        } else {
            return array('#markup' => $this->t('Restricted access'));
        }
    }

    /**
     * Return list of documents
     *
     */
    public function employeeDoc(Request $request, $id) {
        $header = array(
            'doc' => array(
                'data' => $this->t('Documents'),
                'field' => 'doc_name',
                'sort' => 'asc',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'action' => array(
                'data' => '',
            ),
        );

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        $query = "SELECT administrator FROM {ek_hr_workforce} WHERE id=:id";
        $a = array(':id' => $id);
        $admin = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
        $account = \Drupal::currentUser();
        $uid = $account->id();

        if ($admin == '0' || in_array('administrator', \Drupal::currentUser()->getRoles())) {
            //no filter by admin
            $query = "SELECT ek_hr_documents.id,fid,uri,filename,type,comment,date,size "
                    . "FROM {ek_hr_documents} INNER JOIN {ek_hr_workforce} "
                    . "ON ek_hr_documents.employee_id=ek_hr_workforce.id "
                    . "WHERE employee_id=:e AND FIND_IN_SET (company_id, :u) order by ek_hr_documents.id";
            $a = array(
                ':e' => $id,
                ':u' => $company
            );

            $form = 1;
        } else {

            // filter by admin
            $query = "SELECT ek_hr_documents.id,uri,fid,filename,type,comment,date,size "
                    . "FROM {ek_hr_documents} INNER JOIN {ek_hr_workforce} "
                    . "ON ek_hr_documents.employee_id=ek_hr_workforce.id "
                    . "WHERE employee_id=:e AND FIND_IN_SET ($uid, :u) order by ek_hr_documents.id";
            $a = array(
                ':e' => $id,
                ':u' => $admin
            );

            $form = 0;
        }

        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

        while ($r = $data->fetchObject()) {
            $date = date('Y-m-d', $r->date);

            if ($r->comment == 'deleted') {
                $link = '[-]';
                $doc = $r->filename;
            } else {
                $route = Url::fromRoute('ek_hr.employee.delete-doc', ['id' => $r->id])->toString();
                $link = "<a href=" . $route . " class='use-ajax red' >[x]</a>";
                $doc = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($r->uri) . "' target='_blank'>" . $r->filename . "</a>";
            }


            $options[$r->id] = array(
                'id' => 'div-' . $r->id,
                'data' => array(
                    'doc' => ['data' => ['#markup' => $doc]],
                    'date' => array('data' => $date),
                    'action' => array('data' => ['#markup' => $link], 'align' => 'center'),
                ),
            );
        }//loop

        $form == 1 ? $build['upload'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\UploadForm', $id) : $build['upload'] = array();
        $build['hr_doc_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'hr_table'),
            '#empty' => $this->t('No document'),
            '#attached' => array(
                'library' => array('ek_hr/ek_hr_css'),
            ),
        );





        return $build;
    }

    public function deletedoc($id) {
        $query = "SELECT uri from {ek_hr_documents} WHERE id=:id";
        $a = array(':id' => $id);
        $uri = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        $fields = array(
            'uri' => date('U'),
            'comment' => 'deleted',
            'fid' => 0
        );
        $del = Database::getConnection('external_db', 'external_db')->update('ek_hr_documents')
                        ->fields($fields)->condition('id', $id)->execute();

        if ($del) {
            $query = Database::getConnection()->select('file_managed', 'f');
            $query->fields('f', ['fid']);
            $query->condition('uri', $uri);
            $fid = $query->execute()->fetchField();
            if (!$fid) {
                unlink($uri);
            } else {
                //file will be deleted by automated cron task
                $file = \Drupal\file\Entity\File::load($fid);
                $file->setTemporary();
                $file->save();
            }
            $response = new AjaxResponse();
            $response->addCommand(new RemoveCommand('#div-' . $id));
            return $response;
        }
    }

    public function category() {
        $build['cat'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditCategory');
        return $build;
    }

    public function ad() {
        $build['ad'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditAd');
        return $build;
    }

    public function main() {
        $build['main'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditMainParameters');
        return $build;
    }

    public function organization() {

        //Return $build;
    }

    public function location() {
        $build['location'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditLocation');
        return $build;
    }

    public function service() {
        $build['service'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditService');
        return $build;
    }

    public function rank() {
        $build['rank'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditRank');
        return $build;
    }

    public function accounts() {
        $build['accounts'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\EditAccounts');
        return $build;
    }

    /**
     * list payslip formats available
     */
    public function payslip() {
        $header = array(
            'doc' => array(
                'data' => $this->t('Form'),
                'field' => 'doc_name',
                'sort' => 'asc',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'action' => array(
                'data' => '',
            ),
        );

        $list = array();
        if (file_exists("private://hr/payslips")) {
            $handle = opendir("private://hr/payslips");
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    array_push($list, $file);
                }
            }
        }
        $i = 0;
        $options = [];
        foreach ($list as $key => $v) {
            $i++;

            $vid = str_replace('.', '___', $v);
            $link = "<a href='#' class='deleteButton red' id='" . $vid . "' title='" . $this->t('delete') . "' >[x]</a>";

            $options[$i] = array(
                'data' => array(
                    'doc' => $v,
                    'date' => date("Y-m-d", filemtime("private://hr/payslips/" . $v)),
                    'action' => array('data' => ['#markup' => $link], 'align' => 'center'),
                ),
                'id' => 'r-' . $vid,
            );
        }


        $build['upload_payslip'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\UploadFormPayslip');
        $build['hr_payslip_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'hr_table_payslip'),
            '#empty' => $this->t('No document'),
            '#attached' => array(
                'drupalSettings' => array('hr' => 'payslip'),
                'library' => array('ek_hr/ek_hr_forms'),
            ),
        );

        return $build;
    }

    /**
     * list HR forms available
     */
    public function formHr() {
        $header = array(
            'doc' => array(
                'data' => $this->t('Form'),
                'field' => 'doc_name',
                'sort' => 'asc',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'action' => array(
                'data' => '',
            ),
        );

        $list = array();
        $handle = opendir("private://hr/forms");
        while ($file = readdir($handle)) {
            if ($file != '.' and $file != '..') {
                array_push($list, $file);
            }
        }

        $i = 0;
        foreach ($list as $key => $v) {
            $i++;
            $doc = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString("private://hr/forms/" . $v) . "'>" . $v . "</a>";
            $vid = str_replace('.', '___', $v);

            $link = "<a href='#' class='use-ajax deleteButton red' id='" . $vid . "' title='" . $this->t('delete') . "' >[x]</a>";

            $options[$i] = array(
                'data' => array(
                    'doc' => $v,
                    'date' => date("Y-m-d", filemtime("private://hr/forms/" . $v)),
                    'action' => array('data' => ['#markup' => $link], 'align' => 'center'),
                ),
                'id' => 'r-' . $vid,
            );
        }


        $build['upload_form'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\UploadFormForms');
        $build['hr_forms_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'hr_table_forms'),
            '#empty' => $this->t('No document'),
            '#attached' => array(
                'drupalSettings' => array('hr' => 'form'),
                'library' => array('ek_hr/ek_hr_forms'),
            ),
        );

        return $build;
    }

    /**
     * delete payslip file
     * @name = file name
     */
    public function deletePayslip(Request $request, $name) {
        if (\Drupal::currentUser()->hasPermission('hr_parameters')) {
            $file = str_replace('___', '.', $name);
            $uri = "private://hr/payslips/" . $file;
            if (\Drupal::service('file_system')->delete($uri)) {
                return new JsonResponse(['id' => $name]);
            }
        } else {
            return new JsonResponse(['id' => null]);
        }
    }

    /**
     * delete form file
     * @name = file name
     */
    public function deleteForm(Request $request, $name) {
        if (\Drupal::currentUser()->hasPermission('hr_parameters')) {
            $file = str_replace('___', '.', $name);
            $uri = "private://hr/forms/" . $file;
            if (\Drupal::service('file_system')->delete($uri)) {
                return new JsonResponse(['id' => $name]);
            }
        } else {
            return new JsonResponse(['id' => null]);
        }
    }

    /**
     * display and edit funds tables
     */
    public function fundHr() {
        $build['filter_form'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\FilterFund');

        if (isset($_SESSION['hrfundfilter']['filter']) && $_SESSION['hrfundfilter']['filter'] == 1) {
            $param = explode('_', $_SESSION['hrfundfilter']['fund']);
            $form = "Drupal\\ek_hr_" . $_SESSION['hrfundfilter']['code'] . "\Form\\" . $param[0] . 'Form';

            $build['fundTable'] = $this->formBuilder->getForm($form);
        }


        return array(
            '#theme' => 'ek_hr_fund',
            '#items' => $build,
            '#title' => $this->t('Funds management'),
        );
    }

    /**
     * Callback function for fund table editing
     * @param
     *  table
     *  reference [id] + "_" + [field]
     *  value
     * @return TRUE or FALSE
     */
    public function fundEdit(Request $request) {
        $ref = explode('_', $request->query->get('reference'));

        if (is_numeric($request->query->get('value'))) {
            $input = str_replace(',', '', $request->query->get('value'));
        }

        if (isset($input)) {
            $update = Database::getConnection('external_db', 'external_db')
                    ->update($request->query->get('table'))
                    ->fields(array($ref['1'] => $input))
                    ->condition('id', $ref[0])
                    ->execute();

            return new JsonResponse(array('data' => true));
        } else {
            return new JsonResponse(array('data' => false));
        }
    }

    /**
     * AJAX callback handler for modal dialog.
     */
    public function modal($param) {
        return $this->dialog(true, $param);
    }

    /**
     * Util to render dialog in ajax callback.
     *
     * @param bool $is_modal
     *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
     * @param $param
     *   calling parameters
     *   0 content source
     *   1 coid
     *   2 options
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = false, $param = null) {
        $param = explode('|', unserialize($param));
        $content = [];
        switch ($param[0]) {

            case 'formula':
                $title = $this->t($param[0]);
                $ad = new HrSettings($param[1]);
                $formula = $ad->get('ad', $param[2], 'formula');
                if ($formula == '') {
                    $formula = $this->t('no formula set for this parameter');
                }
                $content = array(
                    'content' => array('#markup' => "<p>" .
                        $this->t('Formula can be set by parameter for value calculation. Go to "edit parameters" '
                                . 'to change the formula.') . "</p><br/>" . '{ ' . $formula . ' }')
                );
                $options = array('width' => '25%',);

                break;

            case 'roster_note':
                $title = ucfirst(str_replace('_', ' ', $this->t($param[0])));
                $ref = explode('_', $param[1]);
                $width = isset($param[2]) ? $param[2] : '30%';
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_hr\Form\RosterNoteEdit', $ref[1], $ref[2], $ref[0]);
                $options = array('width' => $width, 'height' => '300');
                break;
        }

        $response = new AjaxResponse();

        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $dialog = new OpenModalDialogCommand($title, $content, $options);
        $response->addCommand($dialog);

        return $response;
    }

    /**
     * Util to return employee name callback.
     * @param option
     * @param term
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function employee_autocomplete(Request $request) {
        $option = $request->query->get('option');
        $term = trim($request->query->get('q'));
        //$term = strtolower($term);
        $data = [];
        if (!isset($_SESSION['hrEmpData'])) {
            // build data by session 
            $_SESSION['hrEmpData'] = [];
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'hr')
                    ->fields('hr', ['id', 'custom_id', 'company_id', 'name', 'picture']);

            $ids = $query->execute();
            while ($d = $ids->fetchObject()) {
                $_SESSION['hrEmpData'][$d->id] = [
                    'custom_id' => $d->custom_id,
                    'name' => $d->name,
                    'company_id' => $d->company_id,
                    'picture' => $d->picture,
                ];
            }
        }

        if (isset($_SESSION['hrEmpData']) && strlen($term) > 0 && strpos($term, '%') === false) {
            switch ($option) {
                case 'default':
                default:
                    foreach ($_SESSION['hrEmpData'] as $id => $emp) {
                        if ($id == $term || $emp['custom_id'] == $term || str_contains($emp['name'], $term)) {
                            $data[] = $id . ' | ' . $emp['name'];
                        }
                    }

                    break;
                case 'image':
                    foreach ($_SESSION['hrEmpData'] as $id => $emp) {
                        if ($id == $term || $emp['custom_id'] == $term || str_contains($emp['name'], $term)) {
                            $line = [];
                            $dir = "private://hr/pictures/" . $emp['company_id'] . "/";
                            if ($emp['picture'] && file_exists($dir . basename($emp['picture']))) {
                                $thumb = "private://hr/pictures/" . $emp['company_id'] . "/40/40x40_" . basename($emp['picture']);
                                if (!file_exists($thumb)) {
                                    $dir = "private://hr/pictures/" . $emp['company_id'] . "/40/";
                                    $filesystem = \Drupal::service('file_system');
                                    $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                                    $filesystem->copy($emp['picture'], $thumb, 'FILE_EXISTS_REPLACE');
                                    // Resize after copy
                                    $image_factory = \Drupal::service('image.factory');
                                    $image = $image_factory->get($thumb);
                                    $image->scale(40);
                                    $image->save();
                                }

                                $pic = "<img class='hr_thumbnail' src='"
                                        . \Drupal::service('file_url_generator')->generateAbsoluteString($thumb) . "'>";
                            } else {
                                $default = \Drupal::service('file_url_generator')->generateAbsoluteString(\Drupal::service('extension.path.resolver')->getPath('module', 'ek_hr') . '/art/default.jpeg');
                                $pic = "<img class='hr_thumbnail' src='"
                                        . $default . "'>";
                            }
                            $line['picture'] = isset($pic) ? $pic : '';
                            $line['name'] = $emp['name'];
                            $line['id'] = $id;
                            $data[] = $line;
                        }
                    }
                    break;
            }
        }

        return new JsonResponse($data);
    }

}

//class
