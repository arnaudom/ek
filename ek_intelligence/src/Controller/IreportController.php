<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_intelligence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Controller routines for ek module routes.
 */
class IreportController extends ControllerBase {
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
     * Return list of reports filtered by access
     *
     */
    public function reports() {
        if (\Drupal::currentUser()->hasPermission('generate_i_report')) {
            $new = Url::fromRoute('ek_intelligence.new')->toString();
            $build["new"] = array(
                '#markup' => "<a href='" . $new . "' >" . $this->t('New report') . "</a>",
            );
        }

        $build['filter_ireport_list'] = $this->formBuilder->getForm('Drupal\ek_intelligence\Form\FilterReports');
        $header = array(
            'id' => array(
                'data' => $this->t('ID'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'serial' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'type' => array(
                'data' => $this->t('Category'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'assign' => array(
                'data' => $this->t('Assigned'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => '',
        );

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        if ($_SESSION['ireports']['filter'] == 1) {
            //get data base on criteria

            $query = Database::getConnection('external_db', 'external_db')->select('ek_ireports', 'i');
            $data = $query
                    ->fields('i', array('id', 'serial', 'type', 'coid', 'date', 'assign', 'status'))
                    ->condition('i.coid', $_SESSION['ireports']['coid'], '=')
                    ->condition('i.type', $_SESSION['ireports']['type'], 'like')
                    ->condition('i.date', $_SESSION['ireports']['from'], '>=')
                    ->condition('i.coid', $access, 'in')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('i.id', 'ASC')
                    ->execute();

            $type = array(1 => $this->t('briefing'), 2 => $this->t('report'), 3 => $this->t('training'));
            $status = array(1 => $this->t('active'), 0 => $this->t('closed'));

            while ($r = $data->fetchObject()) {
                if ($r->assign > 1) {
                    $user = User::load($r->assign)->getAccountName();
                } else {
                    $user = $this->t('not assigned');
                }
                $url = Url::fromRoute('ek_intelligence.read', array('id' => $r->id))
                        ->toString();
                $serial = '<a href="' . $url . '">' . $r->serial . '</a>';
                $options[$r->id] = array(
                    'id' => $r->id,
                    'serial' => array('data' => ['#markup' => $serial]),
                    'type' => $type[$r->type],
                    'date' => $r->date,
                    'assign' => $user,
                    'status' => $status[$r->status],
                );

                $links = array();
                if ($r->status == 1) {
                    $links['view'] = array(
                        'title' => $this->t('Edit'),
                        'url' => Url::fromRoute('ek_intelligence.write', ['id' => $r->id]),
                        'route_name' => 'ek_intelligence.write',
                    );
                }
                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_intelligence.delete', ['id' => $r->id]),
                    'route_name' => 'eek_intelligence.delete',
                );
                $options[$r->id]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => $links,
                );
            }



            $build['ireports_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'ireports_table'),
                '#empty' => $this->t('No report'),
                '#attached' => array(
                    'library' => array('ek_intelligence/ek_intelligence_css'),
                ),
            );
            $build['pager'] = array(
                '#type' => 'pager',
            );
        } else {
            $build['alert'] = array(
                '#markup' => $this->t('Use filter to search reports'),
            );
        }
        return $build;
    }

    /**
     * Return a write form with management option for manager access
     * with generate_i_report privilege
     *
     */
    public function write(Request $request, $id) {
        $build['ireport_write'] = $this->formBuilder->getForm('Drupal\ek_intelligence\Form\WriteReport', $id);
        return $build;
    }

    /**
     * Return edit form for generating a new report lead
     *
     */
    public function newReport(Request $request) {
        $build['ireport_new'] = $this->formBuilder->getForm('Drupal\ek_intelligence\Form\NewReport');
        return $build;
    }

    /**
     * Return a read report page
     *
     *
     */
    public function readReport(Request $request, $id) {
        $items = array();
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $u = \Drupal::currentUser()->id();
        if (\Drupal::currentUser()->hasPermission('generate_i_report')) {
            //user with permission and company access can see the report
            $items['permission'] = 1;
            $query = "SELECT * from {ek_ireports} WHERE id=:id "
                    . "AND FIND_IN_SET (coid, :c )";
            $a = array(':id' => $id, ':c' => $company);
        } else {
            //user assigned only can view the report
            $items['permission'] = 2;
            $query = "SELECT * from {ek_ireports} WHERE id=:id "
                    . "AND assign = :a AND FIND_IN_SET (coid, :c )";
            $a = array(':id' => $id, ':a' => $u, ':c' => $company);
        }


        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchObject();
        if ($data) {
            $items['id'] = $id;
            $items['date'] = $data->date;
            $items['serial'] = $data->serial;
            $items['pcode'] = $data->pcode;
            $items['description'] = $data->description;
            $type = array(1 => $this->t('briefing'), 2 => $this->t('report'), 3 => $this->t('training'));
            $status = array(1 => $this->t('active'), 0 => $this->t('closed'));
            $items['status'] = $status[$data->status];
            $items['type'] = $type[$data->type];
            if ($data->assign > 1) {
                $items['assign'] = User::load($data->assign)->getAccountName();
            } else {
                $items['assign'] = $this->t('not assigned');
            }
            if ($data->owner > 1) {
                $items['owner'] = User::load($data->owner)->getAccountName();
            } else {
                $items['owner'] = $this->t('no data');
            }
            if ($data->edit) {
                $items['edit'] = date('Y-m-d h:i', $data->edit);
            } else {
                $items['edit'] = $this->t('not edited');
            }
            $items['report'] = nl2br(unserialize($data->report));
            //$items['report'] = nl2br($data->report);
            $link = Url::fromRoute('ek_intelligence.write', ['id' => $id])->toString();
            $items['write'] = $this->t('<a href="@url" >write</a>', array('@url' => $link));
            $link = Url::fromRoute('ek_intelligence.export', ['id' => $id])->toString();
            $items['export'] = $this->t('<a href="@url" >export</a>', array('@url' => $link));

            return array(
                '#items' => $items,
                '#title' => $this->t('Report'),
                '#theme' => 'ek_ireport_data',
                '#attached' => array(
                    'library' => array('ek_intelligence/ek_intelligence_css'),
                ),
            );
        } else {
            return array(
                '#markup' => $this->t('Report access restricted'),
            );
        }
    }

    /**
     * Return delete form
     *
     */
    public function delete(Request $request, $id) {
        $build['form_ireport_del'] = $this->formBuilder->getForm('Drupal\ek_intelligence\Form\DeleteForm', $id);
        $build['#attached']['library'] = array('ek_intelligence/ek_intelligence_css');
        return $build;
    }

    /**
     * Return print pdf output
     *
     */
    public function reportExport(Request $request, $id) {
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $u = \Drupal::currentUser()->id();
        if (\Drupal::currentUser()->hasPermission('generate_i_report')) {
            //user with permission and company access can edit
            //if owner is later remove from company access he/she cannot edit anymore
            $permission = 1;
            $query = "SELECT owner from {ek_ireports} WHERE id=:id "
                    . "AND FIND_IN_SET (coid, :c )";
            $a = array(':id' => $id, ':c' => $company);
        } else {
            //user assigned only can view the report
            $permission = 2;
            $query = "SELECT assign from {ek_ireports} WHERE id=:id "
                    . "AND  FIND_IN_SET (coid, :c )";
            $a = array(':id' => $id, ':c' => $company);
        }
        $edit = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchField();

        if ($u == $edit) {
            $query = "SELECT * from {ek_ireports} WHERE id=:id ";
            $a = array(':id' => $id);
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchObject();
            if ($data->assign > 0) {
                $account = \Drupal\user\Entity\User::load($data->assign);
                $assign = '';
                if ($account) {
                    $assign = $account->getAccountName();
                }
            } else {
                $assign = $this->t('not assigned');
            }
            if ($data->owner > 0) {
                $account = \Drupal\user\Entity\User::load($data->owner);
                $owner = '';
                if ($account) {
                    $owner = $account->getAccountName();
                }
            } else {
                $owner = $this->t('no data');
            }
            $temp = \Drupal::service('file_system')->getTempDirectory();

            $fileName = $data->serial . '_' . 'report.docx';
            $path = "private://intelligence/reports/" . $data->coid;
            \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);


            $markup = array();
            include_once drupal_get_path('module', 'ek_intelligence') . '/word_report.inc';
            return $markup;
        } else {
            return array(
                '#markup' => $this->t('You cannot export this report'),
            );
        }
    }

    //end class
}
