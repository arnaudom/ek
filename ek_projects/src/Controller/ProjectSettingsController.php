<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_projects\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_projects\ProjectData;

/**
 * Controller routines for ek module routes.
 */
class ProjectSettingsController extends ControllerBase {
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
     * define specific access to project sections by users
     */
    public function users() {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_projects\Form\SettingsUsers');


        return array(
            '#theme' => 'ek_projects_default',
            '#items' => $response,
            '#title' => t('Users sections access control'),
            '#attached' => array(
                'library' => 'ek_projects/ek_projects_css',
            ),
        );
    }

    /**
     * define the project serial format
     */
    public function serial() {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_projects\Form\SerialFormat');
        $response['#title'] = t('Serial format');
        return $response;
    }

    /**
     * List projects per user
     */
    public function access_admin() {


        $build = array();
        $form_builder = $this->formBuilder();
        $build['filter_project'] = $form_builder->getForm('Drupal\ek_projects\Form\AccessAdmin');

        if (isset($_SESSION['paccessadmin']) && $_SESSION['paccessadmin']['filter'] == 1) {

            if ($_SESSION['paccessadmin']['cid'] == 0) {
                $cid = '%';
            } else {
                $cid = $_SESSION['paccessadmin']['cid'];
            }
            $uid = $_SESSION['paccessadmin']['uid'];
            $UserAccess = \Drupal\ek_admin\Access\AccessCheck::GetCountryByUser($uid);

            $query = Database::getConnection('external_db', 'external_db')->select('ek_project', 'p');
            $query->leftJoin('ek_project_description', 'd', 'd.pcode=p.pcode');
            $query
                    ->fields('p', array('id', 'cid', 'pname', 'pcode', 'status', 'category', 'date', 'archive', 'share', 'deny', 'owner'))
                    ->condition('cid', $cid, 'like')
                    ->condition('category', $_SESSION['paccessadmin']['type'], 'like')
                    ->condition('status', $_SESSION['paccessadmin']['status'], 'like')
                    ->condition('client_id', $_SESSION['paccessadmin']['client'], 'like');
            if ($_SESSION['paccessadmin']['supplier'] != '%') {
                $or = $query->orConditionGroup();
                $or->condition('supplier_offer', $_SESSION['paccessadmin']['supplier'] . ',%', 'like');
                $or->condition('supplier_offer', '%,' . $_SESSION['paccessadmin']['supplier'] . ',%', 'like');
                $or->condition('supplier_offer', '%,' . $_SESSION['paccessadmin']['supplier'], 'like');
                $or->condition('supplier_offer', $_SESSION['paccessadmin']['supplier'], '=');
                $query->condition($or);
            }

            $data = $query
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    //->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->orderBy('id', 'ASC')
                    ->execute();
            $countries = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name FROM {ek_country}")
                    ->fetchAllKeyed();

            $categories = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,type FROM {ek_project_type}")
                    ->fetchAllKeyed();
            $i = 0;
            while ($r = $data->fetchObject()) { 
                if (in_array($r->cid, $UserAccess)) {//filter access by country
                    $i++;
                    $pcode = ProjectData::geturl($r->id);
                    $share = explode(',', $r->share);
                    $deny = explode(',', $r->deny);
                    
                    $default = t('access');
                    if($uid == $r->owner) {
                        $default = t('owner');
                    } elseif(($r->share != 0 && $r->deny != 0) && (!in_array($uid, $share) || in_array($uid, $deny))) {
                        $default = t('access denied');
                    }

                    $options[$i] = array(
                        'reference' => ['data' => ['#markup' => $pcode]],
                        'date' => $r->date,
                        'name' => $r->pname,
                        'country' => $countries[$r->cid],
                        'category' => $categories[$r->category],
                        'status' => $r->status,
                        'operation' => ['data' => ['#markup' => $default]],
                    );
                }
            }
            $header = array(
                'pcode' => array(
                    'data' => $this->t('Reference'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    'id' => 'reference',
                ),
                'date' => array(
                    'data' => $this->t('Date'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'pname' => array(
                    'data' => $this->t('Name'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                    'id' => 'due',
                ),
                'country' => array(
                    'data' => $this->t('Country'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'category' => array(
                    'data' => $this->t('Category'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'status' => array(
                    'data' => $this->t('Status'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'operations' => array(
                    $this->t('Operations'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
            );
            
        $build['project_list'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'projects_access_table'),
            '#empty' => $this->t('No search result'),
            '#attached' => array(
                'library' => array('ek_projects/ek_projects_css'),
            ),
        );  
            
            
        }
        
        $build['#title'] = t('Users access by project');
        return $build;
    }

}
