<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Controller\ProjectController.
 */

namespace Drupal\ek_projects\Controller;

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
use Drupal\block\Entity\Block;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\FinanceSettings;

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

            if (isset($_SESSION['pjfilter']['keyword']) && $_SESSION['pjfilter']['keyword'] != NULL 
                    && $_SESSION['pjfilter']['keyword'] != '%') {

                if (is_numeric($_SESSION['pjfilter']['keyword'])) {

                    $id1 = '%-' . trim($_SESSION['pjfilter']['keyword']) . '%';
                    $id2 = '%-' . trim($_SESSION['pjfilter']['keyword']) . '-sub%';

                    $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');

                    $or = db_or();
                    $or->condition('pcode', $id1, 'like');
                    $or->condition('pcode', $id12, 'like');
                    $data = $query
                            ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date','archive'))
                            ->condition($or)
                            ->extend('Drupal\Core\Database\Query\TableSortExtender')
                            ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                            ->limit(10)->orderBy('id', 'ASC')
                            ->execute();
                } else {

                    $key = Xss::filter($_SESSION['pjfilter']['keyword']);
                    $keyword1 = trim($key) . '%';
                    $keyword2 = '%' . trim($key) . '%';
                    $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');
                    $query->leftJoin('ek_project_documents', 'd', 'p.pcode=d.pcode');
                    $query->leftJoin('ek_project_description', 't', 'p.pcode=t.pcode');
                    $or = db_or();
                    $or->condition('p.pname', $keyword2, 'like');
                    $or->condition('d.filename', $keyword2, 'like');
                    $or->condition('t.project_description', $keyword2, 'like');
                    $data = $query
                            ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date','archive'))
                            ->condition($or)
                            ->extend('Drupal\Core\Database\Query\TableSortExtender')
                            ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                            ->limit(10)->orderBy('id', 'ASC')
                            ->execute();
                }
            } else {
                if ($_SESSION['pjfilter']['cid'] == 0) {
                    $cid = '%';
                } else {
                    $cid = $_SESSION['pjfilter']['cid'];
                }

                $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');
                $query->leftJoin('ek_project_description', 'd', 'd.pcode=p.pcode');
                    $query
                        ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date','archive'))
                        ->condition('cid', $cid, 'like')
                        ->condition('category', $_SESSION['pjfilter']['type'], 'like')
                        ->condition('status', $_SESSION['pjfilter']['status'], 'like')
                        ->condition('client_id', $_SESSION['pjfilter']['client'], 'like');
                if( $_SESSION['pjfilter']['supplier'] != '%') {
                        $or = db_or();
                        $or->condition('supplieroffer', $_SESSION['pjfilter']['supplier'] . ',%', 'like');
                        $or->condition('supplieroffer', '%,' . $_SESSION['pjfilter']['supplier'] . ',%', 'like');
                        $or->condition('supplieroffer', '%,' . $_SESSION['pjfilter']['supplier'] , 'like');
                        $or->condition('supplieroffer', $_SESSION['pjfilter']['supplier'] , '=');
                        $query->condition($or);
                        
                }
                
                $data = $query
                        ->extend('Drupal\Core\Database\Query\TableSortExtender')
                        ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                        ->limit(20)->orderBy('id', 'ASC')
                        ->execute();
            }

            $i = 0;
            $archive = [0 => t('no'), 1 => t('yes')];
            $excel = [];
            while ($r = $data->fetchObject()) {
                if(in_array($r->cid, $access)) {//filter access by country
                $i++;
                array_push($excel, $r->id);
                $pcode = ProjectData::geturl($r->id);
                $country = Database::getConnection('external_db', 'external_db')
                                ->query("SELECT name FROM {ek_country} WHERE id=:cid", array(':cid' => $r->cid))->fetchField();
                $category = Database::getConnection('external_db', 'external_db')
                                ->query("SELECT type FROM {ek_project_type} WHERE id=:t", array(':t' => $r->category))->fetchField();

                $route = Url::fromRoute('ek_projects_archive', ['id' => $r->id], array())->toString();
                $archive_button = "<a id='arch".$r->id."' title='" . t('change archive status') . "' href='" . $route . "' class='use-ajax'>" . $archive[$r->archive] . '</a>';
                                        
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
            $build['excel'] = ['#markup' => "<br/><a href='" . $url . "'>" . t('Excel') . "</a>"];
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
        
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);
            $query = "SELECT * FROM {ek_project} p "
                    . "LEFT JOIN {ek_project_description} d ON p.pcode=d.pcode "
                    . "LEFT JOIN {ek_project_finance} f on p.pcode=f.pcode "
                    . "WHERE FIND_IN_SET (id, :c ) ORDER by p.id";
            $data = Database::getConnection('external_db', 'external_db')
                                ->query($query, [':c' => implode(',', $param)]);

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
     * Return project view 
     *
     */
    public function view(Request $request, $id) {

        $items = array();
        Cache::invalidateTags(['project_view_block']);


        $edit_icon = "&nbsp<img src='../../" . drupal_get_path('module', 'ek_projects') . "/art/edit.png' />";
        //$edit_icon = "[/]";
        if (!ProjectData::validate_access($id)) {
            return $items['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\AccessRequest', $id);
        } else {

            //$items['filter_project'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\Filter'); 
            $items['data'] = array();

            $pcode = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT pcode from {ek_project} WHERE id=:id", array(':id' => $id))->fetchField();

            $settings = ['id' => $id,];

            $sections = ProjectData::validate_section_access(\Drupal::currentUser()->id());
            $data = array();

            for ($i = 1; $i < 6; $i++) {
                if (in_array($i, $sections))
                    $data['section_' . $i] = 1;
            }


            /*
             * main data collection
             */

            $query = "SELECT * from {ek_project} WHERE id=:id";
            $data['project'] = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchAll();

            $query = "SELECT type from {ek_project_type} WHERE id=:id";
            $data['type'] = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data['project'][0]->category))->fetchField();

            $code = str_replace('/', '-', $pcode);
            $code = explode("-", $code);
            $code = array_reverse($code);
            $code_serial = $code[0];
            $sub = NULL;
            
            if ($data['project'][0]->level == 'Main project' && $data['project'][0]->subcount > 0) {
                $query = "SELECT id from {ek_project} WHERE main = :id and pcode <> :c2 order by id";
                $sub = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id, ':c2' => $pcode));
                    while ($l = $sub->fetchObject()) {
                        $data['sub'][] = ProjectData::geturl($l->id);
                    }
            } elseif ($data['project'][0]->level == 'Sub project') {
                $data['sub'][] = ProjectData::geturl($data['project'][0]->main);
            }

            /*
             * manage the edit mode 
             */
            if ($data['project'][0]->editor == 0 || $data['project'][0]->editor == \Drupal::currentUser()->id()) {
                $data['project'][0]->edit_mode = '<button class="btn _edit" id="edit_mode"><span >' . t('edit mode') . '</span>'
                        . ' <i class="fa fa-pencil"></i></button>';
            }

            /*
             * manage the notify mode 
             */
            $notify = explode(',', $data['project'][0]->notify);
            if (in_array(\Drupal::currentUser()->id(), $notify)) {
                $val = 'follow';
                $cl = 'follow';
                $cl2 = "fa-check-square-o";
                
            } else {
                $cl = '_follow';
                $cl2 = 'fa-square-o';
            }

            $data['project'][0]->keep_notify = '<button class="btn ' 
                    . $cl . '" id="edit_notify" /> ' . t('follow') . ' '
                    . '<i  id="edit_notify_i" class="fa '. $cl2 .'" aria-hidden="true"></i>'
                    . '</button>';


            /*
             * convert last view data
             */
            $last = explode('|', $data['project'][0]->last_modified);
            $query = "SELECT name from {users_field_data} WHERE uid=:uid";
            $name = db_query($query, array(':uid' => $last[1]))->fetchField();
            $on = date('l jS \of F Y h:i A', $last[0]);
            $data['project'][0]->last_modified = $name . ' (' . t('on') . ' ' . $on . ')';
            //update new last view
            $last_modified = time() . '|' . \Drupal::currentUser()->id();
            Database::getConnection('external_db', 'external_db')
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
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_tracker')->fields($fields)->execute();

            //system log view
            $a = array('@u' => \Drupal::currentUser()->getUsername(), '@d' => $pcode);
            $log = t("User @u has opened project @d", $a);
            \Drupal::logger('ek_projects')->notice($log);


            $prio = array(0 => t('not set'), 1 => t('low'), 2 => t('medium'), 3 => t('high'));
            $data['project'][0]->priority = $prio[$data['project'][0]->priority];

            /*
             * create a link to edit title
             */
            if (\Drupal::currentUser()->hasPermission('admin_projects')) {
                $param_edit = 'field|pname|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_pname = ( '<a title="' . t('edit name') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                $param_edit = 'field|owner|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_owner = ( '<a title="' . t('edit owner') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                $param_edit = 'field|client_id|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['project'][0]->edit_client_id = ( '<a title="' . t('edit client') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
            }

            /*
             * create a link to edit users access for owner only
             */
            if (\Drupal::currentUser()->id() == $data['project'][0]->owner || \Drupal::currentUser()->hasPermission('admin_projects')) {
                $param_access = 'access|' . $id . '|project';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_access])->toString();
                $data['project'][0]->access = t('<a href="@url" class="@c" >manage access</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            }

            /*
             * create a link for notification
             */
            $param_note = 'notification|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_note])->toString();
            $data['project'][0]->notification = t('<a href="@url" class="@c" >notification</a>', array('@url' => $link, '@c' => 'use-ajax blue notification'));
            /*
             * create a link to create a task
             */

            if ($this->moduleHandler->moduleExists('ek_extranet')) {
                /*
                 * create a link for extranet management
                 */
                $query = "SELECT * FROM {ek_extranet_pages} WHERE pcode=:s";
                $ext = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':s' => $pcode])
                        ->fetchObject();
                if ($ext && $ext->id > 0) {
                    $param_edit = 'extranet|' . $ext->id;
                    $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                    $data['project'][0]->extranet = t('<a href="@url" class="@c" >Edit extranet page</a>', array('@url' => $link, '@c' => 'use-ajax blue notification'));
                } else {
                    $link = Url::fromRoute('ek_extranet.make_page', array('id' => $pcode), array())->toString();
                    $data['project'][0]->extranet = t('<a href="@url" class="@c" >Create extranet page</a>', array('@url' => $link, '@c' => 'blue notification'));
                }
            }
            $param_edit = 'task|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['project'][0]->new_project_task = t('<a href="@url" class="@c" >New task</a>', array('@url' => $link, '@c' => 'use-ajax blue notification'));

            $data['project'][0]->task_list = self::TaskList($pcode);
            /*
             * create a link to addresses book
             */
            $query = "SELECT * from {ek_address_book} WHERE id=:id";
            $data['client'] = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data['project'][0]->client_id))
                    ->fetchAll();

            $data['client'][0]->url = \Drupal\ek_address_book\AddressBookData::geturl($data['client'][0]->id);

            /*
             * create a link to edit status
             */
            $param_edit = 'field|status|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['project'][0]->edit_statusUrl = $link;
            $data['project'][0]->edit_status = ( '<a title="' . t('edit status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

            /*
             * create a link to edit priority
             */
            $param_edit = 'field|priority|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['project'][0]->edit_priority = ( '<a title="' . t('edit priority') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


            /*
             * user table
             */
            $query = "SELECT * from {users_field_data} WHERE uid=:uid";
            $data['user'] = db_query($query, array(':uid' => $data['project'][0]->owner))->fetchAll();
            $param_edit = 'followers|' . $id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
            $data['followers'][0]->url = $link;
            $data['followers'][0]->list = ( '<a href="' . $link . '" class="use-ajax blue" >' . t('Followers') . '</a>');

            /*
             * description table
             */
            if (in_array(1, $sections)) {
                $query = "SELECT * from {ek_project_description} WHERE pcode=:p";
                $data['description'] = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':p' => $pcode))
                        ->fetchAll();

                $data['description'][0]->project_description = html_entity_decode($data['description'][0]->project_description, ENT_QUOTES, "utf-8");
                $data['description'][0]->project_comment = html_entity_decode($data['description'][0]->project_comment, ENT_QUOTES, "utf-8");
                
                
                /*
                 * suppliers
                 */
                
                $data['suppliers'] = '';
                if($data['description'][0]->supplieroffer){
                    $query = "SELECT id,name FROM {ek_address_book} WHERE FIND_IN_SET (id, :s )";
                    $suppliers = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':s' => $data['description'][0]->supplieroffer));
                   
                    WHILE($s = $suppliers->fetchObject()){
                        
                        $data['suppliers'][] = [
                            'name' => $s->name,
                            'url' => \Drupal\ek_address_book\AddressBookData::geturl($s->id),
                        ];
                        ;
                    }
                    $data['description'][0]->supplieroffer = $data['suppliers'];
                }
                /*
                 * create a link to edit suppliers
                 */
                $param_edit = 'field|supplieroffer|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_supplieroffer = ( '<a title="' . t('edit supplier') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date submission
                 */
                $param_edit = 'field|submission|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_submission = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date validation
                 */
                $param_edit = 'field|validation|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_validation = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date deadline
                 */
                $param_edit = 'field|deadline|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_deadline = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date start_date
                 */
                $param_edit = 'field|start_date|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_start_date = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit date completion
                 */
                $param_edit = 'field|completion|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_completion = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_1
                 */
                $param_edit = 'field|perso_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_1 = ( '<a title="' . t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_2
                 */
                $param_edit = 'field|perso_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_2 = ( '<a title="' . t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit perso_3
                 */
                $param_edit = 'field|perso_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_perso_3 = ( '<a title="' . t('edit in charge') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to edit repo_1
                 */
                $param_edit = 'field|repo_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_1 = ( '<a title="' . t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit repo_2
                 */
                $param_edit = 'field|repo_2|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_2 = ( '<a title="' . t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit repo_3
                 */
                $param_edit = 'field|repo_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_repo_3 = ( '<a title="' . t('edit reponsibility') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to edit task_1
                 */
                $param_edit = 'field|task_1|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_1 = ( '<a title="' . t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit task_2
                 */
                $param_edit = 'field|task_2|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_2 = ( '<a title="' . t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit task_3
                 */
                $param_edit = 'field|task_3|' . $id;
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_task_3 = ( '<a title="' . t('edit task') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit project_description
                 */
                $param_edit = 'field|project_description|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_project_description = ( '<a title="' . t('edit description') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit project_comment
                 */
                $param_edit = 'field|project_comment|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['description'][0]->edit_project_comment = ( '<a title="' . t('edit comment') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
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
                $param_upload = 'upload|' . $pcode . '|doc|com';
                
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_upload])->toString();
                $data['communication'][0]->uploadUrl = $link;
                $data['communication'][0]->upload = t('<a href="@url" class="@c" >upload new file</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            }

            /*
             * reports links
             */
            if ($this->moduleHandler->moduleExists('ek_intelligence')) {
                $query = "SELECT id,serial,edit,description FROM {ek_ireports} WHERE pcode=:p";
                $irep = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':p' => $data['project'][0]->pcode));
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
                $query = "SELECT * from {ek_project_shipment} WHERE pcode=:p";
                $data['logistic'] = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':p' => $pcode))->fetchAll();

                /*
                 * create a link to edit first_ship
                 */
                $param_edit = 'field|first_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_first_ship = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit second_ship
                 */
                $param_edit = 'field|second_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_second_ship = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit third_ship
                 */
                $param_edit = 'field|third_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_third_ship = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit four_ship
                 */
                $param_edit = 'field|four_ship|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_four_ship = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit status
                 */
                $param_edit = 'field|ship_status|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_ship_status = ( '<a title="' . t('edit status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit last_delivery
                 */
                $param_edit = 'field|last_delivery|' . $id . '|25%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['logistic'][0]->edit_last_delivery = ( '<a title="' . t('edit date') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                if ($this->moduleHandler->moduleExists('ek_logistics')) {
                    /*
                     * extact receiving
                     */
                    $query = "SELECT id,serial,head,ddate,status,supplier FROM {ek_logi_receiving}  WHERE pcode=:p AND type=:t ";
                    $deliveries = Database::getConnection('external_db', 'external_db')->query($query, array(':p' => $pcode, ':t' => 'RR'));

                    $delivery_list = array();
                    $i = 0;
                    $delivery_status = array('0' => t('open'), '1' => t('printed'), '2' => t('invoiced'), '3' => t('posted'));

                    while ($delivery = $deliveries->fetchObject()) {

                        $href = Url::fromRoute('ek_logistics_receiving_print_share', ['id' => $delivery->id])->toString();
                        $link = ( '<a title="' . t('pdf') . '" href="' . $href . '" class="blue" >' . t('print') . '</a>');
                        $client = Database::getConnection('external_db', 'external_db')
                                        ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $delivery->supplier))->fetchField();

                        $delivery_list[$i] = array(
                            'serial' => $delivery->serial,
                            'status' => $delivery_status[$delivery->status],
                            'client' => $client,
                            'date' => $delivery->ddate,
                            'url' => $link,
                        );
                        $i++;
                    }
                    $data['logistic'][0]->receiving = $delivery_list;

                    /*
                     * extact deliveries
                     */
                    $query = "SELECT id,serial,head,ddate,status,client FROM {ek_logi_delivery}  WHERE pcode=:p ";
                    $deliveries = Database::getConnection('external_db', 'external_db')->query($query, array(':p' => $pcode));

                    $delivery_list = array();
                    $i = 0;
                    $delivery_status = array('0' => t('open'), '1' => t('printed'), '2' => t('invoiced'), '3' => t('posted'));

                    while ($delivery = $deliveries->fetchObject()) {

                        $href = Url::fromRoute('ek_logistics_delivery_print_share', ['id' => $delivery->id])->toString();
                        $link = ( '<a title="' . t('pdf') . '" href="' . $href . '" class="blue" >' . t('print') . '</a>');
                        $client = Database::getConnection('external_db', 'external_db')
                                        ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $delivery->client))->fetchField();

                        $delivery_list[$i] = array(
                            'serial' => $delivery->serial,
                            'status' => $delivery_status[$delivery->status],
                            'client' => $client,
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


                $query = "SELECT * from {ek_project_finance} WHERE pcode=:p";
                $data['finance'] = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':p' => $pcode))
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
                    //NOTE: PO are not filtered by COID access here. TODO?
                    $query = "SELECT id,serial,status,currency,date,due,amount,amountbc FROM {ek_sales_purchase} "
                            . "WHERE pcode=:p order by id";
                    $pos = Database::getConnection('external_db', 'external_db')->query($query, array(':p' => $pcode));
                    $po_list = array();
                    $i = 0;
                    $po_status = array(t('unpaid'), t('paid'), t('partially paid'));
                    $sum_a = 0;
                    $sum_abc = 0;
                    while ($p = $pos->fetchObject()) {
                        $href = Url::fromRoute('ek_sales.purchases.print_html', ['id' => $p->id])->toString();
                        $link = '<a title="' . t('view') . '" href="' . $href . '" class="blue" target="_blank">' . t('view') . '</a>';
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
                            $duetitle = t('past due') . ' ' . -1*$long . ' ' . t('day(s)');
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
                    $query = "SELECT id,serial,status,currency,date,amount from {ek_sales_quotation} "
                            . "WHERE pcode=:p order by id";
                    $quotes = Database::getConnection('external_db', 'external_db')->query($query, array(':p' => $pcode));
                    $quote_list = array();
                    $i = 0;
                    $quote_status = array(t('open'), t('printed'), t('invoiced'));


                    while ($q = $quotes->fetchObject()) {

                        $href = Url::fromRoute('ek_sales.quotations.print_share', ['id' => $q->id])->toString();
                        $link = ( '<a title="' . t('pdf') . '" href="' . $href . '" class="blue" >' . t('print') . '</a>');

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
                    $query = "SELECT id,serial,head,status,currency,date,due,amount,amountbase "
                            . "FROM {ek_sales_invoice} "
                            . "WHERE pcode=:p order by id";
                    $invoices = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':p' => $pcode));
                    $invoice_list = array();
                    $i = 0;
                    $invoice_status = array(t('unpaid'), t('paid'), t('partially paid'));
                    $sum_a = 0;
                    $sum_abc = 0;

                    while ($in = $invoices->fetchObject()) {
                        
                        
                        $href = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $in->id])->toString();
                        $link = '<a title="' . t('view') . '" href="' . $href . '" class="blue" target="_blank">' . t('view') . '</a>';
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
                            $duetitle = t('past due') . ' ' . -1*$long . ' ' . t('day(s)');
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
                    $query = "SELECT sum(amount) FROM {ek_expenses}  WHERE pcode=:p AND class like :c";
                    $a = array(':p' => $pcode, ':c' => $chart['cos'] . '%');
                    $exp5 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
                    $data['finance'][0]->expenses5 = $baseCurrency . ' ' . number_format($exp5, 2);
                    $query = "SELECT sum(amount) FROM {ek_expenses}  WHERE pcode=:p AND (class like :c1 or class like :c2)";
                    $a = array(':p' => $pcode, ':c1' => $chart['expenses'] . '%', ':c2' => $chart['other_expenses'] . '%');
                    $exp6 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
                    $data['finance'][0]->expenses6 = $baseCurrency . ' ' . number_format($exp6, 2);

                    $data['finance'][0]->expenses = $baseCurrency . ' ' . number_format($exp5 + $exp6, 2);


                    /*
                     * extact Memos
                     */
                    $query = "SELECT id,serial,entity,date,status,value,currency,value_base,mission "
                            . "FROM {ek_expenses_memo} "
                            . "WHERE pcode=:p ";
                    $memos = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':p' => $pcode));

                    $memo_list = array();
                    $i = 0;
                    $memo_status = array('0' => t('unpaid'), '1' => t('partially paid'), '2' => t('paid'));
                    $stc = array('0' => 'red', '1' => 'orange', '2' => 'green');
                    $sum_a = 0;
                    $sum_abc = 0;

                    while ($memo = $memos->fetchObject()) {

                        $href = Url::fromRoute('ek_finance_manage_print_html', ['id' => $memo->id])->toString();
                        $link = ( '<a title="' . t('pdf') . '" href="' . $href . '" class="blue" >' . t('print') . '</a>');
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
                        $data['finance'][0]->memo_sum = array('sum' =>  number_format($sum_a, 2),
                            'sum_bc' => $baseCurrency . ' ' . number_format($sum_abc, 2));
                    }
                }


                /*
                 * create a link to edit currency
                 */
                $param_edit = 'field|currency|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_currency = ( '<a title="' . t('edit currency') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit proposed value
                 */
                $param_edit = 'field|tender_offer|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_tender_offer = ( '<a title="' . t('edit offer value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit agreed value
                 */
                $param_edit = 'field|project_amount|' . $id . '|20%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_project_amount = ( '<a title="' . t('edit project value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit payment terms
                 */
                $param_edit = 'field|payment_terms|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_payment_terms = ( '<a title="' . t('edit payment terms') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit discount
                 */
                $param_edit = 'field|discount_offer|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_discount_offer = ( '<a title="' . t('edit discount') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');
                /*
                 * create a link to edit offer validity
                 */
                $param_edit = 'field|offer_validity|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_offer_validity = ( '<a title="' . t('edit validity') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit offer delivery
                 */
                $param_edit = 'field|offer_delivery|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_offer_delivery = ( '<a title="' . t('edit deadline') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit incoterm
                 */
                $param_edit = 'field|incoterm|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_incoterm = ( '<a title="' . t('edit incoterm') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit purchase value
                 */
                $param_edit = 'field|purchase_value|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_purchase_value = ( '<a title="' . t('edit purchase value') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc status
                 */
                $param_edit = 'field|lc_status|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_status = ( '<a title="' . t('edit LC status') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc revision
                 */
                $param_edit = 'field|lc_revision|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_revision = ( '<a title="' . t('edit LC revision/ref.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit lc expiry
                 */
                $param_edit = 'field|lc_expiry|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_lc_expiry = ( '<a title="' . t('edit LC expiry.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit payment
                 */
                $param_edit = 'field|payment|' . $id . '|30%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_payment = ( '<a title="' . t('edit payment.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');

                /*
                 * create a link to edit comments
                 */
                $param_edit = 'field|comment|' . $id . '|50%';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit])->toString();
                $data['finance'][0]->edit_comment = ( '<a title="' . t('edit comment.') . '" href="' . $link . '" class="use-ajax blue notification" >' . $edit_icon . '</a>');


                /*
                 * create a link to upload a file
                 */
                //the param to pass to modal follow the structure action|pcode|query|type
                // action is used by modal to select form, other params are passed to the form
                $param_upload = 'upload|' . $pcode . '|doc|fi';
                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_upload])->toString();
                $data['finance'][0]->uploadUrl = $link;
                $data['finance'][0]->upload = t('<a href="@url" class="@c" >upload new file</a>', array('@url' => $link, '@c' => 'use-ajax red '));
            } //if section_5


            return array(
                '#theme' => 'ek_projects_view',
                '#items' => $data,
                '#title' => $code_serial . ' | ' .t('Reference') . ': ' . $pcode,
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

            switch ($qfield) {

                case 'fields' :
                    $fields = array();
                    $prio = array(0 => '', 1 => t('low'), 2 => t('medium'), 3 => t('high'));
                    $query = "SELECT status,priority from {ek_project} WHERE id=:id";
                    $data = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $id))
                            ->fetchObject();

                    $fields['status'] = $data->status;
                    $fields['status_container'] = $data->status;
                    $fields['priority'] = $prio[$data->priority];

                    if (in_array(1, $sections)) {
                        $query = 'SELECT * FROM {ek_project_description} d INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE id =:i';
                        $data = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':i' => $id))
                                ->fetchObject();
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
                            if($data->supplieroffer != ''){
                                $query = "SELECT id,name FROM {ek_address_book} WHERE FIND_IN_SET (id, :s )";
                                $suppliers = Database::getConnection('external_db', 'external_db')
                                    ->query($query, array(':s' => $data->supplieroffer));
                                $fields['suppliers'] = "<ul>";
                                WHILE($s = $suppliers->fetchObject()){ 
                                    $fields['suppliers'] .= '<li>'.\Drupal\ek_address_book\AddressBookData::geturl($s->id) . '</li>';
                                }
                                $fields['suppliers'] .= "</ul>";
                            }
                        }
                    }

                    if (in_array(5, $sections)) {
                        $query = 'SELECT * FROM {ek_project_finance} d INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE id =:i';
                        $data = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':i' => $id))
                                ->fetchObject();
                        if ($data) {
                            $fields['payment_terms'] = $data->payment_terms;
                            $fields['purchase_value'] = is_numeric($v = $data->purchase_value) ? number_format($v,2) : $v;
                            $fields['discount_offer'] = is_numeric($v = $data->discount_offer) ? number_format($v,2) : $v;
                            $fields['paymentdate_i'] = $data->paymentdate_i;
                            $fields['paymentdate_d'] = $data->paymentdate_d;
                            $fields['project_amount'] = is_numeric($v = $data->project_amount) ? number_format($v,2) : $v;
                            $fields['lc_status'] = $data->lc_status;
                            $fields['lc_revision'] = $data->lc_revision;
                            $fields['lc_expiry'] = $data->lc_expiry;
                            $fields['tender_offer'] = is_numeric($v = $data->tender_offer) ? number_format($v,2) : $v;
                            $fields['down_payment'] = is_numeric($v = $data->down_payment) ? number_format($v,2) : $v;
                            $fields['offer_delivery'] = $data->offer_delivery;
                            $fields['offer_validity'] = $data->offer_validity;
                            $fields['invoice'] = is_numeric($v = $data->invoice) ? number_format($v,2) : $v;
                            $fields['incoterm'] = $data->incoterm;
                            $fields['currency'] = $data->currency;
                            $fields['payment'] = is_numeric($v = $data->payment) ? number_format($v,2) : $v;
                            $fields['comment'] = nl2br($data->comment);
                        }
                    }
                    if (in_array(4, $sections)) {
                        $query = 'SELECT * FROM {ek_project_shipment} d '
                                . 'INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE id =:i';
                        $data = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':i' => $id))
                                ->fetchObject();
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



                case 'com_docs' :
                    if (in_array(3, $sections)) {
                        $query = "SELECT p.pcode,d.id,fid,filename, uri, d.date,d.comment, owner,size "
                                . "FROM {ek_project_documents} d "
                                . "INNER JOIN {ek_project} p "
                                . "ON d.pcode=p.pcode "
                                . "WHERE p.id=:id "
                                . "AND folder=:t order by d.date,d.id";
                        $a = array(':id' => $id, ':t' => 'com');
                        $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
                    }
                    break;

                case 'fi_docs' :
                    if (in_array(5, $sections)) {
                        $query = "SELECT p.pcode,d.id,fid,filename, uri, d.date,d.comment, owner,size "
                                . "FROM {ek_project_documents} d "
                                . "INNER JOIN {ek_project} p "
                                . "ON d.pcode=p.pcode "
                                . "WHERE p.id=:id AND folder=:t order by d.date, d.id";
                        $a = array(':id' => $id, ':t' => 'fi');
                        $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
                    }
                    break;

                case 'ap_docs' :
                    if (in_array(2, $sections)) {
                        $query = "SELECT p.pcode,d.id,fid,filename, uri, d.date,d.comment, owner,size "
                                . "FROM {ek_project_documents} d "
                                . "INNER JOIN {ek_project} p "
                                . "ON d.pcode=p.pcode "
                                . "WHERE p.id=:id "
                                . "AND folder=:t order by d.date,d.id";
                        $a = array(':id' => $id, ':t' => 'ap');
                        $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
                    }
                    break;
            }


            //build list of documents
            $t = '';
            $i = 0;
            $items = [];
            if (isset($list)) {
                while ($l = $list->fetchObject()) {
                    $i++;
                    
                    /* default values */
                    $items[$i]['fid'] = 1; //default file status on
                    $items[$i]['delete'] = 1; //default delete action is on
                    $items[$i]['email'] = 1; //default email action is on
                    $items[$i]['extranet'] = 0;//default extranet action is of
                    $items[$i]['icon'] = 'file';//default icon 
                    $items[$i]['file_url'] = ''; //default
                    $items[$i]['access_url'] = 0; //default access management if off
                    
                    $items[$i]['uri'] = $l->uri;
                    
                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);
                    
                    $items[$i]['icon_path'] = drupal_get_path('module', 'ek_projects') . '/art/icons/';
                    
                    if (file_exists(drupal_get_path('module', 'ek_projects') . '/art/icons/' . $extension . ".png")) {
                        $items[$i]['icon'] = strtolower($extension);
                    } 

                    //filename formating
                    if (strlen($l->filename) > 30) {
                        $items[$i]['doc_name'] = substr($l->filename, 0, 30) . " ... ";
                    } else {
                        $items[$i]['doc_name'] = $l->filename;
                    }
                    
                    
                    if ($l->fid == '0') { //file was deleted
                        $items[$i]['fid'] = 0;
                        $items[$i]['delete'] = 0;
                        $items[$i]['email'] = 0;
                        $items[$i]['extranet'] = 0;
                        $items[$i]['comment'] = $l->comment . " " . date('Y-m-d', $l->uri);
                        
                    } else {
                        if (!file_exists($l->uri)) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $items[$i]['fid'] = 2;
                            $items[$i]['delete'] = 0;
                            $items[$i]['email'] = 0;
                            $items[$i]['extranet'] = 0;
                            $items[$i]['comment'] = t('Document not available. Please contact administrator');
                        } else {
                            //file exist
                            if (ProjectData::validate_file_access($l->id)) {
                                $route = Url::fromRoute('ek_projects_delete_file', ['id' => $l->id])->toString();
                                $items[$i]['delete_url'] = $route;
                                $items[$i]['file_url'] = file_create_url($l->uri);

                                $param_mail = 'mail|' . $l->id . '|project_documents';
                                $link = Url::fromRoute('ek_projects_modal', ['param' => $param_mail])->toString();
                                $items[$i]['mail_url'] = $link;
                                $items[$i]['delete'] = 1;
                                $items[$i]['email'] = 1;
                                $items[$i]['comment'] = $l->comment;
                                $items[$i]['date'] = date('Y-m-d', $l->date);
                                $items[$i]['size'] = round($l->size / 1000, 0) . " Kb";
                                
                             //add extranet
                                
                                if ($this->moduleHandler->moduleExists('ek_extranet')) {
                                    
                                    $query = "SELECT id,content FROM {ek_extranet_pages} WHERE pcode=:p";
                                    $ext = Database::getConnection('external_db', 'external_db')
                                            ->query($query, [':p' => $l->pcode])
                                            ->fetchObject();
                                    if ($ext && $ext->id > 0) {
                                        $items[$i]['extranet'] = 1;
                                        $c = unserialize($ext->content);
                                        $route = Url::fromRoute('ek_extranet_file', ['id' => $l->id, 'pcode' => $l->pcode], array())->toString();
                                        $items[$i]['extranet_url'] = $route;
                                        if (isset($c['document'][$l->id]) && $c['document'][$l->id] == 1) {
                                            $items[$i]['extranet_share'] = 1;                                         
                                        } else {
                                            $items[$i]['extranet_share'] = 0;
                                        }
                                    } 
                                }
                                
                                
                                
                            } else {  
                                //file exist but access not authorized
                                $items[$i]['delete'] = 0;
                                $items[$i]['email'] = 0;
                                $items[$i]['fid'] = 0;
                                $items[$i]['comment'] = t('Restricted access');
                            }
                        }
                    }

                    //disable because not using file_managed table (TO implement in project table?)
                    //$owner = ProjectData::file_owner($l->id);
                    // use project owner instead

                    if (($l->owner == \Drupal::currentUser()->id() 
                            || \Drupal::currentUser()->hasPermission('admin_projects')) && $l->fid != '0') {
                        $param_access = 'access|' . $l->id . '|project_doc';
                        $link = Url::fromRoute('ek_projects_modal', ['param' => $param_access])->toString();
                        $items[$i]['access_url'] = $link;
                    } 


                }

                $render = ['#theme' => 'ek_projects_doc_view', '#items' => $items];
                $data =  \Drupal::service('renderer')->render($render);               
            }
            return new JsonResponse(array('data' => $data));
            
        } else {
            // no access
            return new JsonResponse(array('data' => NULL));
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
        $first = NULL;
        $today = time('U') - 86400;
        if (isset($id)) {
            
            $query = "SELECT uid,name from {users_field_data}";
            $users = db_query($query)->fetchAllKeyed();
            $uid = \Drupal::currentUser()->id();
            $query = 'SELECT t.uid,t.stamp,action  '
                    . 'FROM {ek_project_tracker} t '
                    . 'INNER JOIN {ek_project} p ON t.pcode=p.pcode '
                    . 'WHERE id =:i ORDER by stamp DESC';
            $a = array(':i' => $id);
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
            $t1 = "<div><b>--- [" . t('Today') . "] ---</b></div>" ;
            $t2 = "<div><b>--- [" . t('Earlier') . "] ---</b></div>" ;
            $first = '';
            $i = 0;

            while ($d = $data->fetchObject()) {
                if($d->uid != $uid) {
                    isset($users[$d->uid]) ? $name = $users[$d->uid] : $name = t('Unknown');
                } else {
                    $name = t('You');
                }
                
                $on = date('l jS \of F Y h:i A', $d->stamp);
                //$t.= '<li>' . $d->action . ' <span title="' . $on . '">(' . $name . ')</span></li>';

                if ($i == 0) {
                    $first = str_replace('edit', '', $d->action);
                    $first = str_replace(' ', '_', trim($first));
                } 
                
                if($d->stamp >= $today){
                    $t1.= '<li>' . $d->action . ' <span title="' . $on . '">(' . $name . ')</span></li>';
                } else {
                    $t2.= '<li>' . $d->action . ' <span title="' . $on . '">(' . $name . ')</span></li>';
                }
                $i++;
            }
        }

        return new JsonResponse(array('data' => $t1.$t2, 'field' => $first));
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
    public function deletefile($id) {
       $query = 'SELECT filename FROM {ek_project_documents} WHERE id=:id';
        $file = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchField();
        $content = array('content' =>
            array('#markup' =>
                "<p><a href='delete_file_confirmed/" . $id . "' class='use-ajax'>"
                . t('delete') . "</a> " . $file . "</p>")
        );

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

        $response = new Response('', 204);//default return response
        
        $query = "SELECT filename,uri,d.deny, p.id from {ek_project_documents} d "
                . "LEFT JOIN {ek_project} p "
                . "ON d.pcode = p.pcode WHERE d.id=:f";
        
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':f' => $id))
                ->fetchObject();
        //control project access 
        if(ProjectData::validate_access($p->id)) {
            
            //control deny access
            if(!in_array(\Drupal::currentUser()->id(), explode(',', $p->deny))) {          
            
                $fields = array(
                    'fid' => 0,
                    'uri' => date('U'),
                    'comment' => t('deleted by') . ' ' . \Drupal::currentUser()->getUsername(),
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
                    
                    $query = "SELECT fid FROM {file_managed} WHERE uri=:u";
                    $file = db_query($query, [':u' => $p->uri])->fetchObject();
                    if($file->fid) {
                        $obj = \Drupal\file\Entity\File::load($file->fid);
                        $obj->setTemporary();
                        $obj->save();
                    }
                    
                }


                $log = $p->pcode . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
                \Drupal::logger('ek_projects')->notice($log);


                $fields = array(
                    'pcode' => $p->pcode,
                    'uid' => \Drupal::currentUser()->id(),
                    'stamp' => time(),
                    'action' => 'delete' . ' ' . $p->filename
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
        return $this->dialog(TRUE, $param);
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function nonModal($param) {
        return $this->dialog(FALSE, $param);
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
    protected function dialog($is_modal = FALSE, $param = NULL) {

        $param = explode('|', $param);
        $content = [];
        switch ($param[0]) {

            case 'upload':
                $id = $param[1] . '|' . $param[2] . '|' . $param[3] . '|' . $param[4];
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\UploadForm', $id);
                $options = array('width' => '25%',);
                break;

            case 'task':
                if ($param[2] == '')
                    $param[2] = 0;
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\TaskProject', $param[1], $param[2]);
                $options = array('width' => '40%',);
                
                break;

            case 'mail':
                $data = array(
                    $param[1], //doc id
                    $param[2], //tb ref
                    'open' => TRUE,
                );
                $data = serialize($data);
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $data);
                $options = array('width' => '30%',);

                break;

            case 'access' :
                $id = $param[1];
                $type = $param[2];
                $content['form'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\ProjectAccessEdit', $id, $type);
                $options = array('width' => '25%',);
                break;

            case 'notification' :
                $id = $param[1];
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\Notification', $id);
                $options = array('width' => '30%',);
                break;
            case 'field' :
                $id = $param[2];
                $field = $param[1];
                $width = isset($param[3]) ? $param[3] : '30%';
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_projects\Form\ProjectFieldEdit', $id, $field);
                $options = array('width' => $width);
                break;
            case 'extranet' :
                $id = $param[1];
                $width = isset($param[2]) ? $param[2] : '50%';
                $content['content'] = $this->formBuilder->getForm('Drupal\ek_extranet\Form\Edit', $id);
                $options = array('width' => $width);
                break;
            case 'followers' :
                $id = $param[1];
                $width = isset($param[2]) ? $param[2] : '30%';
                
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p');
                $query->fields('p' , ['notify']);
                $query->condition('id', $id, '=');
                $data = $query->execute();
                $notify = explode(',', $data->fetchField());
                $list = '';
                $query = "SELECT uid,name from {users_field_data}";
                $users = db_query($query)->fetchAllKeyed();
                
                foreach($notify as  $value) {
                    if ($value == \Drupal::currentUser()->id()) {
                        $list .= '<li>' . t('Me') . '</li>';
                    } else {
                        $username = isset($users[$value]) && $users[$value] != ''  ? $users[$value] : t('Unknown') . ' ' . $value;
                        $list .= '<li>' . $username . '</li>';
                    }
                }
                $content['content'] = ['#markup' => '<ul>' . $list . '</ul>'];
                $options = array('width' => $width);
                $is_modal == TRUE;
                break;
        }

        $response = new AjaxResponse();
        $title = ucfirst($this->t($param[0]));
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

        $query = "SELECT notify FROM {ek_project} WHERE id=:id";
        $notify = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $_POST['id']))->fetchField();

        $notify = explode(',', $notify);
        $action =  0;
        if (in_array(\Drupal::currentUser()->id(), $notify)) {

            if (($key = array_search(\Drupal::currentUser()->id(), $notify)) !== false) {
                unset($notify[$key]);
            }
        } else {
            array_push($notify, \Drupal::currentUser()->id());
            $action = 1;
        }
        $notify = implode(',', $notify);
        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_project')
                ->fields(array('notify' => $notify))
                ->condition('id', $_POST['id'])
                ->execute();

        if ($update)
            return new JsonResponse(['action' => $action]);
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

        if ($update)
            $response = new AjaxResponse();
            $a = ['0' => t('no'), '1' => t('yes')];
            return $response->addCommand(new HtmlCommand('#arch'.$id , $a[$status]));
            //return new JsonResponse(TRUE);
    }
    
    /**
     * Return ajax search autocomplete data
     * used in forms
     * @param $type : all|main|sub
     * @param $status : 0(all) | 1(open) | 2(awarded) | 3(completed) | 4(closed)
     * @return array json format
     */
    public function lookupProject(Request $request, $level = NULL, $status = 0) {

        $text = '%' . $request->query->get('q') . '%';
        
        if($level == "main") {
            $level = 'Main project';
        } elseif($level == "sub"){
            $level = 'Sub project';
        } else {
            $level = '%';
        }
        
        if($status == "1") {
            $status = 'open';
        } elseif($status == "2") {
            $status = 'awarded';
        } elseif($status == "3") {
            $status = 'completed';
        } elseif($status == "4") {
            $status = 'closed';
        } else {
            $status = '%';
        }
        
        $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');

            $or = db_or();
            $or->condition('pcode', $text, 'like');
            $or->condition('pname', $text, 'like');
            
            
            $data = $query
                    ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date','archive'))
                    ->condition($or)
                    ->condition('level', $level, 'like')
                    ->condition('status', $status, 'like')
                    ->execute();
        /*
        $query = "SELECT p.id,pcode,pname,cid,p.status from {ek_project} p "
                . "INNER JOIN {ek_country} c on p.cid=c.id WHERE level=:l AND pcode like :t";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':l' => 'Main project', ':t' => $text));
        */
            
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
     * Currently the task form is called via modal dialog
     */
    public function TaskProject(Request $request) {
        return array();
    }

    /**
     * List current tasks per project
     * 
     */
    //todo: add filter to project 

    public function TaskList($pcode) {

        $header = array(
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
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_tasks', 't');
        $query->join('ek_project', 'p', 'p.pcode=t.pcode');
        $data = $query->fields('t', array('id', 'event', 'task', 'start', 'end', 'uid', 'gid', 'completion_rate','color'))
                ->fields('p', array('id'))
                ->condition('t.pcode', $pcode, '=')
                ->extend('Drupal\Core\Database\Query\TableSortExtender')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(25)->orderBy('id', 'ASC')
                ->execute();

        while ($r = $data->fetchObject()) {

            $period = date('Y-m-d', $r->start) . ' <br/>' . date('Y-m-d', $r->end);
            if (\Drupal::currentUser()->id() == $r->uid) {
                $name = t('Myself');
            } else {
                $query = "SELECT name from {users_field_data} WHERE uid=:u";
                $name = db_query($query, array(':u' => $r->uid))->fetchField();
            }

            if ($r->end != NULL && date('U') > $r->end && $r->completion_rate < 100) {
                $status = "<span class='red'>" . t('expired') . "</span>";
            } else {
                $status = t('done') . ': ' . $r->completion_rate . ' %';
            }

            $options[$r->id] = array(
                'event' => array('data' => ['#markup' => $r->event]),
                'task' => ['data' => ['#markup' => $r->task ], 'style' => ['background-color:' . $r->color]],
                'period' => ['data' => ['#markup' => $period]],
                'user' => $name,
                'status' => ['data' => ['#markup' => $status]],
            );


            $param_edit = 'task|' . $r->p_id . '|' . $r->id;
            $link = Url::fromRoute('ek_projects_modal', ['param' => $param_edit]);
            /* */
            $links['edit'] = array(
                'title' => $this->t('Edit'),
                'url' => $link,
                'attributes' => array('class' => array('use-ajax'))
            );
            if (\Drupal::currentUser()->hasPermission('delete_project_task')) {
                $links['del'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_projects_task_delete', ['id' => $r->id]),
                );
            }

            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }


        $build['tasks_table'] = array(
            '#type' => 'table',
            '#title' => t('Tasks') . ' ' . $pcode,
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'tasks_table'),
            '#empty' => $this->t('No task available'),
            '#attached' => array(
                'library' => array('ek_projects/ek_projects_css'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

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
     *
     */
    public function userautocomplete(Request $request) {

        $text = $request->query->get('term');
        $name = array();

        $query = "SELECT distinct name from {users_field_data} WHERE mail like :t1 or name like :t2 ";
        $a = array(':t1' => "$text%", ':t2' => "$text%");
        $name = db_query($query, $a)->fetchCol();

        return new JsonResponse($name);
    }

//end class
}
