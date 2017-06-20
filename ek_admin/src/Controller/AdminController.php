<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Controller\AdminController.
 */

namespace Drupal\ek_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_admin\GlobalSettings;

/**
 * Controller routines for ek module routes.
 */
class AdminController extends ControllerBase {
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
     * The entity query factory service.
     *
     * @var \Drupal\Core\Entity\Query\QueryFactory
     */
    protected $entityQuery;

    /**
     * The entity manager service.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface
     */
    protected $entityManager;

    /**
     * Stores the state storage service.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler'), $container->get('entity.manager'), $container->get('entity.query'), $container->get('state')
        );
    }

    /**
     * Constructs object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
     *   The entity manager.
     * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
     *   The entity query factory.
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler, EntityManagerInterface $entity_manager, QueryFactory $entity_query, StateInterface $state) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->entityManager = $entity_manager;
        $this->entityQuery = $entity_query;
        $this->state = $state;
    }

    /**
     * Run Cron called by server to execute EK tasks.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *   A Symfony response object.
     */
    public function run_task_cron($key) {

        if ($this->state->get('system.cron_key') == $key) {
            $stamp = date('U');
            $log = 'start tasks checking at: ' . date('Y-m-d h:i', $stamp);
            \Drupal::logger('ek_admin')->notice($log);
            
            //include the tasks checking code (modules ek_sales, ek_projects)
            include_once drupal_get_path('module', 'ek_admin') . '/cron/cron_get_tasks.php';
        }
        
        // HTTP 204 is "No content", meaning "I did what you asked and we're done."
        return new Response('', 204);
    }

    
    /**
     * Run Cron called by server to retrieve backup file9s) and email to selected addresses.
     * Cron backup must be set separately on the server. This will only send an email with attached file
     * 
     * @return \Symfony\Component\HttpFoundation\Response
     *   A Symfony response object.
     */
    public function run_backup_cron($key) {

        if ($this->state->get('system.cron_key') == $key) {
            $stamp = date('U');
            $log = 'send backup file to recipient: ' . date('Y-m-d h:i', $stamp);
            \Drupal::logger('ek_admin')->notice($log);
            
          $settings = new GlobalSettings(0);
          $backupDir = $settings->get('backup_directory');
          $backupFiles = explode(",", $settings->get('backup_filename'));
          $recipients = explode(",", $settings->get('backup_recipients'));
          
          $options['origin'] = "backup_documents";
          $options['user'] = t("Automated task");
          
          
          $message = t("<p>Your faithful assistant has generated a backup of your company data.</p>") . "<br/>" .
            
            t("<p>DISCLAIMER: the system provider and its affiliates take no responsibility 
            for any data loss or corruption when using this script and service. 
            You are advised to download, verify and store your backup in a safe place.</p>");
          
          foreach($backupFiles as $backupFile) {
            if(file_exists($backupDir . "/" . $backupFile)) {
                mail_attachment($recipients, $backupDir . "/" . $backupFile, $message, $options);
            }
          }
        }
        

        // HTTP 204 is "No content", meaning "I did what you asked and we're done."
        return new Response('', 204);
    }
    
    /**
     * Return default 
     *
     */
    public function isDefault() {
        $site_config = \Drupal::config('system.site');

        $build['welcome'] = array(
            '#markup' => '<h2>' . t('Welcome to @s management site.', array('@s' => $site_config->get('name'))) . '</h2>',
        );

        return $build;
    }

    /**
     * Return main 
     *
     */
    public function Admin() {

        $build = array(
            '#title' => t('Administrate system data and settings.'),
        );


        //verify proper setup of the external database
        //all data tables will be generated by the admin module outside from the system database
        // database is called by 'external_db' key in the settings files
        //1/ the external DB exist? check from the settings key

        try {
            $external = Database::getConnectionInfo('external_db');
            if (!empty($external)) {
                $db = TRUE;
            } else {
                $db = FALSE;
            }
        } catch (Exception $e) {
            
        }

        if ($db == FALSE) {

            $build['alert'] = array(
                '#markup' => '<h3>' . t('You do not have external data database defined yet. You need to create one. '
                        . 'Data will be stored in this database and must exists before you validate these settings.') . '</h3>',
            );
            $build['form'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\SiteSettingsForm');
        } else {

            /*
             * Verify if tables are installed
             */


            $query = "SHOW TABLES LIKE 'ek_admin_settings'";
            $tb = Database::getConnection('external_db', 'external_db')
                            ->query($query)->fetchField();



            if ($tb == 'ek_admin_settings') {
                /*
                 * Module Tables installed; Verify various settings data
                 */

                //company
                $query = "SELECT count(id) FROM {ek_company}";
                $data = Database::getConnection('external_db', 'external_db')->query($query)->fetchField();

                if ($data < 1) {
                    $link = Url::fromRoute('ek_admin.company.new', array(), array())->toString();
                    $build['company'] = array(
                        '#markup' => t('You have not created any company yet. Go <a href="@c">here</a> to create one.', array('@c' => $link)) . '<br/>',
                    );
                }

                //countries
                $query = "SELECT count(id) FROM {ek_country} WHERE status=:s";
                $data = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => 1))->fetchField();
                if ($data < 1) {
                    $link = Url::fromRoute('ek_admin.country.list', array(), array())->toString();
                    $build['country'] = array(
                        '#markup' => t('You have not activated any country yet. Go <a href="@c">here</a> to activate countries.', array('@c' => $link)) . '<br/>',
                    );
                }

                //address book
                if ($this->moduleHandler->moduleExists('ek_address_book')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_address_book'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_address_book') {
                        return new RedirectResponse(\Drupal::url('ek_address_book_install'));
                        exit;
                    }
                }
                //assets
                if ($this->moduleHandler->moduleExists('ek_assets')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_assets'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_assets') {
                        return new RedirectResponse(\Drupal::url('ek_assets_install'));
                        exit;
                    }
                }
                //documents
                if ($this->moduleHandler->moduleExists('ek_documents')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_documents'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_documents') {
                        return new RedirectResponse(\Drupal::url('ek_documents_install'));
                        exit;
                    }
                }
                //hr
                if ($this->moduleHandler->moduleExists('ek_hr')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_hr_workforce'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_hr_workforce') {
                        return new RedirectResponse(\Drupal::url('ek_hr_install'));
                        exit;
                    }
                }
                //reports
                if ($this->moduleHandler->moduleExists('ek_intelligence')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_ireports'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_ireports') {
                        return new RedirectResponse(\Drupal::url('ek_intelligence_install'));
                        exit;
                    }
                }
                //logistics
                if ($this->moduleHandler->moduleExists('ek_logistics')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_logi_settings'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_logi_settings') {
                        return new RedirectResponse(\Drupal::url('ek_logistics_install'));
                        exit;
                    }
                }
                //messaging
                if ($this->moduleHandler->moduleExists('ek_messaging')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_messaging'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_messaging') {
                        return new RedirectResponse(\Drupal::url('ek_messaging_install'));
                        exit;
                    }
                }

                //products
                if ($this->moduleHandler->moduleExists('ek_products')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_items'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_items') {
                        return new RedirectResponse(\Drupal::url('ek_products_install'));
                        exit;
                    }
                }
                //projects
                if ($this->moduleHandler->moduleExists('ek_projects')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_project'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_project') {
                        return new RedirectResponse(\Drupal::url('ek_projects.install'));
                        exit;
                    }
                }
                //sales
                if ($this->moduleHandler->moduleExists('ek_sales')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_sales_settings'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_sales_settings') {
                        return new RedirectResponse(\Drupal::url('ek_sales_install'));
                        exit;
                    }
                }

                //finance
                if ($this->moduleHandler->moduleExists('ek_finance')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_finance'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_finance') {
                        return new RedirectResponse(\Drupal::url('ek_finance_install'));
                        exit;
                    }


                    $settings = new FinanceSettings();
                    $baseCurrency = $settings->get('baseCurrency');

                    if ($baseCurrency = '') {
                        $link = Url::fromRoute('ek_finance.admin.settings', array(), array())->toString();
                        $build['finance'] = array(
                            '#markup' => t('You have not set any base currency yet. Go <a href="@c">here</a> to select a base currency.<br/>', array('@c' => $link)),
                        );
                    }
                }
            } else {
                //need to install tables for main module
                return new RedirectResponse(\Drupal::url('ek_admin_install'));
                exit;
            }
        }

        return $build;
    }

    /**
     * @return 
     *    settings form
     * @param int coid : optional company id ref (0 = global settings). 
     * 
     */
    public function AdminSettings($coid = 0) {

        $build['form'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\SettingsForm', $coid);
        $build['#title'] = t('Global settings');
        return $build;
    }

    public function Access() {

        return array(t('Administrate managed entities (companies, countries) and users access to those entities'));
    }

    /**
     * @return company list
     *
     */
    public function ListCompany(Request $request) {

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        $query = "SELECT * FROM {ek_company}  WHERE (FIND_IN_SET (id, :h ))  order by :o :d";
        $a = array(
            ':h' => $company,
            ':o' => $request->query->get('order') ? $request->query->get('order') : 'id',
            ':d' => $request->query->get('sort') ? $request->query->get('sort') : 'asc',
        );


        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);


        $header = array(
            'id' => array(
                'data' => $this->t('id'),
                'field' => 'id',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'field' => 'name',
                'class' => array(),
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => array(
                'data' => '',
                'width' => '20%',
            ),
        );
        $i = 0;
        while ($r = $data->fetchObject()) {
            $i++;
            $options[$i] = array(
                'id' => $r->id,
                'name' => $r->name,
                'status' => ($r->active == '0') ? t('non active') : t('active'),
            );

            $links = array();

            $links['edit'] = array(
                'title' => "" . $this->t('Edit'),
                'url' => Url::fromRoute('ek_admin.company.edit', ['id' => $r->id]),
            );
            $links['finance'] = array(
                'title' => $this->t('Finance parameters'),
                'url' => Url::fromRoute('ek_admin.company_settings.edit', ['id' => $r->id]),
            );
            $links['documents'] = array(
                'title' => $this->t('Attached documents'),
                'url' => Url::fromRoute('ek_admin.company.docs', ['id' => $r->id]),
            );
            $links['label'] = array(
                'title' => $this->t('Print label'),
                'url' => Url::fromRoute('ek_admin_company.pdf', ['id' => $r->id]),
                'attributes' => array('target' => '_blank'),
            );

            $options[$i]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }//while

        $build['company_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'company_table'),
            '#empty' => $this->t('No company'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );

        return $build;
    }

    /**
     * @return company docs ajax list
     * 
     */
    public function CompanyDocuments($id) {

        $items['form'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\UploadForm', $id);

        $link = Url::fromRoute('ek_admin.company.list', array(), array())->toString();

        $items['back'] = t('<a href="@c">List</a>', array('@c' => $link));

        return array(
            '#items' => $items,
            '#theme' => 'ek_admin_docs_view',
            '#attached' => array(
                'drupalSettings' => array('coid' => $id),
                'library' => array('ek_admin/ek_admin_docs_updater', 'ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * @return html list
     *
     */
    public function load(Request $request) {

        $query = "SELECT * FROM {ek_company_documents} WHERE coid = :c order by id";
        $list = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $request->get('coid')));


        //build list of documents
        $t = '';
        $i = 0;
        if (isset($list)) {
            while ($l = $list->fetchObject()) {
                $i++;
                $share = explode(',', $l->share);
                $deny = explode(',', $l->deny);

                if ($l->share == '0' || ( in_array(\Drupal::currentUser()->id(), $share) && !in_array(\Drupal::currentUser()->id(), $deny) )) {

                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);
                    $image = 'modules/ek_admin/art/icons/' . $extension . ".png";
                    $uri = $l->uri;

                    if (file_exists($image)) {
                        $image = "<IMG src='../../$image'>";
                    } else {
                        $image = "<IMG src='../../modules/ek_admin/art/icons/file.png'>";
                    }

                    if ($l->fid == '0') { //file was deleted
                        $del_button = '[-]';
                        $doc = $l->filename;
                        $mail_button = '';
                    } else {
                        if (file_exists(file_uri_target($uri))) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $del_button = '';
                            $l->comment = t('Document not available. Please contact administrator');
                            $doc = $l->filename;
                            $mail_button = '';
                        } else {
                            //file exist

                            $route = Url::fromRoute('ek_admin_delete_file', ['id' => $l->id])->toString();
                            $del_button = "<a id='d$i' href=" . $route . " class='use-ajax red fa fa-trash-o' title='" . t('delete the file') . "'></a>";
                            $doc = "<a href='" . file_create_url($uri) . "' target='_blank' >" . $l->filename . "</a>";
                        }
                    }


                    if ($l->fid != '0') {

                        $param_access = 'access|' . $l->id . '|company_doc';
                        $link = Url::fromRoute('ek_admin_modal', ['param' => $param_access])->toString();
                        $share_button = t('<a href="@url" class="@c"  > access </a>', array('@url' => $link, '@c' => 'use-ajax red fa fa-lock'));
                    } else {
                        $share_button = '';
                    }

                    $t .= "<tr id='ad" . $l->id . "'>
                          <td>" . $image . " " . $doc . "</td>
                          <td>" . date('Y-m-d', $l->date) . "</td>
                          <td>" . $l->comment . "</td>
                          <td>" . $del_button . "</td>
                          <td>" . $share_button . "</td>

                      </tr>
                      
                      ";
                } //in array
            }
        }
        return new JsonResponse(array('list' => $t));
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
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
        $content = '';
        switch ($param[0]) {

            case 'access' :
                $id = $param[1];
                $type = $param[2];
                $content = $this->formBuilder->getForm('Drupal\ek_admin\Form\DocAccessEdit', $id, $type);
                $options = array('width' => '25%',);
                break;
        }

        $response = new AjaxResponse();
        $title = $this->t($param[0]);
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content, $options);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-text-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $content));
        }
        return $response;
    }

    /**
     * delete a file from list 
     * @return Ajax response
     *
     */
    public function deletefile($id) {

        $p = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_company_documents} WHERE id=:f", array(':f' => $id))
                ->fetchObject();
        $fields = array(
            'fid' => 0,
            'uri' => date('U'),
            'comment' => t('deleted by') . ' ' . \Drupal::currentUser()->getUsername(),
            'date' => time()
        );
        $delete = Database::getConnection('external_db', 'external_db')
                ->update('ek_company_documents')
                ->fields($fields)->condition('id', $id)
                ->execute();
        if ($delete) {
            $query = "SELECT * FROM {file_managed} WHERE uri=:u";
            $file = db_query($query, [':u' => $p->uri])->fetchObject();
            file_delete($file->fid);
        }


        $log = $p->id . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
        \Drupal::logger('ek_admin')->notice($log);

        $response = new AjaxResponse();
        $response->addCommand(new RemoveCommand('#ad' . $id));
        return $response;
    }

    /**
     * Create new business entity
     * 
     */
    public function AdminCompanyNew() {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCompanyForm');

        return array(
            '#theme' => 'ek_admin_company_form',
            '#items' => $response,
            '#title' => t('New company'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Edit business entity
     * @param int id
     * @return Form
     */
    public function AdminCompanyEdit($id) {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCompanyForm', $id);
        $query = "SELECT name from {ek_company} where id=:id";
        $company = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
        return array(
            '#theme' => 'ek_admin_company_form',
            '#items' => $response,
            '#title' => t('Edit company  - <small>@id</small>', array('@id' => $company)),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Edit settings for business entity
     * @param int id
     * @return Form
     */
    public function AdminCompanyEditSettings($id) {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCompanySettings', $id);
        $query = "SELECT name from {ek_company} where id=:id";
        $company = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
        return array(
            '#theme' => 'ek_admin_company_form',
            '#items' => $response,
            '#title' => t('Edit company settings - <small>@id</small>', array('@id' => $company)),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Business entities excel list
     * @param int id
     * @return File download
     * 
     *
     */
    public function excelcompany($id = NULL) {
        $markup = array();    
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            include_once drupal_get_path('module', 'ek_admin') . '/excel_list.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * Business entities contact card in pdf
     * @param int id
     * @return File download
     */
    public function pdfcompany($id = NULL) {

        $markup = array();
        include_once drupal_get_path('module', 'ek_admin') . '/company_pdf.inc';
        return $markup;
    }

    /**
     * Edit settings for countries
     * @param 
     * @return Form
     *
     */
    public function AdminCountry() {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCountryForm');

        return array(
            '#theme' => 'ek_admin_country_form',
            '#items' => $response,
            '#title' => t('List and edit countries'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Manage business entity access form
     * @return Form
     *
     */
    public function AccessCompany() {

        Cache::invalidateTags(['ek_access_control_form']);
        $form_builder = $this->formBuilder();
        $build['form'] = $form_builder->getForm('Drupal\ek_admin\Form\CompanyAccessForm');
        $build['#attached'] = array(
            'library' => array('ek_admin/ek_admin_css'),
        );
        $build['#title'] = t('Edit company access by user');
        $build['#cache']['max-age'] = -1;
        return $build;
    }

    /**
     * Manage country access form
     * @return Form
     * 
     */
    public function AccessCountry() {

        Cache::invalidateTags(['ek_access_control_form']);
        $form_builder = $this->formBuilder();
        $build['form'] = $form_builder->getForm('Drupal\ek_admin\Form\CountryAccessForm');

        $build['#attached'] = array(
            'library' => array('ek_admin/ek_admin_css'),
        );
        $build['#title'] = t('Edit country access by user');
        $build['#cache']['max-age'] = -1;
        return $build;
    }

    /**
     * View access to companies and countries by user
     * @return html
     * 
     */
    public function AccessByUser() {

        
        $form_builder = $this->formBuilder();
        $items['form'] = $form_builder->getForm('Drupal\ek_admin\Form\UserSelect');
        
        
        if(isset($_SESSION['admin_user_select'])) {
            
            $items['company'] = AccessCheck::CompanyListByUid($_SESSION['admin_user_id_select']);
            $items['country'] = AccessCheck::CountryListByUid($_SESSION['admin_user_id_select']);
            
        }
        return array(
            '#theme' => 'ek_admin_user_access',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }
    
    /**
     * Generate a business entity shortname
     * @param request
     * @return Json response
     */
    public function ajaxcsnbuilt(Request $request) {

        $text = $request->query->get('term');

        $a = explode(' ', $text);

        $terms = count($a);
        $sn = '';

        if ($terms == 1) {
            $sn.= substr($text, 0, 4);
        } elseif ($terms > 1) {

            for ($i = 0; $i <= 3; $i++) {

                if ($a[$i] == '') {
                    $sn.= '-';
                } else {
                    $sn.= substr($a[$i], 0, 1);
                }
            }
        }
        $sn = strtoupper($sn);

        //confirm entry
        $valid = '';
        for ($i = 0; $i <= $terms; $i++) {
            $valid.= $a[$i] . '%';
        }
        $query = "SELECT count(name) from {ek_company} where name like :text";
        $ok = Database::getConnection('external_db', 'external_db')->query($query, array(':text' => $valid))->fetchField();
        if ($ok == 1)
            $alert = t('This name already exists in the records');

        return new JsonResponse(array('sn' => $sn, 'name' => $ok, 'alert' => $alert));
    }

    /**
     * User name autocomplete
     * @param request
     * @return Json response
     */
    public function userAutocomplete(Request $request) {
        $q = $request->query->get('q');
        $uids = $this->entityQuery->get('user')
                ->condition('name', $q, 'STARTS_WITH')
                ->range(0, 10)
                ->execute();

        $controller = $this->entityManager->getStorage('user');
        foreach ($controller->loadMultiple($uids) as $account) {
            if ($account->getUsername() != 'Anonymous') {
                $matches[] = array('value' => $account->getUsername(), 'label' => SafeMarkup::checkPlain($account->getUsername()));
            }
        }

        return new JsonResponse($matches);
    }

    /*
     * function to send an email notification for tasks (cron)
     */

    private function send_mail($params) {

        //$site_config = \Drupal::config('system.site');
        //$site_name = $site_config->get('name');
        //$domain = $_SERVER['HTTP_HOST'];

        $query = "SELECT name, mail FROM `users_field_data` WHERE uid=:uid";
        $a = array(':uid' => $params['uid']);
        $data = db_query($query, $a);
        $row = $data->fetchObject();
        $params['options']['name'] = $row->name;
        $mail = $row->mail;
  
        $params['options']['subject'] = $params['subject'];

        if ($params['type'] == 'status') {
            $template = 'project_status';
            $params['options']['data'] = $params['data'];
        } elseif($params['type'] == 'salert') {
            $template = 'sales_alert';
            $params['options']['data'] = $params['data'];
        } else { //default
            $template = 'tasks';
            $params['options']['link'] = $params['link'];
            $params['options']['serial'] = $params['serial'];
            $params['options']['task'] = $params['task'];
            $params['options']['end'] = $params['end'];
            $params['options']['alert'] = $params['alert'];
            
            $query = "SELECT name, mail FROM `users_field_data` WHERE uid=:uid";
            $a = array(':uid' => $params['assign']);
            $data = db_query($query, $a);
            $row = $data->fetchObject();
            $params['options']['assign_name'] = $row->name;
        }
        
        if ($mail) {
            if ($target_user = user_load_by_mail($mail)) {
                    $target_langcode = $target_user->getPreferredLangcode();
            } else {
                    $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
            }
          $send = \Drupal::service('plugin.manager.mail')->mail(
                'ek_admin',
                $template,
                trim($mail),
                $target_langcode,
                $params,
                "do_not_reply@" . $_SERVER['HTTP_HOST'],
                TRUE
            );
            
        } else {
            return FALSE;
        }
    }

}
