<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Controller\AdminController.
 */

namespace Drupal\ek_admin\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
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

    /**
     * The config factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

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
    //protected $entityQuery;

    /**
     * The entity manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManager
     */
    protected $entityTypeManager;

    /**
     * Stores the state storage service.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    /**
     * The flood service.
     *
     * @var \Drupal\Core\Flood\FloodInterface
     */
    protected $flood;

    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler'), $container->get('entity_type.manager'),
                //$container->get('entity.query'),
                $container->get('state'), $container->get('flood'), $container->get('config.factory')
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
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler, EntityTypeManager $entity_manager, StateInterface $state, FloodInterface $flood, ConfigFactoryInterface $config_factory) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->entityTypeManager = $entity_manager;
        //$this->entityQuery = $entity_query;
        $this->state = $state;
        $this->flood = $flood;
        $this->configFactory = $config_factory;
    }

    /**
     * Run Cron called by server to execute EK tasks.
     * In order to use this function setup a server with a cron task to call admin task function
     * i.e. in Linux server
     *      0 0 * * *  wget --no-check-certificate https://[domain]/cron/task/[key]
     * where key is drupal cron key you can get from /ek_admin/settings or /admin/config/system/cron
     * cron_get_tasks.php is a custom PHP file that will do tasks checking according to requirements and send emails
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
     * Run Cron called by server to retrieve backup files and email to selected addresses.
     * Cron backup must be set separately on the server. This will only send an email with attached file
     * In order to use this function setup a server with a cron task to call admin task function
     * i.e. in Linux server
     *      0 0 * * *  wget --no-check-certificate https://[domain]/cron/backup/[key]
     * where key is drupal cron key you can get from /ek_admin/settings or /admin/config/system/cron
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
            $options['user'] = $this->t("Automated task");
            $message = $this->t("<p>Your faithful assistant has generated a backup of your company data.</p>");
            $message .= $this->t("<p>DISCLAIMER: the system provider and its affiliates take no responsibility 
                        for any data loss or corruption when using this script and service. 
                        You are advised to download, verify and store your backup in a safe place.</p>");
            //scan settings root directory for configuration with backup files in separate sub directories
            $scan = scandir($backupDir);
            foreach ($scan as $directory) {
                foreach ($backupFiles as $backupFile) {
                    if (file_exists($backupDir . "/" . $directory . "/" . $backupFile)) {
                        mail_attachment($recipients, $backupDir . "/" . $directory . "/" . $backupFile, $message, $options);
                    }
                }
            }
        }

        // HTTP 204 is "No content", meaning "I did what you asked and we're done."
        return new Response('', 204);
    }

    /**
     * issue receipt from mail open
     * when sending mail with attachment from system
     * @return 204
     * @param str $key: a receipt source type
     * 
     * ex. /ek_admin_receipt/mail?org=sender@example.com&ts=1398873600&r=somebody@example.com&c=filename
     */
    public function mail_receipt(Request $request, $key) {

        //will attempt to send an email to existing user 
        //triggered by email opening
        $flood_config = $this->configFactory->get('user.flood');
        
        // add flood control for protection
        // use parameters for user login flood
        // @see \Drupal\user\Form\UserLoginForm::validateAuthentication()

        if ($this->flood->isAllowed('mail.receipt', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
            $identifier = $request->query->get('r') . '-' . $request->getClientIP();

            if ($this->flood->isAllowed('mail.receipt', $flood_config->get('user_limit'), $flood_config->get('user_window'), $identifier)) {
                if ($user = user_load_by_mail($request->query->get('org'))) {
                    $params['options']['subject'] = t('Mail open receipt');
                    $markup = "<p>" . $this->t('Message sent') . ": " . date('Y-m-d H:i', $request->query->get('ts')) . "</p>";
                    $markup .= "<p>" . $this->t('Message open') . ": " . date('Y-m-d H:i', time()) . "</p>";
                    $markup .= "<p>" . $this->t('Location') . ": " . $request->getClientIP() . "</p>";
                    $markup .= "<p>" . $this->t('Message type') . ": " . $key . "</p>";
                    $markup .= "<p>" . $this->t('Recipient') . ": " . $request->query->get('r') . "</p>";
                    $markup .= "<p>" . $this->t('Content') . ": " . $request->query->get('c') . "</p>";

                    $render = ['#markup' => $markup];
                    $params['body'] = \Drupal::service('renderer')->render($render);

                    $send = \Drupal::service('plugin.manager.mail')->mail(
                            'ek_admin', 'receipt', $user->getEmail(), $target_langcode, $params, "no-reply@" . $_SERVER['HTTP_HOST'], true
                    );
                }
            } else {
                // Register a per-user failed login event.
                $this->flood->register('mail.receipt', $flood_config->get('user_window'), $identifier);
            }
        } else {
            // Always register an IP-based failed login event.
            $this->flood->register('mail.receipt', $flood_config->get('ip_window'));
        }
        return new Response('', 204);
    }

    /**
     * Return default
     *
     */
    public function isDefault() {
        $site_config = \Drupal::config('system.site');
        $build = [];
        if (!\Drupal::currentUser()->isAuthenticated() && \Drupal::service('theme_handler')->themeExists('ek_login')) {
            $build['img'] = drupal_get_path('module', 'ek_admin') . "/css/images/";
            $build['form'] = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserLoginForm');
            $build['name'] = $site_config->get('name');
            return array(
                '#theme' => 'ek_login',
                '#items' => $build,
                '#title' => $this->t('login'),
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $build['welcome'] = array(
                '#markup' => '<h2>' . $this->t('Welcome to @s management site.', array('@s' => $site_config->get('name'))) . '</h2>',
            );

            $build['api']['adm'] = [
                'name' => 'default_homepage',
                'module' => 'Admin',
                'stamp' => 1582532880,
                'type' => "info",
                'content' => $this->t('This is your home page. You will find here updates and news about application features. Click on the start to mark it as read.'),
            ];
            $api = \Drupal::moduleHandler()->invokeAll('ek_home');
            $userData = \Drupal::service('user.data');
            foreach ($api as $k => $v) {
                $page = $v['module'] . "_" . $v['name'];
                if (!$userData->get('ek_admin', \Drupal::currentUser()->id(), $page)) {
                    $build['api'][$k] = $v;
                }
            }

            return [
                '#theme' => 'ek_admin_home',
                '#items' => $build,
                '#title' => $this->t('Back office management'),
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css', 'ek_admin/frontpage'),
                ),
                '#cache' => [
                    'tags' => ['home'],
                    'contexts' => ['user'],
                ],
            ];
        }
    }

    /**
     * Return main
     *
     */
    public function Admin() {

        //verify proper setup of the external database
        //all data tables will be generated by the admin module outside from the system database
        // database is called by 'external_db' key in the settings files
        //1/ the external DB exist? check from the settings key

        try {
            $external = Database::getConnectionInfo('external_db');
            if (!empty($external)) {
                $db = true;
            } else {
                $db = false;
            }
        } catch (Exception $e) {
            
        }

        if ($db == false) {
            $build['alert_db'] = array(
                '#markup' => '<h3>' . $this->t('You do not have external data database defined yet. You need to create one. '
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
                $master = $this->currentUser()->hasPermission('administer site configuration');
                if ($master) {
                    $build['updatephp'] = ['#markup' => '<p>' . $this->t('Always run the <a href=":update-php">update script</a> each time a module is updated.', [':update-php' => Url::fromRoute('system.db_update')->toString(),]) . '</p>'];
                }
                //countries
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_country', 'c')
                        ->condition('status', 1);
                $query->addExpression('Count(id)', 'count');
                $Obj = $query->execute();
                $count = $Obj->fetchObject()->count;
                if ($count < 1) {
                    $_SESSION['install'] = 1;
                    $link = Url::fromRoute('ek_admin.country.list', array(), array())->toString();
                    $build['country'] = array(
                        '#markup' => $this->t('You have not activated any country yet. Go <a href="@c">here</a> to activate countries.', array('@c' => $link)) . '<br/>',
                    );
                }

                //company
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_company', 'c')
                        ->fields('c', ['id']);
                $coids = $query->execute()->fetchCol();

                if (empty($coids)) {
                    $_SESSION['install'] = 1;
                    $link = Url::fromRoute('ek_admin.company.new', array(), array())->toString();
                    $build['company'] = array(
                        '#markup' => $this->t('You have not created any company yet. Go <a href="@c">here</a> to create one.', array('@c' => $link)) . '<br/>',
                    );
                }

                //settings and server data
                //Private file space
                if (!null == \Drupal\Core\StreamWrapper\PrivateStream::basePath()) {
                    $f = disk_free_space(\Drupal\Core\StreamWrapper\PrivateStream::basePath());
                    $Type = array("", "kilo", "mega", "giga", "tera", "peta", "exa", "zetta", "yotta");
                    $Index = 0;
                    while ($f >= 1024) {
                        $f /= 1024;
                        $Index++;
                    }
                    $build['space'] = round($f) . " " . $Type[$Index] . "bytes";
                } else {
                    $build['privateStream'] = $this->t("Set private data folder in <a href='@c'>configuration</a>.", ['@c' => './admin/config/media/file-system']);
                    $build['space'] = 'n/a';
                }
                //libraries
                $build['excel'] = (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) ? 1 : 0;
                $build['tcpdf'] = (class_exists('TCPDF')) ? 1 : 0;

                //verify if the connection to system DB is secured
                //when system database is remote (from drupal installation server) the connection should be done via ssl
                $query = "SHOW STATUS LIKE 'Ssl_cipher'";
                $ssl = Database::getConnection()
                                ->query($query)->fetchField();
                $build['ssl'] = ($ssl) ? 1 : 0;



                //address book
                if ($this->moduleHandler->moduleExists('ek_address_book')) {

                    //link to install
                    $query = "SHOW TABLES LIKE 'ek_address_book'";
                    $tb = Database::getConnection('external_db', 'external_db')
                                    ->query($query)->fetchField();
                    if ($tb != 'ek_address_book') {
                        return new RedirectResponse(Url::fromRoute('ek_address_book_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_assets_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_documents_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_hr_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_intelligence_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_logistics_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_messaging_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_products_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_projects.install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_sales_install')->toString());
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
                        return new RedirectResponse(Url::fromRoute('ek_finance_install')->toString());
                        exit;
                    }

                    $settings = new FinanceSettings();
                    $baseCurrency = $settings->get('baseCurrency');

                    if ($baseCurrency == null) {
                        $link = Url::fromRoute('ek_finance.admin.settings', array(), array())->toString();
                        $build['currency'] = array(
                            '#markup' => $this->t('No base currency selected. Go <a href="@c">here</a> to select a base currency.<br/>', array('@c' => $link)),
                        );
                    }
                }
            } else {
                //need to install tables for main module
                return new RedirectResponse(Url::fromRoute('ek_admin_install')->toString());
                exit;
            }

            $api = \Drupal::moduleHandler()->invokeAll('ek_settings', [$coids]);
            if (array_key_exists('documents', $api)) {
                $build['documents'] = $api['documents'];
            }
            if (array_key_exists('finance', $api)) {
                $build['finance'] = $api['finance'];
            }
            if (array_key_exists('logistics', $api)) {
                $build['logistics'] = $api['logistics'];
            }
            if (array_key_exists('projects', $api)) {
                $build['projects'] = $api['projects'];
            }
            if (array_key_exists('sales', $api)) {
                $build['sales'] = $api['sales'];
            }
        }

        return [
            '#theme' => 'ek_admin_settings',
            '#items' => $build,
            '#title' => $this->t('Administrate system data and settings'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        ];
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
        $build['#title'] = $this->t('Global settings');
        return $build;
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
                'id' => 'coid',
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'field' => 'name',
                'class' => array(),
                'id' => 'name',
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'status',
            ),
            'operations' => array(
                'data' => '',
                'width' => '20%',
                'id' => 'operations',
            ),
        );
        $i = 0;
        while ($r = $data->fetchObject()) {
            $i++;
            $lk = Url::fromRoute('ek_admin_modal', ['param' => 'clipboard|' . $r->id])->toString();
            $options[$i] = array(
                'id' => $r->id,
                'name' => ['data' => ['#markup' => '<a href="' . $lk . '" class="use-ajax clipboard_add"></a>' . $r->name . '']],
                'status' => ($r->active == '0') ? $this->t('non active') : $this->t('active'),
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
                'library' => array('ek_admin/ek_admin_css', 'core/drupal.ajax'),
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

        $items['back'] = $this->t('<a href="@c">List</a>', array('@c' => $link));


        return array(
            '#items' => $items,
            '#theme' => 'ek_admin_docs_view',
            '#attached' => array(
                'drupalSettings' => array('coid' => $id),
                'library' => array('ek_admin/ek_admin_docs_updater', 'ek_admin/ek_admin_css', 'ek_admin/classic_doc'),
            ),
        );
    }

    /**
     * @return html list
     *
     */
    public function load(Request $request) {
        $query = "SELECT * FROM {ek_company_documents} WHERE coid = :c order by id";
        $list = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':c' => $request->get('coid')));


        //build list of documents
        $t = '';
        $i = 0;
        $items = [];
        if (isset($list)) {
            while ($l = $list->fetchObject()) {
                $i++;
                $share = explode(',', $l->share);
                $deny = explode(',', $l->deny);

                if ($l->share == '0' || (in_array(\Drupal::currentUser()->id(), $share) && !in_array(\Drupal::currentUser()->id(), $deny))) {
                    $items[$i]['id'] = $l->id;
                    $items[$i]['fid'] = 0; //default file status off
                    $items[$i]['ico'] = '_doc_list';
                    $items[$i]['doc'] = $l->filename;
                    $items[$i]['comment'] = $l->comment;
                    $items[$i]['date'] = date('Y-m-d', $l->date);
                    $items[$i]['size'] = round($l->size / 1000, 0) . " Kb";
                    $items[$i]['del_button'] = 0;
                    $items[$i]['share_button'] = 0;

                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);
                    $icon_path = drupal_get_path('module', 'ek_admin') . '/art/ico/';
                    if (file_exists($icon_path . $extension . ".png")) {
                        $items[$i]['ico'] = $extension . '_doc_list';
                    }

                    if ($l->fid != '0') {
                        //file not deleted
                        $items[$i]['fid'] = 1;
                        if (!file_exists($l->uri)) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $items[$i]['comment'] = $this->t('Document not available. Please contact administrator');
                            $items[$i]['url'] = 0;
                        } else {
                            //file exist
                            $items[$i]['del_button'] = Url::fromRoute('ek_admin_confirm_delete_file', ['id' => $l->id])->toString();
                            $items[$i]['url'] = file_create_url($l->uri);

                            //$del_button = "<a id='d$i' href=" . $route . " class='use-ajax red fa fa-trash-o' title='" . $this->t('delete the file') . "'></a>";
                            //$doc = "<a href='" . file_create_url($uri) . "' target='_blank' >" . $l->filename . "</a>";
                            $param_access = 'access|' . $l->id . '|company_doc';
                            $link = Url::fromRoute('ek_admin_modal', ['param' => $param_access])->toString();
                            $items[$i]['share_button'] = Url::fromRoute('ek_admin_modal', ['param' => $param_access])->toString();
                            //$share_button = $this->t('<a href="@url" class="@c"  > access </a>', array('@url' => $link, '@c' => 'use-ajax red fa fa-lock'));
                        }
                    }
                } //in array
            }
        }
        $render = ['#theme' => 'ek_admin_list_docs_view', '#items' => $items];
        $data = \Drupal::service('renderer')->render($render);
        return new JsonResponse(array('list' => $data));
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
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

            case 'access':
                $id = $param[1];
                $type = $param[2];
                $content = $this->formBuilder->getForm('Drupal\ek_admin\Form\DocAccessEdit', $id, $type);
                $options = array('width' => '25%',);
                break;

            case 'clipboard':

                $title = $this->t('Copy address');
                $options = array('width' => '50%',);
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_company', 'c')
                        ->fields('c')
                        ->condition('id', $param[1], '=');
                $data = $query->execute()->fetchObject();
                $markup = "<p>"
                        . $data->name . "<br/>"
                        . "(" . $data->reg_number . ")<br/>"
                        . $data->address1 . "<br/>"
                        . $data->address2 . "<br/>"
                        . $data->city . "<br/>"
                        . $data->postcode . "<br/>"
                        . $data->country . "<br/>"
                        . $this->t('telephone') . " " . $data->telephone . "<br/>"
                        . $this->t('fax') . ": " . $data->fax . "<br/>"
                        . $this->t('email') . ": " . $data->email . "<br/>"
                        . $this->t('contact') . ": " . $data->contact . "<br/></p>";
                if (isset($data->address3) && $data->address3 != $data->address1) {
                    $markup .= "<p>"
                            . $data->address3 . "<br/>"
                            . $data->address4 . "<br/>"
                            . $data->city2 . "<br/>"
                            . $data->postcode2 . "<br/>"
                            . $data->country2 . "<br/>"
                            . $this->t('telephone') . ": " . $data->telephone2 . "<br/>"
                            . $this->t('fax') . ": " . $data->fax2 . "</p>";
                }

                $content['#markup'] = $markup;
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
     * Return ajax delete confirmation alert
     * @param $id document id
     * @return ajax response
     */
    public function confirmDeleteFile($id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company_documents', 'd')
                ->fields('d', ['filename'])
                ->condition('id', $id, '=');
        $file = $query->execute()->fetchField();
        $url = Url::fromRoute('ek_admin_delete_file', ['id' => $id])->toString();
        $content = array('content' =>
            array('#markup' =>
                "<div><a href='" . $url . "' class='use-ajax'>"
                . $this->t('delete') . "</a> " . $file . "</div>")
        );

        $response = new AjaxResponse();

        $title = $this->t('Confirm');
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        $response->addCommand(new OpenModalDialogCommand($title, $content));
        return $response;
    }

    /**
     * delete a file from list
     * @return Ajax response
     *
     */
    public function deleteFile($id) {
        $p = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_company_documents} WHERE id=:f", array(':f' => $id))
                ->fetchObject();
        $fields = array(
            'fid' => 0,
            'uri' => date('U'),
            'comment' => $this->t('deleted by') . ' ' . \Drupal::currentUser()->getAccountName(),
            'date' => time()
        );
        $delete = Database::getConnection('external_db', 'external_db')
                ->update('ek_company_documents')
                ->fields($fields)->condition('id', $id)
                ->execute();
        if ($delete) {
            $query = Database::getConnection()->select('file_managed', 'f');
            $query->fields('f');
            $query->condition('uri', $p->uri);
            $file = $query->execute()->fetchObject();
            //$query = "SELECT * FROM {file_managed} WHERE uri=:u";
            //$file = db_query($query, [':u' => $p->uri])->fetchObject();
            //file_delete($file->fid);
            if ($file->fid) {
                $obj = \Drupal\file\Entity\File::load($file->fid);
                $obj->setTemporary();
                $obj->save();
            }
        }


        $log = $p->id . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
        \Drupal::logger('ek_admin')->notice($log);

        $response = new AjaxResponse();
        $response->addCommand(new RemoveCommand('#ad' . $id));
        $response->addCommand(new CloseDialogCommand());
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
            '#title' => $this->t('New company'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     * Edit business entity
     * @param int id
     * @return array Form or content access denied
     */
    public function AdminCompanyEdit($id) {
        $access = AccessCheck::GetCompanyAccess($id);

        if (in_array(\Drupal::currentUser()->id(), $access[$id])) {
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCompanyForm', $id);
            $query = "SELECT name from {ek_company} where id=:id";
            $company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchField();
            return array(
                '#theme' => 'ek_admin_company_form',
                '#items' => $response,
                '#title' => $this->t('Edit company  - <small>@id</small>', array('@id' => $company)),
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $url = Url::fromRoute('ek_admin.company.list', [], [])->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('Access denied')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            return [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }
    }

    /**
     * Edit settings for business entity
     * @param int id
     * @return Form
     */
    public function AdminCompanyEditSettings($id) {
        $access = AccessCheck::GetCompanyAccess($id);
        if (in_array(\Drupal::currentUser()->id(), $access[$id])) {
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_admin\Form\EditCompanySettings', $id);
            $query = "SELECT name from {ek_company} where id=:id";
            $company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchField();
        } else {
            $url = Url::fromRoute('ek_admin.company.list', [], [])->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => $this->t('Access denied')];
            $items['link'] = ['#markup' => $this->t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            return [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }

        return array(
            '#theme' => 'ek_admin_company_form',
            '#items' => $response,
            '#title' => $this->t('Edit company settings - <small>@id</small>', array('@id' => $company)),
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
    public function excelcompany($id = null) {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
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
    public function pdfcompany($id = null) {
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
            '#title' => $this->t('List and edit countries'),
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
        $build['#title'] = $this->t('Edit company access by user');
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
        $build['#title'] = $this->t('Edit country access by user');
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


        if (isset($_SESSION['admin_user_select'])) {
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
        $alert = null;

        if ($terms == 1) {
            $sn .= substr($text, 0, 4);
        } elseif ($terms > 1) {
            for ($i = 0; $i <= 3; $i++) {
                if ($a[$i] == '') {
                    $sn .= '-';
                } else {
                    $sn .= substr($a[$i], 0, 1);
                }
            }
        }
        $sn = strtoupper($sn);

        //confirm entry
        $valid = '';
        for ($i = 0; $i <= $terms; $i++) {
            $valid .= $a[$i] . '%';
        }
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company', 'c')
                ->condition('name', $valid, 'LIKE');
        $query->addExpression('Count(name)', 'count');
        $Obj = $query->execute();
        $count = $Obj->fetchObject()->count;

        if ($count == 1) {
            $alert = $this->t('This name already exists in the records');
        }

        return new JsonResponse(array('sn' => $sn, 'name' => $count, 'alert' => $alert));
    }

    /**
     * User name autocomplete
     * @param request
     * @return Json response
     */
    public function userAutocomplete(Request $request) {
        $q = $request->query->get('q');
        $matches = [];
        $uids = $this->entityTypeManager->getStorage('user')->getQuery()
                ->condition('name', $q, 'STARTS_WITH')
                ->range(0, 10)
                ->execute();

        $controller = $this->entityTypeManager->getStorage('user');
        foreach ($controller->loadMultiple($uids) as $account) {
            if (!$account->isAnonymous()) {
                $matches[] = array('value' => $account->getAccountName(), 'label' => $account->getAccountName());
            }
        }

        return new JsonResponse($matches);
    }

    /*
     * function to send an email notification for tasks (cron)
     */

    private function send_mail($params) {
        $u = \Drupal\user\Entity\User::load($params['uid']);
        if ($u) {
            $params['options']['name'] = $u->getAccountName();
            $mail = $u->getEmail();

            $params['options']['subject'] = $params['subject'];

            if ($params['type'] == 'status') {
                $template = 'project_status';
                $params['options']['data'] = $params['data'];
            } elseif ($params['type'] == 'salert') {
                $template = 'sales_alert';
                $params['options']['data'] = $params['data'];
            } elseif ($params['type'] == 'hr_date') {
                $template = 'hr_date';
                $params['options']['data'] = $params['data'];
            } else { //default
                $template = 'tasks';
                $params['options']['link'] = $params['link'];
                $params['options']['serial'] = $params['serial'];
                $params['options']['task'] = $params['task'];
                $params['options']['end'] = $params['end'];
                $params['options']['alert'] = $params['alert'];

                $a = \Drupal\user\Entity\User::load($params['uid']);
                $params['options']['assign_name'] = '';
                if ($a) {
                    $params['options']['assign_name'] = $a->getAccountName();
                }
            }

            if ($mail) {
                if ($target_user = user_load_by_mail($mail)) {
                    $target_langcode = $target_user->getPreferredLangcode();
                } else {
                    $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
                }
                $send = \Drupal::service('plugin.manager.mail')->mail(
                        'ek_admin', $template, trim($mail), $target_langcode, $params, "no-reply@" . $_SERVER['HTTP_HOST'], true
                );
            } else {
                return false;
            }
        }
    }

    /**
     * reset read feature for home
     * @return Ajax response
     *
     */
    public function featureReset(Request $request) {
        $userData = \Drupal::service('user.data');
        $q = $request->query->get('q');
        if (!$userData->get('ek_admin', \Drupal::currentUser()->id(), $q)) {
            $userData->set('ek_admin', \Drupal::currentUser()->id(), $q, 1);
            return new JsonResponse(['action' => 1]);
        }
        return new JsonResponse(['action' => 0]);
    }

}
