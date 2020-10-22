<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Controller\ProjectController.
 */

namespace Drupal\ek_projects\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_projects\ProjectData;

/**
 * Controller routines for ek module routes.
 */
class ProjectController extends ControllerBase {
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
     * The entity manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManager
     */
    protected $entityTypeManager;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler'), $container->get('entity_type.manager')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
     *   The entity manager.
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler, EntityTypeManager $entity_manager) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->entityTypeManager = $entity_manager;
        $this->extdb = Database::getConnection('external_db', 'external_db');
    }

    /**
     * Return project dashboard
     *
     */
    public function dashboard() {
        return array();
    }

    /**
     * Return a search form
     *
     */
    public function search(Request $request) {
        $build['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\FilterProjects');

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCountryByUser();
        $country = implode(',', $access);
        $links = array();
        $options = array();

        if (isset($_SESSION['pjfilter']['filter'])) {
            if (isset($_SESSION['pjfilter']['keyword']) && $_SESSION['pjfilter']['keyword'] != null && $_SESSION['pjfilter']['keyword'] != '%') {
                if (is_numeric($_SESSION['pjfilter']['keyword'])) {
                    $id1 = '%-' . trim($_SESSION['pjfilter']['keyword']) . '%';
                    $id2 = '%-' . trim($_SESSION['pjfilter']['keyword']) . '-sub%';

                    $query = $this->extdb->select('ek_project', 'p');

                    $or = $query->orConditionGroup();
                    $or->condition('pcode', $id1, 'like');
                    $or->condition('pcode', $id2, 'like');
                    $data = $query
                            ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date', 'archive'))
                            ->condition($or)
                            ->extend('Drupal\Core\Database\Query\TableSortExtender')
                            ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                            ->limit(10)->orderBy('id', 'ASC')
                            ->execute();
                } else {
                    $key = Xss::filter($_SESSION['pjfilter']['keyword']);
                    $keyword1 = trim($key) . '%';
                    $keyword2 = '%' . trim($key) . '%';
                    $query = $this->extdb->select('ek_project', 'p');
                    $query->leftJoin('ek_project_documents', 'd', 'p.pcode=d.pcode');
                    $query->leftJoin('ek_project_description', 't', 'p.pcode=t.pcode');
                    $or = $query->orConditionGroup();
                    $or->condition('p.pname', $keyword2, 'like');
                    $or->condition('d.filename', $keyword2, 'like');
                    $or->condition('t.project_description', $keyword2, 'like');
                    $or->condition('t.project_comment', $keyword2, 'like');
                    $data = $query
                            ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date', 'archive'))
                            ->condition($or)
                            ->extend('Drupal\Core\Database\Query\TableSortExtender')
                            ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                            ->limit(10)->orderBy('id', 'ASC')
                            ->distinct()
                            ->execute();
                }
            } else {
                if ($_SESSION['pjfilter']['cid'] == 0) {
                    $cid = '%';
                } else {
                    $cid = $_SESSION['pjfilter']['cid'];
                }
                if (in_array('%', $_SESSION['pjfilter']['supplier'])) {
                    $_SESSION['pjfilter']['supplier'] = '%';
                }
                if (in_array('%', $_SESSION['pjfilter']['client'])) {
                    $_SESSION['pjfilter']['client'] = '%';
                }

                $query = $this->extdb->select('ek_project', 'p');
                $query->leftJoin('ek_project_description', 'd', 'd.pcode=p.pcode');
                $query
                        ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date', 'archive'))
                        ->condition('cid', $cid, 'like')
                        ->condition('category', $_SESSION['pjfilter']['type'], 'like')
                        ->condition('status', $_SESSION['pjfilter']['status'], 'like');

                if ($_SESSION['pjfilter']['client'] != '%') {
                    $query->condition('client_id', $_SESSION['pjfilter']['client'], 'IN');
                }

                if ($_SESSION['pjfilter']['date'] == '1') {
                    $query->condition('date', $_SESSION['pjfilter']['start'], '>=');
                    $query->condition('date', $_SESSION['pjfilter']['end'], '<=');
                }

                if ($_SESSION['pjfilter']['supplier'] != '%') {
                    //a project can have multiple suppliers

                    $or = $query->orConditionGroup();
                    foreach ($_SESSION['pjfilter']['supplier'] as $key => $id) {
                        $or->condition('supplier_offer', $id . ',%', 'like');
                        $or->condition('supplier_offer', '%,' . $id . ',%', 'like');
                        $or->condition('supplier_offer', '%,' . $id, 'like');
                        $or->condition('supplier_offer', $id, '=');
                    }

                    $query->condition($or);
                } else {
                    
                }


                $data = $query
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(30)->orderBy('id', 'ASC')
                        ->execute();
            }

            $i = 0;
            $archive = [0 => $this->t('no'), 1 => $this->t('yes')];
            $excel = [];
            while ($r = $data->fetchObject()) {
                if (in_array($r->cid, $access)) {//filter access by country
                    $i++;
                    array_push($excel, $r->id);
                    $pcode = ProjectData::geturl($r->id);
                    $country = $this->extdb->query("SELECT name FROM {ek_country} WHERE id=:cid", array(':cid' => $r->cid))->fetchField();
                    $category = $this->extdb->query("SELECT type FROM {ek_project_type} WHERE id=:t", array(':t' => $r->category))->fetchField();

                    $route = Url::fromRoute('ek_projects_archive', ['id' => $r->id], array())->toString();
                    $archive_button = "<a id='arch" . $r->id . "' title='" . $this->t('change archive status') . "' href='" . $route . "' class='use-ajax'>" . $archive[$r->archive] . '</a>';

                    $options[$i] = array(
                        'reference' => ['data' => ['#markup' => $pcode]],
                        'date' => $r->date,
                        'name' => $r->pname,
                        'country' => $country,
                        'category' => $category,
                        'status' => $r->status,
                        'archive' => ['data' => ['#markup' => $archive_button]],
                    );
                }
            }
            $url = Url::fromRoute('ek_projects_excel_list', array('param' => serialize($excel)), array())->toString();
            $build['excel'] = ['#markup' => "<br/><a href='" . $url . "'>" . $this->t('Excel') . "</a>"];
        }



        $header = array(
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'country' => array(
                'data' => $this->t('Country'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'category' => array(
                'data' => $this->t('Category'),
            ),
            'status' => array(
                'data' => $this->t('Status'),
            ),
            'archive' => array(
                'data' => $this->t('Archive'),
            ),
        );

        $build['project_list'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'projects_table'),
            '#empty' => $this->t('No search result'),
            '#attached' => array(
                'library' => array('ek_projects/ek_projects_css'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
            '#weight' => 5,
        );

        return $build;
    }

    /**
     * Output a list of project as search result in excel format
     * @param param = array list of project ID
     */
    public function list_excel($param) {
        $markup = array();

        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);
            $query = "SELECT * FROM {ek_project} p "
                    . "LEFT JOIN {ek_project_description} d ON p.pcode=d.pcode "
                    . "LEFT JOIN {ek_project_finance} f on p.pcode=f.pcode "
                    . "WHERE FIND_IN_SET (id, :c ) ORDER by p.id";
            $data = $this->extdb->query($query, [':c' => implode(',', $param)]);

            include_once drupal_get_path('module', 'ek_projects') . '/excel_list.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * Return project dashboard
     *
     */
    public function newproject() {
        return $items['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\NewProject');
    }

    /**
     * Return project page and data
     *
     * @see hook_project_view()
     *
     * @param $id
     *  The project id
     * @return array
     */
    public function project_view(Request $request, $id) {
        $items = [];
        $data = [];
        Cache::invalidateTags(['project_view_block']);
        $data['display'] = [
            's1' => (null !== $request->query->get('s1')) ? $request->query->get('s1') : 'none',
            's2' => (null !== $request->query->get('s2')) ? $request->query->get('s2') : 'none',
            's3' => (null !== $request->query->get('s3')) ? $request->query->get('s3') : 'none',
            's4' => (null !== $request->query->get('s4')) ? $request->query->get('s4') : 'none',
            's5' => (null !== $request->query->get('s5')) ? $request->query->get('s5') : 'none',
        ];
        $edit_icon = "&nbsp<span class='ico pencil-edit'></span>";

        $ab = $this->extdb
                        ->select('ek_address_book', 'ab')
                        ->fields('ab', ['id', 'name'])
                        ->execute()->fetchAllKeyed();

        if (!ProjectData::validate_access($id)) {
            return $items['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\AccessRequest', $id);
        } else {

            $settings = ['id' => $id,];
            $sections = ProjectData::validate_section_access(\Drupal::currentUser()->id());

            for ($i = 1; $i < 6; $i++) {
                if (in_array($i, $sections)) {
                    $data['section_' . $i] = 1;
                }
            }

            /*
             * main data collection
             */

            $query = $this->extdb
                    ->select('ek_project')
                    ->fields('ek_project')
                    ->condition('id', $id)
                    ->execute();

            $data['project'] = $query->fetchAll();
            $pcode = $data['project'][0]->pcode;

            $data['type'] = $this->extdb
                            ->select('ek_project_type')
                            ->fields('ek_project_type', ['type'])
                            ->condition('id', $id)
                            ->execute()->fetchField();

            $code = str_replace('/', '-', $pcode);
            $code = explode("-", $code);
            $code = array_reverse($code);
            $code_serial = $code[0];
            $sub = null;

            if ($data['project'][0]->level == 'Main project' && $data['project'][0]->subcount > 0) {
                $sub = $this->extdb
                        ->select('ek_project', 'p')
                        ->fields('p', ['id', 'pname'])
                        ->condition('main', $id)
                        ->condition('pcode', $pcode, '<>')
                        ->execute();

                while ($l = $sub->fetchObject()) {
                    $data['sub'][] = ProjectData::geturl($l->id) . " - " . $l->pname;
                }
            } elseif ($data['project'][0]->level == 'Sub project') {
                $data['sub'][] = ProjectData::geturl($data['project'][0]->main);
                $cc = $this->extdb
                            ->select('ek_country')
                            ->fields('ek_country', ['code'])
                            ->condition('id', $data['project'][0]->cid)
                            ->execute()->fetchField();
                $data['project'][0]->level = $cc . ' - ' . $data['project'][0]->level;
            }

            /*
             * manage the edit mode
             */
            if ($data['project'][0]->editor == 0 || $data['project'][0]->editor == \Drupal::currentUser()->id()) {
                $data['project'][0]->edit_mode = '<button class="btn _edit" id="edit_mode"><span >' . $this->t('edit mode') . '</span>'
                        . ' <span class="ico pencil"></span></button>';
            }

            /*
             * manage the notify mode
             */
            $notify = explode(',', $data['project'][0]->notify);
            if (in_array(\Drupal::currentUser()->id(), $notify)) {
                $val = 'follow';
                $cl = 'follow';
                $cl2 = "check-square";
            } else {
                $cl = '_follow';
                $cl2 = 'square';
            }

            $data['project'][0]->keep_notify = '<button class="btn '
                    . $cl . '" id="edit_notify" /> ' . $this->t('follow') . ' '
                    . '<span  id="edit_notify_i" class="ico ' . $cl2 . '"></span>'
                    . '</button>';


            /*
             * convert last view data
             */
            $last = explode('|', $data['project'][0]->last_modified);
            $acc = \Drupal\user\Entity\User::load($last[1]);
            $name = '';
            if ($acc) {
                $name = $acc->getDisplayName();
            }
            $on = date('l jS \of F Y h:i A', $last[0]);
            $data['project'][0]->last_modified = $name . ' (' . $this->t('on') . ' ' . $on . ')';
            //update new last view
            $last_modified = time() . '|' . \Drupal::currentUser()->id();
            $this->extdb
                    ->update('ek_project')
                    ->fields(array('last_modified' => $last_modified))
                    ->condition('id', $id)
                    ->execute();

            $fields = array(
                'pcode' => $pcode,
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => 'open'
            );
            $this->extdb
                    ->insert('ek_project_tracker')->fields($fields)->execute();

            //system log view
            $a = array('@u' => \Drupal::currentUser()->getAccountName(), '@d' => $pcode);
            $log = $this->t("User @u has opened project @d", $a);
            \Drupal::logger('ek_projects')->notice($log);


            $prio = array(0 => $this->t('not set'), 1 => $this->t('low'), 2 => $this->t('medium'), 3 => $this->t('high'));
            $data['project'][0]->priority = $prio[$data['project'][0]->priority];

            /*
             * create a link to edit title
             */
            if (\Drupal::currentUser()->hasPermission('admin_projects')) {
                $param_edit = 'field|pname|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_pname = ('<a title="' . $this->t('edit name') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                $param_edit = 'field|owner|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_owner = ('<a title="' . $this->t('edit owner') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                $param_edit = 'field|client_id|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_client_id = ('<a title="' . $this->t('edit client') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
            }

            /*
             * create a link to edit users access for owner only
             */
            if (\Drupal::currentUser()->id() == $data['project'][0]->owner || \Drupal::currentUser()->hasPermission('admin_projects')) {
                $param_access = 'access|' . $id . '|project';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_access])->toString();
                $data['project'][0]->access = $this->t('<a href="@url" class="@c" >manage access</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            }
            
            /*
             * create a link to split form
             */
            $data['project'][0]->split = null;
            if ($data['project'][0]->level == 'Main project') {
                $param_split = 'split|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_split])->toString();
                $data['project'][0]->split = $this->t('<a href="@url" class="@c" >split</a>', array('@url' => $link, '@c' => 'use-ajax blue'));
            }
            
            /*
             * create a link for notification
             */
            $param_note = 'notification|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_note])->toString();
            $data['project'][0]->notification = $this->t('<a href="@url" class="@c" >notification</a>', array('@url' => $link, '@c' => 'use-ajax blue notification'));
            /*
             * create a link to create a task
             */
            $param_edit = 'task|' . $id;
            $destination = ['destination' => 'projects/project/' . $id . '?s2=true#ps2'];
            $link = Url::fromRoute('ek_projects_task', ['pid' => $id, 'id' => '0'], ['query' => $destination])->toString();
            $size = Json::encode(['width' => '30%', 'resizable' => 1]);
            $data['project'][0]->new_project_task = '<a href="' . $link . '" class="use-ajax blue notification" data-dialog-type="dialog" data-dialog-renderer="off_canvas" data-dialog-options=' . $size . '>' . $this->t('New task') . '</a>';

            $data['project'][0]->task_list = self::TaskList($pcode);
            /*
             * create a link to addresses book
             */

            $data['project'][0]->client_url = \Drupal\ek_address_book\AddressBookData::geturl($data['project'][0]->client_id);

            /*
             * create a link to edit status
             */
            $param_edit = 'field|status|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['project'][0]->edit_statusUrl = $link;
            $data['project'][0]->edit_status = ('<a title="' . $this->t('edit status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

            /*
             * create a link to edit priority
             */
            $param_edit = 'field|priority|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['project'][0]->edit_priority = ('<a title="' . $this->t('edit priority') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


            /*
             * user table
             */
            $acc = \Drupal\user\Entity\User::load($data['project'][0]->owner);
            if ($acc) {
                $data['user']['name'] = $acc->getDisplayName();
            }
            $param_edit = 'followers|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['followers'][0] = new \stdClass;
            $data['followers'][0]->url = $link;
            $data['followers'][0]->list = ('<a href="' . $link . '" class="use-ajax blue" >' . $this->t('Followers') . '</a>');

            /*
             * description table
             */
            if (in_array(1, $sections)) {
                //$query = "SELECT * from {ek_project_description} WHERE pcode=:p";
                $data['description'] = $this->extdb->select('ek_project_description', 'd')
                        ->fields('d')
                        ->condition('pcode', $pcode)
                        ->execute()
                        ->fetchAll();

                $data['description'][0]->project_description = html_entity_decode($data['description'][0]->project_description, ENT_QUOTES, "utf-8");
                $data['description'][0]->project_comment = html_entity_decode($data['description'][0]->project_comment, ENT_QUOTES, "utf-8");


                /*
                 * suppliers
                 */

                $data['suppliers'] = [];
                if ($data['description'][0]->supplier_offer) {
                    $suppliers = explode(',', $data['description'][0]->supplier_offer);
                    foreach ($suppliers as $key) {
                        $data['suppliers'][] = [
                            'name' => $ab[$key],
                            'url' => \Drupal\ek_address_book\AddressBookData::geturl($key),
                        ];
                    }
                    $data['description'][0]->supplier_offer = $data['suppliers'];
                }
                /*
                 * create a link to edit suppliers
                 */
                $param_edit = 'field|supplier_offer|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_supplier_offer = ('<a title="' . $this->t('edit supplier') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date submission
                 */
                $param_edit = 'field|submission|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_submission = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date validation
                 */
                $param_edit = 'field|validation|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_validation = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date deadline
                 */
                $param_edit = 'field|deadline|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_deadline = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date start_date
                 */
                $param_edit = 'field|start_date|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_start_date = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date completion
                 */
                $param_edit = 'field|completion|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_completion = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_1
                 */
                $param_edit = 'field|perso_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_1 = ('<a title="' . $this->t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_2
                 */
                $param_edit = 'field|perso_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_2 = ('<a title="' . $this->t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_3
                 */
                $param_edit = 'field|perso_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_3 = ('<a title="' . $this->t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to edit repo_1
                 */
                $param_edit = 'field|repo_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_1 = ('<a title="' . $this->t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit repo_2
                 */
                $param_edit = 'field|repo_2|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_2 = ('<a title="' . $this->t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit repo_3
                 */
                $param_edit = 'field|repo_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_3 = ('<a title="' . $this->t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to edit task_1
                 */
                $param_edit = 'field|task_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_1 = ('<a title="' . $this->t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit task_2
                 */
                $param_edit = 'field|task_2|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_2 = ('<a title="' . $this->t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit task_3
                 */
                $param_edit = 'field|task_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_3 = ('<a title="' . $this->t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit project_description
                 */
                $param_edit = 'field|project_description|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_project_description = ('<a title="' . $this->t('edit description') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit project_comment
                 */
                $param_edit = 'field|project_comment|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_project_comment = ('<a title="' . $this->t('edit comment') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
            } //if section_1

            /*
             * action plan table
             */

            if (in_array(2, $sections)) {
                
            }//if section_2

            /*
             * communication table
             */

            if (in_array(3, $sections)) {

                /*
                 * create a link to upload a file
                 */
                //the param to pass to modal follow the structure action|pcode|query|type
                // action is used by modal to select form, other params are passed to the form
                $data['communication'][0] = (object) array();
                $param_upload = 'upload|' . $pcode . '|doc|com|';

                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_upload])->toString();
                $data['communication'][0]->uploadUrl = $link;
                $data['communication'][0]->upload = $this->t('<a href="@url" class="@c" >upload new file</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            }

            /*
             * reports links
             */
            if ($this->moduleHandler->moduleExists('ek_intelligence')) {
                //$query = "SELECT id,serial,edit,description FROM {ek_ireports} WHERE pcode=:p";
                $irep = $this->extdb->select('ek_ireports', 'r')
                        ->fields('r', ['id', 'serial', 'edit', 'description'])
                        ->condition('pcode', $pcode)
                        ->execute();
                //->query($query, array(':p' => $data['project'][0]->pcode));
                $report_list = array();
                $data['reports'][0] = (object) array();
                while ($d = $irep->fetchObject()) {
                    $link = Url::fromRoute('ek_intelligence.read', ['id' => $d->id])->toString();
                    $report_list[] = array(
                        'serial' => '<a href="' . $link . '">' . $d->serial . '</a>',
                        'edit' => date('Y-m-d', $d->edit),
                        'description' => $d->description,
                    );
                }
                $data['reports'][0]->list = $report_list;
            }
            /*
             * shipment table
             */
            if (in_array(4, $sections)) {
                $data['logistic'] = $this->extdb->select('ek_project_shipment', 's')
                                ->fields('s')
                                ->condition('pcode', $pcode)
                                ->execute()->fetchAll();

                /*
                 * create a link to edit first_ship
                 */
                $param_edit = 'field|first_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_first_ship = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit second_ship
                 */
                $param_edit = 'field|second_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_second_ship = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit third_ship
                 */
                $param_edit = 'field|third_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_third_ship = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit four_ship
                 */
                $param_edit = 'field|four_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_four_ship = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit status
                 */
                $param_edit = 'field|ship_status|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_ship_status = ('<a title="' . $this->t('edit status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit last_delivery
                 */
                $param_edit = 'field|last_delivery|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_last_delivery = ('<a title="' . $this->t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                if ($this->moduleHandler->moduleExists('ek_logistics')) {
                    /*
                     * extact receiving
                     */

                    $deliveries = $this->extdb->select('ek_logi_receiving', 'r')
                            ->fields('r', ['id', 'serial', 'head', 'ddate', 'status', 'supplier'])
                            ->condition('pcode', $pcode)
                            ->condition('type', 'RR')
                            ->execute();

                    $delivery_list = array();
                    $i = 0;
                    $delivery_status = array('0' => $this->t('open'), '1' => $this->t('printed'), '2' => $this->t('invoiced'), '3' => $this->t('posted'));

                    while ($delivery = $deliveries->fetchObject()) {
                        $href = Url::fromRoute('ek_logistics_receiving_print_share', ['id' => $delivery->id])->toString();
                        $link = ('<a title="' . $this->t('pdf') . '" href="' . $href . '" class="blue" >' . $this->t('print') . '</a>');

                        $delivery_list[$i] = array(
                            'serial' => $delivery->serial,
                            'status' => $delivery_status[$delivery->status],
                            'client' => $ab[$delivery->supplier],
                            'date' => $delivery->ddate,
                            'url' => $link,
                        );
                        $i++;
                    }
                    $data['logistic'][0]->receiving = $delivery_list;

                    /*
                     * extact deliveries
                     */

                    $deliveries = $this->extdb->select('ek_logi_delivery', 'd')
                            ->fields('d', ['id', 'serial', 'head', 'ddate', 'status', 'client'])
                            ->condition('pcode', $pcode)
                            ->execute();
                    $delivery_list = array();
                    $i = 0;
                    $delivery_status = array('0' => $this->t('open'), '1' => $this->t('printed'), '2' => $this->t('invoiced'), '3' => $this->t('posted'));

                    while ($delivery = $deliveries->fetchObject()) {
                        $href = Url::fromRoute('ek_logistics_delivery_print_share', ['id' => $delivery->id])->toString();
                        $link = ('<a title="' . $this->t('pdf') . '" href="' . $href . '" class="blue" >' . $this->t('print') . '</a>');

                        $delivery_list[$i] = array(
                            'serial' => $delivery->serial,
                            'status' => $delivery_status[$delivery->status],
                            'client' => $ab[$delivery->client],
                            'date' => $delivery->ddate,
                            'url' => $link,
                        );
                        $i++;
                    }
                    $data['logistic'][0]->delivery = $delivery_list;
                }
            } //if section_4


            /*
             * finance table
             */
            if (in_array(5, $sections)) {
                $data['finance'] = $this->extdb->select('ek_project_finance', 'f')
                        ->fields('f')
                        ->condition('pcode', $pcode)
                        ->execute()
                        ->fetchAll();

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    $fsettings = new FinanceSettings();
                    $baseCurrency = $fsettings->get('baseCurrency');
                } else {
                    $baseCurrency = '';
                }


                if ($this->moduleHandler->moduleExists('ek_sales')) {
                    /*
                     * extact PO list
                     */
                    // @TODO NOTE: PO are not filtered by COID access here

                    $pos = $this->extdb->select('ek_sales_purchase', 'p')
                            ->fields('p')
                            ->condition('pcode', $pcode)
                            ->orderBy('id')
                            ->execute();

                    $po_list = array();
                    $i = 0;
                    $po_status = array(t('unpaid'), $this->t('paid'), $this->t('partially paid'));
                    $sum_a = 0;
                    $sum_abc = 0;
                    while ($p = $pos->fetchObject()) {
                        $href = Url::fromRoute('ek_sales.purchases.print_html', ['id' => $p->id])->toString();
                        $link = '<a title="' . $this->t('view') . '" href="' . $href . '" class="blue" target="_blank">' . $this->t('view') . '</a>';
                        $sum_a = $sum_a + $p->amount;
                        $sum_abc = $sum_abc + $p->amountbc;
                        $more = Url::fromRoute('ek_sales.modal_more', ['param' => 'purchase|' . $p->id])->toString();
                        $psettings = new \Drupal\ek_sales\SalesSettings($p->head);
                        $due = 'green';
                        $duedate = '';
                        if ($p->status != '1') {
                            $duedate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($p->date)) . "+" . $p->due . "days"));
                            $long = round((strtotime($due) - strtotime(date('Y-m-d'))) / (24 * 60 * 60), 0);
                            if ($long > $psettings->get('longdue')) {
                                $due = 'green';
                            } elseif ($long >= $psettings->get('shortdue') && $long <= $psettings->get('longdue')) {
                                $due = 'orange';
                            } else {
                                $due = 'red';
                            }
                            $duetitle = $this->t('past due') . ' ' . -1 * $long . ' ' . $this->t('day(s)');
                        }
                        $po_list[$i] = array(
                            'serial' => $p->serial,
                            'status' => $po_status[$p->status],
                            'amount' => $p->currency . ' ' . number_format($p->amount, 2),
                            'amountbc' => $baseCurrency . ' ' . number_format($p->amountbc, 2),
                            'date' => $p->date,
                            'url' => ['#markup' => $link],
                            'href' => $href,
                            'more' => ['#markup' => $more],
                            'due' => ['#markup' => $due],
                            'duedate' => ['#markup' => $duedate],
                        );
                        $i++;
                    }
                    if ($i > 0) {
                        $data['finance'][0] = (object) array();
                        $data['finance'][0]->po = $po_list;
                        $data['finance'][0]->po_sum = array('sum' => number_format($sum_a, 2), 'sum_bc' => $baseCurrency . ' ' . number_format($sum_abc, 2));
                    }

                    /*
                     * extact Quote list
                     */

                    $quotes = $this->extdb->select('ek_sales_quotation', 'q')
                            ->fields('q')
                            ->condition('pcode', $pcode)
                            ->orderBy('id')
                            ->execute();
                    $quote_list = array();
                    $i = 0;
                    $quote_status = array(t('open'), $this->t('printed'), $this->t('invoiced'));


                    while ($q = $quotes->fetchObject()) {
                        $href = Url::fromRoute('ek_sales.quotations.print_share', ['id' => $q->id])->toString();
                        $link = ('<a title="' . $this->t('pdf') . '" href="' . $href . '" class="blue" >' . $this->t('print') . '</a>');

                        $quote_list[$i] = array(
                            'serial' => $q->serial,
                            'status' => $quote_status[$q->status],
                            'amount' => $q->currency . ' ' . number_format($q->amount, 2),
                            'date' => $q->date,
                            'url' => ['#markup' => $link],
                            'href' => $href
                        );
                        $i++;
                    }
                    if ($i > 0) {
                        $data['finance'][0]->quote = $quote_list;
                    }

                    /*
                     * extact Invoice list
                     */

                    $invoices = $this->extdb->select('ek_sales_invoice', 'i')
                            ->fields('i')
                            ->condition('pcode', $pcode)
                            ->orderBy('id')
                            ->execute();
                    $invoice_list = array();
                    $i = 0;
                    $invoice_status = array(t('unpaid'), $this->t('paid'), $this->t('partially paid'));
                    $sum_a = 0;
                    $sum_abc = 0;

                    while ($in = $invoices->fetchObject()) {
                        $href = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $in->id])->toString();
                        $link = '<a title="' . $this->t('view') . '" href="' . $href . '" class="blue" target="_blank">' . $this->t('view') . '</a>';
                        $sum_a = $sum_a + $in->amount;
                        $sum_abc = $sum_abc + $in->amountbase;
                        $more = Url::fromRoute('ek_sales.modal_more', ['param' => 'invoice|' . $in->id])->toString();
                        $isettings = new \Drupal\ek_sales\SalesSettings($in->head);
                        $due = 'green';
                        $duedate = '';
                        if ($in->status != '1') {
                            $duedate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($in->date)) . "+" . $in->due . "days"));
                            $long = round((strtotime($due) - strtotime(date('Y-m-d'))) / (24 * 60 * 60), 0);
                            if ($long > $isettings->get('longdue')) {
                                $due = 'green';
                            } elseif ($long >= $isettings->get('shortdue') && $long <= $isettings->get('longdue')) {
                                $due = 'orange';
                            } else {
                                $due = 'red';
                            }
                            $duetitle = $this->t('past due') . ' ' . -1 * $long . ' ' . $this->t('day(s)');
                        }
                        $invoice_list[$i] = array(
                            'serial' => $in->serial,
                            'status' => $invoice_status[$in->status],
                            'amount' => $in->currency . ' ' . number_format($in->amount, 2),
                            'amountbc' => $baseCurrency . ' ' . number_format($in->amountbase, 2),
                            'date' => $in->date,
                            'url' => ['#markup' => $link],
                            'href' => $href,
                            'more' => ['#markup' => $more],
                            'due' => ['#markup' => $due],
                            'duedate' => ['#markup' => $duedate],
                        );
                        $i++;
                    }
                    if ($i > 0) {
                        $data['finance'][0]->invoice = $invoice_list;
                        $data['finance'][0]->invoice_sum = array('sum' => number_format($sum_a, 2), 'sum_bc' => $baseCurrency . ' ' . number_format($sum_abc, 2));
                    }
                }

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    /*
                     * extact Expenses
                     */
                    $chart = $fsettings->get('chart');

                    $query = $this->extdb->select('ek_expenses', 'e')
                            ->condition('pcode', $pcode)
                            ->condition('class', $chart['cos'] . '%', 'LIKE');
                    $query->addExpression('SUM(amount)', 'sumValue');
                    $exp5 = $query->execute()->fetchObject()->sumValue;
                    $data['finance'][0]->expenses5 = $baseCurrency . ' ' . number_format($exp5, 2);

                    $query = $this->extdb->select('ek_expenses', 'e');
                    $condition = $query->orConditionGroup()
                            ->condition('class', $chart['expenses'] . '%', 'LIKE')
                            ->condition('class', $chart['other_expenses'] . '%', 'LIKE');
                    $query->condition($condition)->condition('pcode', $pcode);
                    $query->addExpression('SUM(amount)', 'sumValue');
                    $exp6 = $query->execute()->fetchObject()->sumValue;
                    $data['finance'][0]->expenses6 = $baseCurrency . ' ' . number_format($exp6, 2);
                    $data['finance'][0]->expenses = $baseCurrency . ' ' . number_format($exp5 + $exp6, 2);


                    /*
                     * extact Memos
                     */

                    $memos = $this->extdb->select('ek_expenses_memo', 'm')
                            ->fields('m')
                            ->condition('pcode', $pcode)
                            ->execute();

                    $memo_list = array();
                    $i = 0;
                    $memo_status = array('0' => $this->t('unpaid'), '1' => $this->t('partially paid'), '2' => $this->t('paid'));
                    $stc = array('0' => 'red', '1' => 'orange', '2' => 'green');
                    $sum_a = 0;
                    $sum_abc = 0;

                    while ($memo = $memos->fetchObject()) {
                        $href = Url::fromRoute('ek_finance_manage_print_html', ['id' => $memo->id])->toString();
                        $link = ('<a title="' . $this->t('pdf') . '" href="' . $href . '" class="blue" >' . $this->t('print') . '</a>');
                        $sum_a = $sum_a + $memo->value;
                        $sum_abc = $sum_abc + $memo->value_base;

                        $memo_list[$i] = array(
                            'serial' => $memo->serial,
                            'status' => $memo_status[$memo->status],
                            'status_class' => $stc[$memo->status],
                            'amount' => $memo->currency . ' ' . number_format($memo->value, 2),
                            'amountbc' => $baseCurrency . ' ' . number_format($memo->value_base, 2),
                            'date' => $memo->date,
                            'url' => ['#markup' => $link],
                            'href' => $href,
                            'mission' => $memo->mission,
                        );
                        $i++;
                    }
                    if ($i > 0) {
                        $data['finance'][0]->memo = $memo_list;
                        $data['finance'][0]->memo_sum = array('sum' => number_format($sum_a, 2),
                            'sum_bc' => $baseCurrency . ' ' . number_format($sum_abc, 2));
                    }
                }


                /*
                 * create a link to edit currency
                 */
                $param_edit = 'field|currency|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_currency = ('<a title="' . $this->t('edit currency') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit proposed value
                 */
                $param_edit = 'field|tender_offer|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_tender_offer = ('<a title="' . $this->t('edit offer value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit agreed value
                 */
                $param_edit = 'field|project_amount|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_project_amount = ('<a title="' . $this->t('edit project value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit payment terms
                 */
                $param_edit = 'field|payment_terms|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_payment_terms = ('<a title="' . $this->t('edit payment terms') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit discount
                 */
                $param_edit = 'field|discount_offer|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_discount_offer = ('<a title="' . $this->t('edit discount') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit offer validity
                 */
                $param_edit = 'field|offer_validity|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_offer_validity = ('<a title="' . $this->t('edit validity') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit offer delivery
                 */
                $param_edit = 'field|offer_delivery|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_offer_delivery = ('<a title="' . $this->t('edit deadline') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit incoterm
                 */
                $param_edit = 'field|incoterm|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_incoterm = ('<a title="' . $this->t('edit incoterm') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit purchase value
                 */
                $param_edit = 'field|purchase_value|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_purchase_value = ('<a title="' . $this->t('edit purchase value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc status
                 */
                $param_edit = 'field|lc_status|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_status = ('<a title="' . $this->t('edit LC status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc revision
                 */
                $param_edit = 'field|lc_revision|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_revision = ('<a title="' . $this->t('edit LC revision/ref.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc expiry
                 */
                $param_edit = 'field|lc_expiry|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_expiry = ('<a title="' . $this->t('edit LC expiry.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit payment
                 */
                $param_edit = 'field|payment|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_payment = ('<a title="' . $this->t('edit payment.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit comments
                 */
                $param_edit = 'field|comment|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_comment = ('<a title="' . $this->t('edit comment.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to upload a file
                 */
                //the param to pass to modal follow the structure action|pcode|query|type
                // action is used by modal to select form, other params are passed to the form
                $param_upload = 'upload|' . $pcode . '|doc|fi|';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_upload])->toString();
                $data['finance'][0]->uploadUrl = $link;
                $data['finance'][0]->upload = $this->t('<a href="@url" class="@c" >upload new file</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            } //if section_5
            // Let other modules add data to the page.
            if ($invoke = $this->moduleHandler()->invokeAll('project_view', [$data], $pcode)) {
                $data = $invoke;
            }

            $data['#theme'] = 1;

            return array(
                '#theme' => 'ek_projects_view',
                '#items' => $data,
                '#title' => $code_serial . ' | ' . $this->t('Reference') . ': ' . $pcode,
                '#attached' => array(
                    'drupalSettings' => array('ek_projects' => $settings),
                    'library' => array('ek_projects/ek_projects_view', 'ek_admin/ek_admin_css'),
                ),
                '#cache' => [
                    'tags' => ['project_page_view'],
                    'contexts' => ['user'],
                ],
            );
        }
    }

    /**
     * Return data called from periodical updater
     *
     */
    public function periodicalupdater(Request $request) {

        $id = $request->query->get('id');
        $qfield = $request->query->get('query');
        // filter user to avoid direct access to data by entering the link in address bar
        $access = ProjectData::validate_access($id);
        $sections = ProjectData::validate_section_access(\Drupal::currentUser()->id());

        if ($access) {

            //doc query
            $querydoc = Database::getConnection('external_db', 'external_db')->select('ek_project_documents', 'd');
            $querydoc->fields('d', ['id', 'fid', 'filename', 'sub_folder', 'uri', 'date', 'comment', 'size']);
            $querydoc->leftJoin('ek_project', 'p', 'd.pcode = p.pcode');
            $querydoc->fields('p', ['pcode', 'owner']);
            $querydoc->condition('p.id', $id);
            $querydoc->orderBy('d.date', 'ASC');



            switch ($qfield) {

                case 'fields':
                    $fields = array();
                    $prio = array(0 => '', 1 => $this->t('low'), 2 => $this->t('medium'), 3 => $this->t('high'));

                    $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');
                    $query->fields('p');
                    $query->innerJoin('ek_project_description', 'd', 'd.pcode = p.pcode');
                    $query->fields('d');
                    $query->innerJoin('ek_project_finance', 'f', 'f.pcode = p.pcode');
                    $query->fields('f');
                    $query->innerJoin('ek_project_shipment', 's', 's.pcode = p.pcode');
                    $query->fields('s');
                    $query->condition('p.id', $id);
                    $data = $query->execute()->fetchObject();
                    $fields['status'] = $data->status;
                    $fields['status_container'] = $data->status;
                    $fields['priority'] = $prio[$data->priority];

                    if (in_array(1, $sections)) {

                        if ($data) {
                            $fields['submission'] = $data->submission;
                            $fields['deadline'] = $data->deadline;
                            $fields['start_date'] = $data->start_date;
                            $fields['validation'] = $data->validation;
                            $fields['completion'] = $data->completion;
                            $fields['project_description'] = nl2br($data->project_description);
                            $fields['project_comment'] = nl2br($data->project_comment);
                            $fields['perso_1'] = $data->perso_1;
                            $fields['perso_2'] = $data->perso_2;
                            $fields['perso_3'] = $data->perso_3;
                            $fields['repo_1'] = $data->repo_1;
                            $fields['repo_2'] = $data->repo_2;
                            $fields['repo_3'] = $data->repo_3;
                            $fields['task_1'] = $data->task_1;
                            $fields['task_2'] = $data->task_2;
                            $fields['task_3'] = $data->task_3;
                            if ($data->supplier_offer != '') {
                                $array = explode(',', $data->supplier_offer);
                                $query = Database::getConnection('external_db', 'external_db')->select('ek_address_book', 'ab');
                                $query->fields('ab', ['id', 'name']);
                                $query->condition('id', $array, 'IN');
                                $suppliers = $query->execute();

                                $fields['suppliers'] = "<ul>";
                                while ($s = $suppliers->fetchObject()) {
                                    $fields['suppliers'] .= '<li>' . \Drupal\ek_address_book\AddressBookData::geturl($s->id) . '</li>';
                                }
                                $fields['suppliers'] .= "</ul>";
                            }
                        }
                    }

                    if (in_array(5, $sections)) {

                        if ($data) {
                            $fields['payment_terms'] = $data->payment_terms;
                            $fields['purchase_value'] = is_numeric($v = $data->purchase_value) ? number_format($v, 2) : $v;
                            $fields['discount_offer'] = is_numeric($v = $data->discount_offer) ? number_format($v, 2) : $v;
                            $fields['paymentdate_i'] = $data->paymentdate_i;
                            $fields['paymentdate_d'] = $data->paymentdate_d;
                            $fields['project_amount'] = is_numeric($v = $data->project_amount) ? number_format($v, 2) : $v;
                            $fields['lc_status'] = $data->lc_status;
                            $fields['lc_revision'] = $data->lc_revision;
                            $fields['lc_expiry'] = $data->lc_expiry;
                            $fields['tender_offer'] = is_numeric($v = $data->tender_offer) ? number_format($v, 2) : $v;
                            $fields['down_payment'] = is_numeric($v = $data->down_payment) ? number_format($v, 2) : $v;
                            $fields['offer_delivery'] = $data->offer_delivery;
                            $fields['offer_validity'] = $data->offer_validity;
                            $fields['invoice'] = is_numeric($v = $data->invoice) ? number_format($v, 2) : $v;
                            $fields['incoterm'] = $data->incoterm;
                            $fields['currency'] = $data->currency;
                            $fields['payment'] = is_numeric($v = $data->payment) ? number_format($v, 2) : $v;
                            $fields['comment'] = nl2br($data->comment);
                        }
                    }
                    if (in_array(4, $sections)) {

                        if ($data) {
                            $fields['first_ship'] = $data->first_ship;
                            $fields['second_ship'] = $data->second_ship;
                            $fields['third_ship'] = $data->third_ship;
                            $fields['four_ship'] = $data->four_ship;
                            $fields['ship_status'] = $data->ship_status;
                            $fields['last_delivery'] = $data->last_delivery;
                        }
                    }

                    return new JsonResponse(array('data' => $fields));

                    break;



                case 'com_docs':
                    if (in_array(3, $sections)) {
                        $querydoc->condition('folder', 'com');
                    }
                    break;

                case 'fi_docs':
                    if (in_array(5, $sections)) {
                        $querydoc->condition('folder', 'fi');
                    }
                    break;

                case 'ap_docs':
                    if (in_array(2, $sections)) {
                        $querydoc->condition('folder', 'ap');
                    }
                    break;
            }

            $list = $querydoc->execute();
            //build list of documents
            $t = '';
            $i = 0;
            $items = [];

            if (isset($list)) {
                while ($l = $list->fetchObject()) {
                    $i++;

                    /* default values */
                    $items[$l->sub_folder][$i]['pcode'] = $l->pcode; //used in hooks to extend data
                    $items[$l->sub_folder][$i]['id'] = $l->id; //db id
                    $items[$l->sub_folder][$i]['fid'] = 1; //default file status on
                    $items[$l->sub_folder][$i]['delete'] = 1; //default delete action is on
                    $items[$l->sub_folder][$i]['email'] = 1; //default email action is on
                    $items[$l->sub_folder][$i]['extranet'] = 0; //default extranet action is of
                    $items[$l->sub_folder][$i]['icon'] = 'file'; //default icon
                    $items[$l->sub_folder][$i]['file_url'] = ''; //default
                    $items[$l->sub_folder][$i]['access_url'] = 0; //default access management if off

                    $items[$l->sub_folder][$i]['uri'] = $l->uri;

                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);

                    $items[$l->sub_folder][$i]['icon_path'] = drupal_get_path('module', 'ek_projects') . '/art/icons/';

                    if (file_exists(drupal_get_path('module', 'ek_projects') . '/art/icons/' . $extension . ".png")) {
                        $items[$l->sub_folder][$i]['icon'] = strtolower($extension);
                    }

                    //filename formating
                    if (strlen($l->filename) > 30) {
                        $items[$l->sub_folder][$i]['doc_name'] = substr($l->filename, 0, 30) . " ... ";
                    } else {
                        $items[$l->sub_folder][$i]['doc_name'] = $l->filename;
                    }


                    if ($l->fid == '0') { //file was deleted
                        $items[$l->sub_folder][$i]['fid'] = 0;
                        $items[$l->sub_folder][$i]['delete'] = 0;
                        $items[$l->sub_folder][$i]['email'] = 0;
                        $items[$l->sub_folder][$i]['extranet'] = 0;
                        $items[$l->sub_folder][$i]['comment'] = $l->comment . " " . date('Y-m-d', $l->date);
                    } else {
                        if (!file_exists($l->uri)) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $items[$l->sub_folder][$i]['fid'] = 2;
                            $items[$l->sub_folder][$i]['delete'] = 0;
                            $items[$l->sub_folder][$i]['email'] = 0;
                            $items[$l->sub_folder][$i]['extranet'] = 0;
                            $items[$l->sub_folder][$i]['comment'] = $this->t('Document not available. Please contact administrator');
                        } else {
                            //file exist
                            if (ProjectData::validate_file_access($l->id)) {
                                $route = Url::fromRoute('ek_projects_delete_file', ['id' => $l->id])->toString();
                                $items[$l->sub_folder][$i]['delete_url'] = $route;
                                $items[$l->sub_folder][$i]['file_url'] = file_create_url($l->uri);
                                $destination = ['destination' => '/projects/project/' . $id];
                                $link = Url::fromRoute('ek_projects_file_data', ['id' => $l->id], ['query' => $destination])->toString();
                                $size = Json::encode(['width' => '30%', 'resizable' => 1]);
                                $items[$l->sub_folder][$i]['more'] = '<a href="' . $link . '" class="use-ajax" '
                                        . 'data-dialog-type="dialog" data-dialog-renderer="off_canvas" data-dialog-options=' 
                                        . $size . '>[+]</a>';
                                $param_mail = 'mail|' . $l->id . '|project_documents';
                                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_mail])->toString();
                                $items[$l->sub_folder][$i]['mail_url'] = $link;
                                $items[$l->sub_folder][$i]['delete'] = 1;
                                $items[$l->sub_folder][$i]['email'] = 1;
                                $items[$l->sub_folder][$i]['comment'] = ['#markup' => $l->comment];
                                $items[$l->sub_folder][$i]['date'] = date('Y-m-d', $l->date);
                                $items[$l->sub_folder][$i]['size'] = round($l->size / 1000, 0) . " Kb";
                            } else {
                                //file exist but access not authorized
                                $items[$l->sub_folder][$i]['delete'] = 0;
                                $items[$l->sub_folder][$i]['email'] = 0;
                                $items[$l->sub_folder][$i]['fid'] = 0;
                                $items[$l->sub_folder][$i]['comment'] = $this->t('Restricted access');
                            }
                        }
                    }

                    //disable because not using file_managed table (TO implement in project table?)
                    //$owner = ProjectData::file_owner($l->id);
                    // use project owner instead

                    if (($l->owner == \Drupal::currentUser()->id() || \Drupal::currentUser()->hasPermission('admin_projects')) && $l->fid != '0') {
                        $param_access = 'access|' . $l->id . '|project_doc';
                        $link = Url::fromRoute('ek_projects_modal', ['param' => $param_access])->toString();
                        $items[$l->sub_folder][$i]['access_url'] = $link;
                    }
                }

                // Let other modules add data to the list.
                if ($invoke = $this->moduleHandler()->invokeAll('project_doc_view', [$items])) {
                    $items = $invoke;
                }

                $render = ['#theme' => 'ek_projects_doc_view', '#items' => $items];
                $data = \Drupal::service('renderer')->render($render);
            }
            return new JsonResponse(array('data' => $data));
        } else {
            // no access
            return new JsonResponse(array('data' => null));
        }
    }

    /**
     * Return project edit
     *
     */
    public function edit(Request $request, $id) {
        
    }

    /**
     * Return project tracker data
     *
     */
    public function tracker(Request $request) {
        $id = $request->query->get('id');
        $first = null;
        $today = time() - 86400;
        if (isset($id)) {


            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid', 'name']);
            $users = $query->execute()->fetchAllKeyed();
            $uid = \Drupal::currentUser()->id();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_tracker', 't')
                    ->fields('t', ['uid', 'stamp', 'action'])
                    ->condition('id', $id);
            $query->innerJoin('ek_project', 'p', 't.pcode=p.pcode');
            $query->orderBy('stamp', "DESC");
            $data = $query->execute();


            $t1 = "<h3>--- [" . $this->t('Today') . "] ---</h3>";
            $t2 = "<h3>--- [" . $this->t('Earlier') . "] ---</h3>";
            $first = '';
            $i = 0;
            $name_ = '';

            while ($d = $data->fetchObject()) {
                if ($d->uid != $uid) {
                    isset($users[$d->uid]) ? $name = $users[$d->uid] : $name = $this->t('Unknown');
                } else {
                    $name = $this->t('You');
                }

                $on = date('l jS \of F Y h:i A', $d->stamp);

                if ($i == 0) {
                    $first = str_replace('edit', '', $d->action);
                    $first = str_replace(' ', '_', trim($first));
                }

                if (strcmp($name, $name_) || strcmp($d->action, $action)) {
                    //don't show duplicate lines
                    $name_ = $name;
                    $action = $d->action;
                    if ($d->stamp >= $today) {
                        $t1 .= '<li>' . $action . '  &nbsp<span title="' . $on . '">(' . $name_ . ')</span></li>';
                    } else {
                        $t2 .= '<li>' . $action . '  &nbsp<span title="' . $on . '">(' . $name_ . ')</span></li>';
                    }
                }
                $i++;
            }
        }

        return new JsonResponse(array('data' => $t1 . $t2, 'field' => $first));
    }

    /**
     * ajax drag & drop
     * move document from 1 section to another
     *
     */
    public function DragDrop(Request $request) {
        $from = explode("-", $request->get('from'));

        if ($request->get('move') == 'folder') {
            switch ($request->get('to')) {
                case 's1':
                case 'ps1':
                    $folder = 'ap';
                // no break
                case 's3':
                case 'ps3':
                    $folder = 'com';
                    break;
                case 's5':
                case 'ps5':
                    $folder = 'fi';
                    break;
            }
            $fields = array('folder' => $folder);
            $move = Database::getConnection('external_db', 'external_db')
                    ->update('ek_project_documents')
                    ->condition('id', $from[1])
                    ->fields($fields)
                    ->execute();
            if ($move) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_project_documents', 'd');

                $data = $query
                        ->fields('d', array('pcode', 'filename'))
                        ->condition('d.id', $from[1], '=')
                        ->execute()
                        ->fetchObject();
                $fields = array(
                    'pcode' => $data->pcode,
                    'uid' => \Drupal::currentUser()->id(),
                    'stamp' => time(),
                    'action' => 'move' . ' ' . $data->filename
                );
                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_project_tracker')
                        ->fields($fields)->execute();
            }
        }

        if ($request->get('move') == 'subfolder') {
            $fields = array('sub_folder' => $request->get('to'));
            $move = Database::getConnection('external_db', 'external_db')
                    ->update('ek_project_documents')
                    ->condition('id', $from[1])
                    ->fields($fields)
                    ->execute();
        }
        return new Response('', 204);
    }

    /**
     * view meta data from file
     * 
     * @param id: file id
     * @return array
     *
     */
    public function fileData($id) {

        if (ProjectData::validate_file_access($id)) {
            $query = $this->extdb
                ->select('ek_project_documents', 'd')
                ->fields('d');
            $query->leftJoin('ek_project', 'p', 'd.pcode = p.pcode');
            $query->fields('p', ['id','pcode','main','subcount']);
            $query->condition('d.id', $id);
            $file = $query->execute()->fetchObject();
            $file_managed = ProjectData::file_owner($file->uri);
            $owner = '';
            if($file_managed){
                $user = \Drupal\user\Entity\User::load($file_managed->uid);
                if($user){
                    $owner = $user->getAccountName();
                }
            }
            $parts = explode(".", $file->filename);
            $extension = array_pop($parts);
            $icon = '';
            if (file_exists(drupal_get_path('module', 'ek_projects') . '/art/icons/' . $extension . ".png")) {
                $icon = drupal_get_path('module', 'ek_projects') . '/art/icons/' . $extension . ".png";
            }
            $data = [
                'filename' => $file->filename,
                'filetype' => $extension,
                'icon' => $icon,
                'size' => round($file->size / 1000, 0) . " Kb",
                'date' => date('Y-m-d', $file->date),
                'url' => file_create_url($file->uri),
                'comment' => ['#markup' => $file->comment],
                'folder' => $file->sub_folder,
                'owner' => $owner,
            ];
            $access = [0 => ['name' => t('default')]];
            if($file->share != 0) {
                $shares = explode(",",$file->share);
                $access = [];
                foreach($shares as $key => $val){
                    $user = \Drupal\user\Entity\User::load($val);
                    if($user){
                        $avatar = ($user->get('user_picture')->entity) ? $user->get('user_picture')->entity->url(): null;
                        if(!$avatar) {
                            $avatar = file_create_url(drupal_get_path('module','ek_admin') . "/art/avatar/default.jpeg");
                        }
                        $access[] = ['name' => $user->getAccountName(), 'avatar' => $avatar];
                    }
                }
            }
            $data['access'] = $access;
            /* sub project filter */
            if ($file->main != NULL || $file->subcount > 0){
                $data['linked'] = 1;
                $data['sub'] = [];
                $query = $this->extdb
                        ->select('ek_project', 'p')
                        ->fields('p', ['id','pcode','pname']);
                if($file->subcount > 0){
                    $or = $query->orConditionGroup()
                        ->condition('pcode', $file->pcode . '_sub%', 'LIKE')
                        ->condition('main', $file->id);
                    $query->condition($or);
                }
                if($file->main != NULL){
                    $c = explode('_',$file->pcode);
                    $or = $query->orConditionGroup()
                        ->condition('id', $file->main)
                        ->condition('pcode', $c[0] . '_' . $c[1] . '%', 'LIKE');
                    $query->condition($or);
                    $query->condition('pcode', $file->pcode, '<>');
                }
                $sub = $query->execute();
                $data['sub'][$file->pcode] = $file->pcode;
                while ($l = $sub->fetchObject()) {
                    $data['sub'][$l->pcode] = $l->pcode;
                }
                $p = [
                    'id' => $id,
                    'name' => $file->filename,
                    'options' => $data['sub'],
                ];
                $data['move_file'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\MoveFile',$p);
            }
            
            $content = [
                '#items' => $data,
                '#theme' => 'file_data',
                '#attached' => [],
            ];
        }
        return $content;
    }

    /**
     * delete a file from project
     * Return ajax delete confirmation alert
     * @param
     *  id: file id
     * @return
     *  ajax dialog
     *
     */
    public function deleteFile($id) {

        $file = Database::getConnection('external_db', 'external_db')
                        ->select('ek_project_documents', 'd')
                        ->fields('d', ['filename'])
                        ->condition('id', $id)
                        ->execute()->fetchField();
        $content = ['content' =>
            ['#markup' =>
                "<p>" . $file . "</p>"
                . "<a href='delete_file_confirmed/" . $id . "' class='use-ajax'>"
                . $this->t('delete') . "</a>"]
        ];

        $response = new AjaxResponse();

        $title = $this->t('Confirm');
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        $response->addCommand(new OpenModalDialogCommand($title, $content));


        return $response;
    }

    /**
     * delete a file from project
     * Return ajax delete confirmation alert
     * @param
     *  id: file id
     * @return
     *  Json response true if file is deleted
     */
    public function deleteConfirmed($id) {
        $response = new Response('', 204); //default return response

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_documents', 'd')
                ->fields('d', ['filename', 'pcode', 'uri', 'deny'])
                ->condition('d.id', $id);
        $query->leftJoin('ek_project', 'p', 'd.pcode = p.pcode');
        $query->fields('p', ['id']);
        $p = $query->execute()->fetchObject();

        if (ProjectData::validate_access($p->id)) {

            //control deny access
            if (!in_array(\Drupal::currentUser()->id(), explode(',', $p->deny))) {
                $fields = array(
                    'fid' => 0,
                    'uri' => date('U'),
                    'comment' => $this->t('deleted by') . ' ' . \Drupal::currentUser()->getAccountName(),
                    'date' => time()
                );
                //delete from main data DB
                $delete = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_documents')
                        ->fields($fields)->condition('id', $id)
                        ->execute();

                if ($delete) {
                    //Set file status as tmp in file managed DB for recovering process if needed
                    //file will be delete by cron as per settings
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $p->uri);
                    $fid = $query->execute()->fetchField();
                    //$query = "SELECT fid FROM {file_managed} WHERE uri=:u";
                    //$file = db_query($query, [':u' => $p->uri])->fetchObject();
                    if ($fid) {
                        $obj = \Drupal\file\Entity\File::load($fid);
                        $obj->setTemporary();
                        $obj->save();
                    }
                }

                $this->moduleHandler()->invokeAll('project_doc_delete', [['pcode' => $p->pcode, 'id' => $id]]);

                $log = $p->pcode . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
                \Drupal::logger('ek_projects')->notice($log);
                $action = 'delete' . ' ' . $p->filename;
                if (strlen($action) > 255) {
                    $action = substr($action, 0, 250) . "...";
                }
                $fields = array(
                    'pcode' => $p->pcode,
                    'uid' => \Drupal::currentUser()->id(),
                    'stamp' => time(),
                    'action' => $action
                );
                Database::getConnection('external_db', 'external_db')->insert('ek_project_tracker')
                        ->fields($fields)->execute();

                $response = new AjaxResponse();
                $response->addCommand(new CloseDialogCommand());
            }
        }

        return $response;
    }

    /**
     * AJAX callback handler for Ajax Dialog Form.
     */
    public function modal($param) {
        return $this->dialog(true, $param);
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function nonModal($param) {
        return $this->dialog(false, $param);
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
    protected function dialog($is_modal = false, $param = null) {
        $param = explode('|', $param);
        $content = [];
        switch ($param[0]) {

            case 'upload':
                $title = ucfirst($this->t($param[0]));
                $id = $param[1] . '|' . $param[2] . '|' . $param[3] . '|' . $param[4];
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\UploadForm', $id);
                $options = array('width' => '25%',);
                break;

            case 'task':
                $title = ucfirst($this->t($param[0]));
                if ($param[2] == '') {
                    $param[2] = 0;
                }
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\TaskProject', $param[1], $param[2]);
                $options = array('width' => '40%',);

                break;

            case 'mail':
                $title = ucfirst($this->t($param[0]));
                $data = array(
                    $param[1], //doc id
                    $param[2], //tb ref
                    'open' => true,
                );
                $data = serialize($data);
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $data);
                $options = array('width' => '30%',);

                break;

            case 'access':
                $title = ucfirst($this->t($param[0]));
                $id = $param[1];
                $type = $param[2];
                $content['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\ProjectAccessEdit', $id, $type);
                $options = array('width' => '25%',);
                break;

            case 'notification':
                $title = ucfirst($this->t($param[0]));
                $id = $param[1];
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\Notification', $id);
                $options = array('width' => '30%',);
                break;
            case 'field':
                $title = ucfirst($this->t($param[0]));
                $id = $param[2];
                $field = $param[1];
                $width = isset($param[3]) ? $param[3] : '30%';
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\ProjectFieldEdit', $id, $field);
                $options = array('width' => $width, 'height' => '300');
                break;
            case 'extranet':
                $title = ucfirst($this->t($param[0]));
                $id = $param[1];
                $width = isset($param[2]) ? $param[2] : '50%';
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_extranet\Form\Edit', $id);
                $options = array('width' => $width);
                break;
            case 'followers':
                $title = ucfirst($this->t($param[0]));
                $id = $param[1];
                $width = isset($param[2]) ? $param[2] : '30%';

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_project', 'p');
                $query->fields('p', ['notify']);
                $query->condition('id', $id, '=');
                $data = $query->execute();
                $notify = explode(',', $data->fetchField());
                $list = '';
                $query = Database::getConnection()->select('users_field_data', 'u');
                $query->fields('u', ['uid', 'name']);
                $users = $query->execute()->fetchAllKeyed();
                foreach ($notify as $value) {
                    if ($value == \Drupal::currentUser()->id()) {
                        $list .= '<li>' . $this->t('Me') . '</li>';
                    } else {
                        $username = isset($users[$value]) && $users[$value] != '' ? $users[$value] : $this->t('Unknown') . ' ' . $value;
                        $list .= '<li>' . $username . '</li>';
                    }
                }
                $content['content'] = ['#markup' => '<ul>' . $list . '</ul>'];
                $options = array('width' => $width);
                $is_modal == true;
                break;
                
            case 'split':
                $title = $this->t('Split');
                $id = $param[1];
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\SplitProject', $id);
                $options = array('width' => '30%',);
                break;
        }

        $response = new AjaxResponse();

        $content['cancel'] = array(
            '#type' => 'link',
            '#title' => 'Cancel',
            '#url' => '',
            '#attributes' => array(
                // This is a special class to which JavaScript assigns dialog closing
                // behavior.
                'class' => array('dialog-cancel'),
            ),
        );
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';


        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content, $options);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-text-dialog-wrapper-' . $param[2];
            $response->addCommand(new OpenDialogCommand($selector, $title, $content));
        }
        return $response;
    }

    /**
     * Edit the notify field in project
     * @return array 1 = follow 0 = do not follow
     */
    public function edit_notify_me() {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p')
                ->fields('p', ['notify'])
                ->condition('id', $_POST['id']);
        $notify = $query->execute()->fetchField();
        $action = 0;
        if ($notify == null) {
            $notify = \Drupal::currentUser()->id();
            $action = 1;
        } else {
            $notify = explode(',', $notify);

            if (in_array(\Drupal::currentUser()->id(), $notify)) {
                if (($key = array_search(\Drupal::currentUser()->id(), $notify)) !== false) {
                    unset($notify[$key]);
                }
            } else {
                array_push($notify, \Drupal::currentUser()->id());
                $action = 1;
            }
            $notify = implode(',', $notify);
        }

        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_project')
                ->fields(array('notify' => $notify))
                ->condition('id', $_POST['id'])
                ->execute();

        if ($update) {
            \Drupal\Core\Cache\Cache::invalidateTags(['project_last_block']);
            return new JsonResponse(['action' => $action]);
        }
    }

    /**
     * Edit the archive field in project
     *
     */
    public function edit_archive($id) {
        $query = "SELECT archive FROM {ek_project} WHERE id=:id";
        $archive = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchField();

        $status = ($archive == 0) ? 1 : 0;

        $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')
                        ->fields(array('archive' => $status))
                        ->condition('id', $id)->execute();

        if ($update) {
            $response = new AjaxResponse();
        }
        $a = ['0' => $this->t('no'), '1' => $this->t('yes')];
        return $response->addCommand(new HtmlCommand('#arch' . $id, $a[$status]));
        //return new JsonResponse(TRUE);
    }

    /**
     * Return ajax search autocomplete data
     * used in forms
     * @param $type : all|main|sub
     * @param $status : 0(all) | 1(open) | 2(awarded) | 3(completed) | 4(closed)
     * @return array json format
     */
    public function lookupProject(Request $request, $level = null, $status = 0) {
        $text = '%' . $request->query->get('q') . '%';

        if ($level == "main") {
            $level = 'Main project';
        } elseif ($level == "sub") {
            $level = 'Sub project';
        } else {
            $level = '%';
        }

        if ($status == "1") {
            $status = 'open';
        } elseif ($status == "2") {
            $status = 'awarded';
        } elseif ($status == "3") {
            $status = 'completed';
        } elseif ($status == "4") {
            $status = 'closed';
        } else {
            $status = '%';
        }

        $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');

        $or = $query->orConditionGroup();
        $or->condition('pcode', $text, 'like');
        $or->condition('pname', $text, 'like');


        $data = $query
                ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date', 'archive'))
                ->condition($or)
                ->condition('level', $level, 'like')
                ->condition('status', $status, 'like')
                ->execute();

        $name = array();
        while ($r = $data->fetchAssoc()) {
            if (strlen($r['pname']) > 15) {
                $desc = substr($r['pname'], 0, 15) . "...";
            } else {
                $desc = $r['pname'];
            }
            $name[] = $r['id'] . " " . $r['pcode'] . " (" . $r['status'] . ") " . $desc;
        }
        return new JsonResponse($name);
    }

    /**
     * Create or edit a task
     * Currently the task form is called via off canvas dialog
     */
    public function TaskProject($pid, $id) {
        if (ProjectData::validate_access($pid, \Drupal::currentUser()->id())) {
            $access = AccessCheck::GetCountryByUser();
            $param = [];
            $param['pid'] = $pid;
            $param['id'] = $id;
            $param['delete'] = \Drupal::currentUser()->hasPermission('delete_project_task');
            if ($id != null && $id > 0) {
                // edit
                $param['edit'] = true;

                $query = $this->extdb->select('ek_project_tasks', 't');
                $query->leftJoin('ek_project', 'p', 'p.pcode=t.pcode');
                $or1 = $query->orConditionGroup();
                $or1->condition('cid', $access, 'IN');

                $param['data'] = $query
                        ->fields('t')
                        ->fields('p', ['id', 'pcode'])
                        ->condition($or1)
                        ->condition('p.id', $pid, '=')
                        ->condition('t.id', $id, '=')
                        ->execute()
                        ->fetchObject();

                $param['owner'] = (\Drupal::currentUser()->id() == $param['data']->uid) ? 1 : 0;
            } else {
                // new
                $param['edit'] = false;
                $param['data'] = $this->extdb->select('ek_project', 'p')
                        ->fields('p', ['pcode'])
                        ->condition('id', $pid)
                        ->execute()
                        ->fetchObject();
            }

            return $this->formBuilder->getForm('Drupal\ek_projects\Form\TaskProject', $param);
        } else {
            $url = Url::fromRoute('ek_projects_view', ['id' => $pid])->toString();
            $items['type'] = 'edit';
            $items['message'] = ['#markup' => $this->t('@document cannot be edited.', array('@document' => $this->t('Task')))];
            $items['description'] = ['#markup' => $this->t('Access denied')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">Project</a>.', ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];

            return $build;
        }
    }

    /**
     * List current tasks per project
     *
     */
    //todo: add filter to project

    public function TaskList($pcode) {
        $header = array(
            'color' => array(
                'data' => '',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'event' => array(
                'data' => $this->t('Event'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'task' => array(
                'data' => $this->t('Task'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'period' => array(
                'data' => $this->t('From/to'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'user' => array(
                'data' => $this->t('In charge'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'operations' => '',
        );
        $options = array();
        $query = $this->extdb->select('ek_project_tasks', 't');
        $query->join('ek_project', 'p', 'p.pcode=t.pcode');
        $data = $query->fields('t', array('id', 'event', 'task', 'start', 'end', 'uid', 'gid', 'completion_rate', 'color'))
                ->fields('p', array('id'))
                ->condition('t.pcode', $pcode, '=')
                ->extend('Drupal\Core\Database\Query\TableSortExtender')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(25)->orderBy('id', 'ASC')
                ->execute();

        while ($r = $data->fetchObject()) {
            $period = date('Y-m-d', $r->start) . ' <br/>' . date('Y-m-d', $r->end);
            if (\Drupal::currentUser()->id() == $r->uid) {
                $name = $this->t('Myself');
            } else {
                $acc = \Drupal\user\Entity\User::load($r->uid);
                $name = '';
                if ($acc) {
                    $name = $acc->getDisplayName();
                }
            }

            if ($r->end != null && date('U') > $r->end && $r->completion_rate < 100) {
                $status = "<span class='red'>" . $this->t('expired') . "</span>";
            } else {
                $status = "<meter title='" . $r->completion_rate . "%'  value=" . $r->completion_rate . " max=100>". $r->completion_rate." %</meter>";
            }

            $task = $r->task;
            if (strlen($task) > 30) {
                $task = substr($task, 0, 30) . '...';
            }
            $options[$r->id] = [
                'color' => ['data' => ['#markup' => ''], 'style' => ['background-color:' . $r->color]],
                'event' => ['data' => ['#markup' => $r->event]],
                'task' => ['data' => ['#markup' => $task], 'title' => $r->task],
                'period' => ['data' => ['#markup' => $period]],
                'user' => $name,
                'status' => ['data' => ['#markup' => $status]],
            ];


            $destination = ['destination' => '/projects/project/' . $r->p_id . '?s2=true#ps2'];
            $link = Url::fromRoute('ek_projects_task', ['pid' => $r->p_id, 'id' => $r->id], ['query' => $destination]);
            $links['form'] = [
                'title' => $this->t('Edit'),
                'url' => $link,
                'attributes' => [
                    'class' => ['use-ajax'],
                    'data-dialog-type' => 'dialog',
                    'data-dialog-renderer' => 'off_canvas',
                    'data-dialog-options' => Json::encode([
                        'width' => '30%',
                    ]),
                ]
            ];
            if (\Drupal::currentUser()->hasPermission('delete_project_task')) {
                $links['del'] = [
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_projects_task_delete', ['id' => $r->id]),
                ];
            }

            $options[$r->id]['operations']['data'] = [
                '#type' => 'operations',
                '#links' => $links,
            ];
        }


        $build['tasks_table'] = [
            '#type' => 'table',
            '#title' => $this->t('Tasks') . ' ' . $pcode,
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => ['id' => 'tasks_table'],
            '#empty' => $this->t('No task available'),
            '#attached' => [
                'library' => ['ek_projects/ek_projects_css'],
            ],
        ];

        $build['pager'] = [
            '#type' => 'pager',
        ];

        return $build;
    }

    /**
     * Delete tasks per project
     *
     */
    public function DeleteTask(Request $request, $id) {
        return $items['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\DeleteTaskForm', $id);
    }

    /**
     * Return ajax user autocomplete data
     * deprecated: use default ek_admin resources
     */
    public function userautocomplete(Request $request) {
        $text = $request->query->get('term');
        $matches = [];

        $query = $this->entityTypeManager->getStorage('user')->getQuery();
        $or = $query->orConditionGroup();
        $or->condition('name', $text, 'STARTS_WITH');
        $or->condition('mail', $text, 'STARTS_WITH');
        $query->condition($or);
        $query->range(0, 10);
        $uids = $query->execute();

        $controller = $this->entityTypeManager->getStorage('user');
        foreach ($controller->loadMultiple($uids) as $account) {
            if (!$account->isAnonymous()) {
                $matches[] = array('value' => $account->getAccountName(), 'label' => $account->getEmail());
            }
        }

        return new JsonResponse($matches);
    }

}
