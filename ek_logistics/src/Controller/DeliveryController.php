<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_logistics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_logistics\LogisticsSettings;

/**
 * Controller routines for ek module routes.
 */
class DeliveryController extends ControllerBase {
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
     * Return delivery orders list
     *
     */
    public function listdata(Request $request) {

        $build['filter_delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Filter');
        $header = array(
            'serial' => array(
                'data' => $this->t('number'),
                'class' => array(),
                'id' => 'Number',
            ),
            'reference' => array(
                'data' => $this->t('Reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'reference',
            ),
            'issuer' => array(
                'data' => $this->t('Issued by'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'issuer',
            ),
            'date' => array(
                'data' => $this->t('Date'),
                'field' => 'd.date',
            ),
            'delivery' => array(
                'data' => $this->t('Delivery'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'field' => 'd.ddate',
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'specifier' => 'status',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'status',
            ),
            'operations' => array(
                $this->t('Operations'),
                'id' => 'operations',
            ),
        );



        /*
         * Table - query data
         */


        $access = AccessCheck::GetCompanyByUser();
        $company_array = \Drupal\ek_admin\Access\AccessCheck::CompanyList();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_delivery', 'd');

        $or = $query->orConditionGroup();
        $or->condition('head', $access, 'IN');
        $or->condition('allocation', $access, 'IN');

        if (isset($_SESSION['lofilter']['filter']) && $_SESSION['lofilter']['filter'] == 1) {

            $status = $_SESSION['lofilter']['status'];
            $client = $_SESSION['lofilter']['client'];
            $from = $_SESSION['lofilter']['from'];
            $to = $_SESSION['lofilter']['to'];
        } else {
            $status = '%';
            $client = '%';
            $from = date('Y-m') . '-01';
            $to = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT date from {ek_logi_delivery} order by date DESC limit 1")
                    ->fetchField();
            if ($to < $from) {
                $to = $from;
            }
        }


        $data = $query
                ->fields('d', ['id', 'head', 'serial', 'client', 'status', 'title', 'date', 'ddate', 'pcode'])
                ->condition($or)
                ->condition('d.status', $status, 'like')
                ->condition('d.client', $client, 'like')
                ->condition('d.date', $from, '>=')
                ->condition('d.date', $to, '<=')
                ->extend('Drupal\Core\Database\Query\TableSortExtender')
                ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                ->limit(20)->orderByHeader($header)
                ->execute();

        while ($r = $data->fetchObject()) {

            $settings = new LogisticsSettings($r->head);
            $client = \Drupal\ek_address_book\AddressBookData::geturl($r->client, ['short' => 8]);
            $number = "<a title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_logistics.delivery.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            if ($r->pcode <> 'n/a') {
                if ($this->moduleHandler->moduleExists('ek_projects')) {
                    $reference = $client . "<br/>" . \Drupal\ek_projects\ProjectData::geturl($r->pcode);
                } else {
                    $reference = $client;
                }
            } else {
                $reference = $client;
            }

            if ($r->status == 0) {
                $status = "<i class='fa fa-circle green' aria-hidden='true'></i> " . t('open');
            }
            if ($r->status == 1) {
                $status = "<i class='fa fa-circle blue' aria-hidden='true'></i> " . t('printed');
            }
            if ($r->status == 2) {
                $status = "<i class='fa fa-circle yellow' aria-hidden='true'></i> " . t('invoiced');
            }
            if ($r->status == 3) {
                $status = "<i class='fa fa-circle red' aria-hidden='true'></i> " . t('posted');
            }
            $options[$r->id] = array(
                'number' => ['data' => ['#markup' => $number], 'title' => t('view in browser')],
                'reference' => ['data' => ['#markup' => $reference]],
                'issuer' => array('data' => $company_array[$r->head], 'title' => $r->title),
                'date' => $r->date,
                'delivery' => $r->ddate,
                'status' => ['data' => ['#markup' => $status]],
            );

            $links = array();

            if ($r->status == 0 || ($settings->get('edit') == 1 && $r->status == 1 ) || ($settings->get('edit') == 2 && $r->status == 2 )
            ) {
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_logistics_delivery_edit', ['id' => $r->id]),
                );
            }
            
            $links['clone'] = array(
                'title' => $this->t('Clone'),
                'url' => Url::fromRoute('ek_logistics_delivery_clone', ['id' => $r->id]),
            );

            if ($r->status == 1 && $this->moduleHandler->moduleExists('ek_sales')) {
                $links['invoice'] = array(
                    'title' => $this->t('Invoice'),
                    'url' => Url::fromRoute('ek_sales.invoices.do', ['id' => $r->id]),
                );
            }

            if ($r->status == 2) {
                $links['post'] = array(
                    'title' => $this->t('Post quantities'),
                    'url' => Url::fromRoute('ek_logistics_delivery_post', ['id' => $r->id]),
                );
            }


            if (\Drupal::currentUser()->hasPermission('print_share_delivery')) {

                $links['dprint'] = array(
                    'title' => $this->t('Print and share'),
                    'url' => Url::fromRoute('ek_logistics_delivery_print_share', ['id' => $r->id]),
                    'attributes' => ['class' => ['ico', 'pdf']]
                );
                /* @param: id,source,mode (0 download, 1 save),template, 0 = default */
                $param = serialize([$r->id, 'logi_delivery', 0, 0]);

                $links['dexcel'] = array(
                    'title' => $this->t('Excel download'),
                    'url' => Url::fromRoute('ek_logistics_delivery_excel', ['param' => $param]),
                    'attributes' => array('target' => '_blank', 'class' => ['ico', 'excel']),
                );
            }
            if (\Drupal::currentUser()->hasPermission('delete_delivery') && $r->status == 0) {

                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_logistics_delivery_delete', ['id' => $r->id]),
                );
            }

            $options[$r->id]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        } //while


        $build['logistics_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'logistics_table'),
            '#empty' => $this->t('No delivery order available.'),
            '#attached' => array(
                'library' => array('ek_logistics/ek_logistics_css', 'ek_admin/ek_admin_css'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
        );

        return $build;
    }

    /*
     * Edit a delivery order
     * @param int $id document id
     * @return array form
     */

    public function edit(Request $request, $id) {

        //filter edit
        if ($id) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_delivery', 'd')
                    ->fields('d', ['head', 'status'])
                    ->condition('id', $id, '=');
            $d = $query->execute()->fetchObject();
            $settings = new LogisticsSettings($d->head);
            if ($d->status == 0 || ($settings->get('edit') == 1 && $d->status == 1 ) || ($settings->get('edit') == 2 && $d->status == 2 )
            ) {
                $build['delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Delivery', $id);
            } else {
                $url = Url::fromRoute('ek_logistics_list_delivery', array(), array())->toString();
                $build['back'] = array(
                    '#markup' => t('Document not editable. Go to <a href="@url">List</a>.', array('@url' => $url)),
                );
            }
        } else {
            //new
            $build['delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Delivery', $id);
        }



        return $build;
    }

    /*
     * Upload a delivery orders from external source
     * 
     * @return array form
     */

    public function upload() {

        $build['delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\DeliveryUpload');

        return $build;
    }

    /*
     * Clone a delivery order
     * @param int $id document id
     * @return array form
     */

    public function cloneit(Request $request, $id) {
        $build['delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Delivery', $id, 'clone');

        return $build;
    }

    /*
     * Edit an alert for delivery
     * @param int $id document id
     * @return array form
     */

    public function alert(Request $request, $id) {
        $build['alert_delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\AlertDelivery', $id);

        return $build;
    }

    /**
     * @retun
     *  a display delivery in html format
     * 
     * @param 
     *  INT $id document id
     */
    public function Html($id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_logi_delivery} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();
        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\FilterPrint', $id, 'delivery', 'html');
            $document = '';

            if (isset($_SESSION['logisticprintfilter']['filter']) 
                    && $_SESSION['logisticprintfilter']['filter'] == $id
                    && $_SESSION['logisticprintfilter']['format'] == 'html') {

                $id = explode('_', $_SESSION['logisticprintfilter']['for_id']);
                $doc_id = $id[0];
                $param = serialize(
                        array(
                            0 => $id[0], //id
                            1 => 'logi_' . $id[1], //source
                            2 => $_SESSION['logisticprintfilter']['signature'],
                            3 => $_SESSION['logisticprintfilter']['stamp'],
                            4 => $_SESSION['logisticprintfilter']['template'],
                            5 => $_SESSION['logisticprintfilter']['contact'],
                        )
                );

                $format = 'html';
                $url_pdf = Url::fromRoute('ek_logistics_delivery_print_share', ['id' => $doc_id], [])->toString();
                $url_excel = Url::fromRoute('ek_logistics_delivery_excel', ['param' => serialize([$doc_id, 'logi_delivery', 0, 0])], [])->toString();
                $url_edit = Url::fromRoute('ek_logistics_delivery_edit', ['id' => $doc_id], [])->toString();
                include_once drupal_get_path('module', 'ek_logistics') . '/manage_print_output.inc';
                $build['delivery'] = [
                    '#markup' => $document,
                    '#attached' => array(
                        'library' => array('ek_logistics/ek_logistics_html_documents_css', 'ek_admin/ek_admin_css'),
                    ),
                ];
            } 
            return array($build);
        } else {
            $url = Url::fromRoute('ek_logistics_list_delivery')->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => t('Go to <a href="@url">List</a>.', ['@url' => $url])];
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

