<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_assets\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Controller routines for ek module routes.
 */
class AssetsController extends ControllerBase
{
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
    public static function create(ContainerInterface $container)
    {
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
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * Return list
     *
     */
    public function assetsList()
    {
        $new = Url::fromRoute('ek_assets.new')->toString();
        $build["new"] = array(
            '#markup' => "<a href='" . $new . "' >" . t('New asset') . "</a>",
        );
        $build['filter_assets_list'] = $this->formBuilder->getForm('Drupal\ek_assets\Form\FilterAssets');
        
        if (isset($_SESSION['assetfilter']['filter']) && $_SESSION['assetfilter']['filter'] == 1) {
            $header = array(
                'id' => array(
                    'data' => $this->t('ID'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'name' => array(
                    'data' => $this->t('Name'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'category' => array(
                    'data' => $this->t('Category'),
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),
                'location' => array(
                    'data' => $this->t('Registered'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'quantity' => array(
                    'data' => $this->t('Quantity'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'image' => array(
                    'data' => '',
                    'class' => array(RESPONSIVE_PRIORITY_LOW),
                ),

            );

            $header['operations'] = '';
            $access = AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            
            if ($_SESSION['assetfilter']['amort_status'] == '1') {
                $s = 0;
            } else {
                $s = '%';
            }
            //build the export link
            $param = serialize(
                array(
                'id' => 0,
                'coid' => $_SESSION['assetfilter']['coid'],
                'aid' => $_SESSION['assetfilter']['category'],
                'status' => $s
                )
            );
            $excel = Url::fromRoute('ek_assets.excel', array('param' => $param))->toString();
            $build['excel'] = array(
                '#markup' => "<a href='" . $excel . "' title='". t('Excel download') . "'><span class='ico excel green'/></a>",
            );
            $qrcode = Url::fromRoute('ek_assets.print-qrcode', array('param' => $param))->toString();
            $build['qrcode'] = array(
                '#markup' => "<a href='" . $qrcode . "' title='". t('Qr codes') . "' target='_blank'><span class='ico barcode'/></a>",
            );
            //get data base on criteria
            $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                    . "ON a.id = b.asid "
                    . "WHERE coid=:coid "
                    . "AND aid like :a "
                    . "AND amort_status like :s "
                    . "AND FIND_IN_SET (coid, :c)  order by id";
            $a = array(
                ':coid' => $_SESSION['assetfilter']['coid'],
                ':a' => $_SESSION['assetfilter']['category'],
                ':c' => $company,
                ':s' => $s,
            );

            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
            $query2 = "SELECT name FROM {ek_company} WHERE id=:coid";
            $company_name = Database::getConnection('external_db', 'external_db')
                    ->query($query2, array(':coid' => $_SESSION['assetfilter']['coid']))
                    ->fetchField();

            while ($r = $data->fetchObject()) {
                $query2 = "SELECT DISTINCT aname from {ek_accounts} where aid=:aid and coid=:coid";
                $a = array(
                    ':aid' => $r->aid,
                    ':coid' => $_SESSION['assetfilter']['coid'],
                );
                $aname = Database::getConnection('external_db', 'external_db')
                                ->query($query2, $a)->fetchField();

                if ($r->asset_pic != '') {
                    $img = "<a href='" . file_create_url($r->asset_pic) . "' target='_blank'>"
                            . "<img class='thumbnail' src=" . file_create_url($r->asset_pic) . "></a>";
                } else {
                    $img = '';
                }

                $options[$r->id] = array(
                    'id' => $r->id,
                    'name' => array('data' => $r->asset_name),
                    'aid' => $aname,
                    'location' => $company_name,
                    'quantity' => $r->unit,
                    'image' => ['data' => ['#markup' => $img]],
                    
                );

                $links = array();
                $links['view'] = array(
                    'title' => $this->t('View'),
                    'url' => Url::fromRoute('ek_assets.view', ['id' => $r->id]),
                    'route_name' => 'ek_assets.view',
                );
                $param = serialize(
                    array(
                    'id' => $r->id,
                    'coid' => $_SESSION['assetfilter']['coid'],
                    'aid' => $_SESSION['assetfilter']['category'],
                    'status' => $s
                    )
                );
                $links['qrcode'] = array(
                    'title' => $this->t('QRcode'),
                    'url' => Url::fromRoute('ek_assets.print-qrcode', ['param' => $param], ['attributes' =>['target' => '_blank']]),
                    'route_name' => 'ek_assets.view',
                );
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_assets.edit', ['id' => $r->id]),
                    'route_name' => 'ek_assets.edit',
                );
                if (\Drupal::currentUser()->hasPermission('amortize_assets')) {
                    $links['amort'] = array(
                        'title' => $this->t('Amortization'),
                        'url' => Url::fromRoute('ek_assets.set_amortization', ['id' => $r->id]),
                    );
                }
                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_assets.delete', ['id' => $r->id]),
                    'route_name' => 'ek_assets.delete',
                );
                $options[$r->id]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => $links,
                );
            }//loop



            $build['assets_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'assets_table'),
                '#empty' => $this->t('No asset'),
                '#attached' => array(
                    'library' =>[],
                ),
            );
        } else {
            $build['assets_table'] = array(
                '#markup' => t('Use filter to search assets'),
            );
        }
        
        return array(
            '#theme' => 'ek_assets_list',
            '#title' => t('List assets'),
            '#items' => $build,
            '#attached' => array(
                'library' => array('ek_assets/ek_assets_css','ek_admin/admin_css'),
            ),
            '#cache' => [
                'tags' => ['assets'],
            ],
        );
    }

    /**
     * Return view page
     *
     */
    public function assetsView(Request $request, $id)
    {
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $query = "SELECT * from {ek_assets} "
                . "WHERE id=:id "
                . "AND FIND_IN_SET (coid, :c)  order by id";
        $a = array(
            ':id' => $id,
            ':c' => $company,
        );

        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchObject();

        $items = array();
        $query2 = "SELECT name FROM {ek_company} WHERE id=:coid";
        $company_name = Database::getConnection('external_db', 'external_db')
                ->query($query2, array(':coid' => $data->coid))
                ->fetchField();
        $query2 = "SELECT DISTINCT aname from {ek_accounts} where aid=:aid and coid=:coid";
        $a = array(
            ':aid' => $data->aid,
            ':coid' => $data->coid,
        );
        $aname = Database::getConnection('external_db', 'external_db')
                        ->query($query2, $a)->fetchField();
        $items['id'] = $id;
        $items['company_name'] = $company_name;
        $items['asset_name'] = $data->asset_name;
        $items['asset_brand'] = $data->asset_brand;
        $items['asset_ref'] = $data->asset_ref;
        $items['unit'] = $data->unit;
        $items['aid'] = $data->aid;
        $items['aname'] = $aname;
        $items['asset_comment'] = $data->asset_comment;
        $items['asset_value'] = $data->asset_value;
        $items['currency'] = $data->currency;
        $items['date_purchase'] = $data->date_purchase;
        $items['amort_rate'] = $data->amort_rate;
        $items['amort_value'] = $data->amort_value;
        $items['amort_yearly'] = $data->amort_yearly;
        $status = array(0 => t('not amortized'), 1 => t('amortized'));
        $items['amort_status'] = $status[$data->amort_status];
        $items['picture'] = '';
        if ($data->asset_pic != '') {
            $items['picture'] = file_create_url($data->asset_pic);
        }
        if ($data->asset_doc != '') {
            $items['doc_url'] = file_create_url($data->asset_doc);
            
            $items['doc'] = basename($items['doc_url']);
        } else {
            $items['doc'] = '';
        }
        /**/
        if (class_exists('TCPDF2DBarcode')) {
            include_once drupal_get_path('module', 'ek_assets') . '/code.inc';
            $qr_text = t('ID') . ': ' . $data->id . ', ' . t('Name') . ': '
                       . $data->asset_name . ', ' . t('Company') . ': ' . $company_name . ', '
                       . t('Date of purchase') . ': ' . $data->date_purchase . ', '
                       . t('Reference') . ': ' . $data->asset_ref . ', '
                       . t('Category') . ': ' . $aname;
           
            $items['qr_code_html'] = qr_code($qr_text, 'QRCODE,H', '2', 'black', 'html');
            $items['qr_code_svg'] = qr_code($qr_text, 'QRCODE,H', '3', 'black', 'svg');//"<IMG src ='data:image,".  . "' />";
        }
        if ($this->moduleHandler->moduleExists('ek_hr')) {
            $check = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce')
                ->fields('ek_hr_workforce', ['name', 'id'])
                ->condition('id', $data->eid, '=')
                ->execute()
                ->fetchObject();
            if ($check->name) {
                $items['eid'] = $check->id;
                $items['employee'] = $check->name;
                $items['eurl'] = Url::fromRoute('ek_hr.employee.view', ['id' => $check->id])->toString();
            }
        }
            
        return array(
            '#theme' => 'ek_assets_card',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_assets/ek_assets_css'),
            ),
        );
    }

    /**
     * Return edit form for new asset
     *
     */
    public function assetsNew(Request $request)
    {
        $build['form_assets_new'] = $this->formBuilder->getForm('Drupal\ek_assets\Form\EditForm', 0);
        $build['#attached']['library'] = array('ek_assets/ek_assets_css', 'ek_assets/ek_assets.number_format');
        return $build;
    }

    /**
     * Return edit form
     *
     */
    public function assetsEdit(Request $request, $id)
    {
        $build['form_assets_edit'] = $this->formBuilder->getForm('Drupal\ek_assets\Form\EditForm', $id);
        $build['#attached']['library'] = array('ek_assets/ek_assets_css', 'ek_assets/ek_assets.number_format');
        return $build;
    }

    /**
     * Return delete form
     *
     */
    public function assetsDelete(Request $request, $id)
    {
        $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        $access = AccessCheck::GetCompanyByUser();
        $coid = implode(',', $access);

        $del = '1';
        //if(!in_array(\Drupal::currentUser()->id(), $access)) {
        if (!in_array($data->coid, $access)) {
            $del = '0';
            $message = t('You are not authorized to delete this item.');
        } elseif ($data->amort_record != '') {
            $del = '0';
            $message = t('This asset is not amortized. It cannot be deleted.');
        }

        if ($del == '1') {
            $build['form_assets_edit'] = $this->formBuilder->getForm('Drupal\ek_assets\Form\DeleteForm', $data->asset_name, 1);
            $build['#attached']['library'] = array('ek_assets/ek_assets_css');
        } else {
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => $message];
            $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
            $items['link'] = ['#markup' => t('Go to <a href="@url">List</a>.', ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        }
        return $build;
    }

    /**
     * Return export of list in excel format
     *
     */
    public function assetsExcel($param)
    {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            $options = unserialize($param);
            $markup = array();
            $status = array(0 => (STRING)t('not amortized'), 1 => (STRING)t('amortized'));
            $access = AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            $query2 = "SELECT name FROM {ek_company} WHERE id=:coid";
            $company_name = Database::getConnection('external_db', 'external_db')
                    ->query($query2, array(':coid' => $options['coid']))
                    ->fetchField();
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_assets', 'a');
            $query->fields('a');
            $query->leftJoin('ek_assets_amortization', 'b', 'a.id = b.asid');
            $query->fields('b');
            
            if ($this->moduleHandler->moduleExists('ek_hr')) {
                $query->leftJoin('ek_hr_workforce', 'c', 'c.id=a.eid');
                $query->fields('c', ['id', 'name']);
            }
            $query->condition('coid', $options['coid'], '=');
            $query->condition('aid', $options['aid'], 'like');
            $query->condition('amort_status', $options['status'], 'like');
            $query->condition('coid', $access, 'IN');
                       
            $result =  $query->execute();

            include_once drupal_get_path('module', 'ek_assets') . '/excel_list.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * @parm id
     *  asset id
     * @return print pdf output
     *
     */
    public function assetsPrint($id)
    {
        if (!class_exists('TCPDF')) {
            $markup = ['#markup' => t('Pdf library not available, please contact administrator.')];
            return $markup;
        } else {
            $access = AccessCheck::GetCompanyByUser();
            $company = implode(',', $access);
            $query = "SELECT * from {ek_assets} "
                    . "WHERE id=:id "
                    . "AND FIND_IN_SET (coid, :c)  order by id";
            $a = array(
                ':id' => $id,
                ':c' => $company,
            );

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchObject();

            $items = array();
            $query2 = "SELECT name,logo FROM {ek_company} WHERE id=:coid";
            $company = Database::getConnection('external_db', 'external_db')
                    ->query($query2, array(':coid' => $data->coid))
                    ->fetchObject();
            $query2 = "SELECT DISTINCT aname from {ek_accounts} where aid=:aid and coid=:coid";
            $a = array(
                ':aid' => $data->aid,
                ':coid' => $data->coid,
            );
            $aname = Database::getConnection('external_db', 'external_db')
                            ->query($query2, $a)->fetchField();
            $items['id'] = $id;
            $items['company_name'] = $company->name;
            $items['company_logo'] = $company->logo;
            $items['asset_name'] = $data->asset_name;
            $items['asset_brand'] = $data->asset_brand;
            $items['asset_ref'] = $data->asset_ref;
            $items['unit'] = $data->unit;
            $items['aid'] = $data->aid;
            $items['aname'] = $aname;
            $items['asset_comment'] = $data->asset_comment;
            $items['asset_value'] = $data->asset_value;
            $items['currency'] = $data->currency;
            $items['date_purchase'] = $data->date_purchase;
            $items['amort_rate'] = $data->amort_rate;
            $items['amort_value'] = $data->amort_value;
            $items['amort_yearly'] = $data->amort_yearly;
            $status = array(0 => t('not amortized'), 1 => t('amortized'));
            $items['amort_status'] = $status[$data->amort_status];
            if ($data->asset_pic != '' && file_exists($data->asset_pic)) {
                $items['picture'] = $data->asset_pic;
            } else {
                $items['picture'] = '';
            }
            if ($data->asset_doc != '') {
                $parts = explode("/", $data->asset_doc);
                $parts = array_reverse($parts);
                $name = $parts[0];
                $items['doc_name'] = $name;
                $items['doc'] = $data->asset_doc;
            } else {
                $items['doc'] = '';
            }
            /*qr_code*/
            $qr_text = t('ID') . ': ' . $data->id . ', ' . t('Name') . ': '
                       . $data->asset_name . ', ' . t('Company') . ': ' . $company->name . ', '
                       . t('Date of purchase') . ': ' . $data->date_purchase;
               
            $items['qr_text'] = $qr_text;
               
            if ($this->moduleHandler->moduleExists('ek_hr')) {
                $check = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce')
                ->fields('ek_hr_workforce', ['name', 'id'])
                ->condition('id', $data->eid, '=')
                ->execute()
                ->fetchObject();
                if ($check->name) {
                    $items['eid'] = $check->id;
                    $items['employee'] = $check->name;
                    $items['eurl'] = Url::fromRoute('ek_hr.employee.view', ['id' => $check->id])->toString();
                }
            }
            
            include_once drupal_get_path('module', 'ek_assets') . '/pdf_asset.inc';
        }
    }
    
    /**
     * @parm param
     *  printing parameters
     *  int id = asset id , 0 for list
     *  int coid = company id
     *  string aid = account id or  '%'
     *  string status = flag 0 or '%'
     * @return print pdf output
     *
     */
    public function assetsPrintQrcode($param)
    {
        $params = unserialize($param);
        
        if (in_array($params['coid'], \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser())) {
        
            
                //print all from list
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_assets', 'a');
            $query->fields('a', ['id','asset_name','date_purchase','coid','eid','asset_ref','aid']);
            $query->leftJoin('ek_assets_amortization', 'b', 'a.id = b.asid');
            $query->fields('b');
            $query->leftJoin('ek_company', 'c', 'a.coid = c.id');
            $query->fields('c', ['name']);
            $query->condition('coid', $params['coid'], '=');
            $query->condition('aid', $params['aid'], 'like');
            $query->condition('amort_status', $params['status'], 'like');
            if ($params['id'] != '%') {
                $query->condition('a.id', $params['id'], '=');
            }
            $data = $query->execute();
            $print = [];
                
            while ($d = $data->fetchObject()) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a');
                $query->fields('a', ['aname']);
                $query->condition('coid', $d->coid, '=');
                $query->condition('aid', $d->aid, '=');
                $account = $query->execute()->fetchField();
                    
                $qrcode = t('ID') . ': ' . $d->id . ', ' . t('Name') . ': '
                       . $d->asset_name . ', ' . t('Company') . ': ' . $d->name . ', '
                       . t('Date of purchase') . ': ' . $d->date_purchase . ', '
                       . t('Reference') . ': ' . $d->asset_ref . ', '
                       . t('Category') . ': ' . $account;
                $assigned = isset($d->eid) ? 1 :0;
                    
                $print[] = [
                        'id' => $d->id,
                        'reference' => $d->asset_ref,
                        'name' => $d->asset_name,
                        'company' => $d->name,
                        'assigned' => $assigned,
                        'qrcode' => $qrcode,
                    ];
            }
                
            include_once drupal_get_path('module', 'ek_assets') . '/qrcode.inc';
            return new \Symfony\Component\HttpFoundation\Response('', 204);
        } else {
            $url = Url::fromRoute('ek_assets.listl', [], [])->toString();
            $items['type'] = 'access';
            $items['message'] = ['#markup' => t('You are not authorized to print this information')];
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
    
    //end class
}