    /*
     * Print and share delivery order
     * @param int $id document id
     * @return array form for email sharing
     * @return pdf output
     */

    public function printshare(Request $request, $id) {

        //filter access to document
        $query = "SELECT `head`, `allocation` FROM {ek_logi_delivery} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, [':id' => $id])
                ->fetchObject();
        $access = AccessCheck::GetCompanyByUser();

        if (in_array($data->head, $access) || in_array($data->allocation, $access)) {

            $format = 'pdf';
            $build['filter_print'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\FilterPrint', $id, 'delivery', $format);

            if (isset($_SESSION['logisticprintfilter']['filter']) && $_SESSION['logisticprintfilter']['filter'] == $id) {

                $id = explode('_', $_SESSION['logisticprintfilter']['for_id']);

                $param = serialize(
                        array(
                            $id[0], //id
                            'logi_' . $id[1], //source
                            $_SESSION['logisticprintfilter']['signature'],
                            $_SESSION['logisticprintfilter']['stamp'],
                            $_SESSION['logisticprintfilter']['template'],
                            $_SESSION['logisticprintfilter']['contact'],
                        )
                );

                $build['filter_mail'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterMailDoc', $param);

                $path = $GLOBALS['base_url'] . "/logistics/delivery/pdf/" . $param;
                $iframe = "<iframe src ='" . $path . "' width='100%' height='1000px' id='view' name='view'></iframe>";
                $build['iframe'] = $iframe;
                $build['external'] = '<i class="fa fa-external-link" aria-hidden="true"></i>';
            }

            return array(
                '#items' => $build,
                '#theme' => 'iframe',
                '#attached' => array(
                    'library' => array('ek_logistics/ek_logistics_print', 'ek_admin/ek_admin_css'),
                ),
            );
        } else {
            $url = Url::fromRoute('ek_logistics_list_delivery')->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => t('You are not authorized to view this content')];
            $items['link'] = ['#markup' => t('Go to <a href="@url">List</a>.', ['@url' => $url])];
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

    /*
     * Generate Pdf document
     * @param serialized string $param
     * @return array of data
     * 
     */

    public function pdf(Request $request, $param) {
        $markup = array();
        $format = 'pdf';
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $product = TRUE;
        }
        include_once drupal_get_path('module', 'ek_logistics') . '/manage_print_output.inc';
        return $markup;
    }

    /*
     * Generate Excel document
     * @param serialized string $param
     * @return array of data
     * 
     */

    public function excel(Request $request, $param) {
        $markup = array();
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $product = TRUE;
        }
        include_once drupal_get_path('module', 'ek_logistics') . '/manage_excel_output.inc';
        return $markup;
    }

    /*
     * Post data from delivery to stock
     * @param int $id document id
     * @return array form
     * 
     */

    public function post(Request $request, $id) {
        $build['post_delivery'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Post', $id);

        return $build;
    }

    /*
     * Delete data 
     * @param int $id document id
     * @return array form
     * 
     */

    public function delete(Request $request, $id) {

        $query = "SELECT status,serial FROM {ek_logi_delivery} WHERE id=:id";
        $table = 'delivery';
        $opt = [0 => t('open'), 1 => t('printed'), 2 => t('invoiced'), 3 => t('posted')];
        $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchObject();

        if ($data->status > 0) {
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => t('@document cannot be deleted.', array('@document' => t('Delivery')))];
            $items['description'] = ['#markup' => $opt[$data->status]];
            $url = Url::fromRoute('ek_logistics_list_delivery', [], [])->toString();
            $items['link'] = ['#markup' => t("<a href=\"@url\">Back</a>", ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        } else {
            $build['delete_logistics'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\Delete', $id, $table, "D");
        }

        return $build;
    }

}
