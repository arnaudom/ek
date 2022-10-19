<?php

/**
 * @file
 * Contains \Drupal\ek_products\Controller\EkController.
 */

namespace Drupal\ek_products\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_products\ItemSettings;

/**
 * Controller routines for ek module routes.
 */
class ProductsController extends ControllerBase {

    /**
     * The module handler.
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
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(Connection $database, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->moduleHandler = $module_handler;
        $this->settings = new ItemSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('module_handler')
        );
    }

    /**
     * Return lookup item form
     *
     */
    public function searchproducts(Request $request) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\SearchProductsForm');

        return array(
            '#theme' => 'ek_products_search_form',
            '#items' => $response,
            '#title' => $this->t('Items'),
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    public function listproducts(Request $request) {
        $form_builder = $this->formBuilder();
        $build['filter_items'] = $form_builder->getForm('Drupal\ek_products\Form\FilterItemsList');

        $header = array(
            'id' => array(
                'data' => $this->t('Id'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'item-id',
            ),
            'itemcode' => array(
                'data' => $this->t('Item Code'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'item-code',
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'item-name',
            ),
            'image' => array(
                'data' => '',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'item-image',
            ),
            'operations' => array(
                'data' => '',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'operations',
            )
        );



        if (isset($_SESSION['itemfilter']['filter']) && $_SESSION['itemfilter']['filter'] == 1) {
            if ($_SESSION['itemfilter']['coid'] == '%') {
                $access = AccessCheck::GetCompanyByUser();
            } else {
                $access = array($_SESSION['itemfilter']['coid']);
            }

            $query = Database::getConnection('external_db', 'external_db')->select('ek_items', 'i');
            if ($_SESSION['itemfilter']['tag'] != '%') {
                //filter by tag.
                switch ($_SESSION['itemfilter']['tag']) {

                    case 'type':
                        $query->condition('type', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'department':
                        $query->condition('department', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'family':
                        $query->condition('family', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'collection':
                        $query->condition('collection', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'color':
                        $query->condition('color', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;
                }
            } else {
                //todo
            }

            $data = $query
                    ->fields('i', array('id', 'itemcode', 'description1'))
                    ->condition('active', $_SESSION['itemfilter']['status'], 'like')
                    ->condition('coid', $access, 'IN')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit($_SESSION['itemfilter']['paging'])
                    ->orderBy('id', 'ASC')
                    ->execute();

            $i = 0;
            $extract = array();

            while ($r = $data->fetchObject()) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_item_images', 'i');
                $query->fields('i');
                $query->condition('itemcode', $r->itemcode, '=');
                $query->orderBy('id', 'ASC');
                $query->range(null, 1);
                $item_img = $query->execute()->fetchObject();

                $img = '';
                if (isset($item_img->uri) && $item_img->uri != '' && file_exists($item_img->uri)) {
                    $thumb = "private://products/images/" . $r->id . "/40/40x40_" . basename($item_img->uri);
                    if (!file_exists($thumb)) {
                        $filesystem = \Drupal::service('file_system');
                        $dir = "private://products/images/" . $r->id . "/40/";
                        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                        $filesystem->copy($item_img->uri, $thumb, 'FILE_EXISTS_REPLACE');
                        //Resize after copy
                        $image_factory = \Drupal::service('image.factory');
                        $image = $image_factory->get($thumb);
                        $image->scale(40);
                        $image->save();
                    }

                    $mod = serialize(['content' => 'img', 'id' => $item_img->id, 'width' => '50%']);
                    $route = Url::fromRoute('ek_products_modal', ['param' => $mod])->toString();

                    $img = "<a href='" . $route . "' class='use-ajax'>"
                            . "<img class='thumbnail' src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($thumb) . "></a>";
                }

                $i++;
                $options[$i] = array(
                    'id' => $r->id,
                    'itemcode' => array('data' => $r->itemcode, 'title' => ''),
                    'name' => array('data' => $r->description1, 'title' => ''),
                    'image' => ['data' => ['#markup' => $img]],
                );

                $links = array();
                $links['view'] = array(
                    'title' => $this->t('View'),
                    'url' => Url::fromRoute('ek_products.view', ['id' => $r->id]),
                );
                $links['edit'] = array(
                    'title' => $this->t('Edit'),
                    'url' => Url::fromRoute('ek_products.edit', ['id' => $r->id]),
                );
                $links['clone'] = array(
                    'title' => $this->t('Clone'),
                    'url' => Url::fromRoute('ek_products.clone', ['id' => $r->id,]),
                );
                $links['delete'] = array(
                    'title' => $this->t('Delete'),
                    'url' => Url::fromRoute('ek_products.delete', ['id' => $r->id]),
                );

                $options[$i]['operations']['data'] = array(
                    '#type' => 'operations',
                    '#links' => $links,
                );

                //build array list of items for excel
                array_push($extract, $r->id);
            }
            $extract = serialize($extract);
            $excel = Url::fromRoute('ek_products.excel_items', array('param' => $extract), array())->toString();
            $build['excel'] = array(
                '#markup' => "<a href='" . $excel . "' title='" . $this->t('Excel download') . "'><span class='ico excel green'/></a>",
            );
            $build['items_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'items_table'),
                '#empty' => $this->t('No item found'),
                '#attached' => array(
                    'library' => array('ek_products/ek_products_css', 'ek_admin/admin_css'),
                ),
            );


            $build['pager'] = array(
                '#type' => 'pager',
            );
        }


        return $build;
    }

    /**
     * Render excel file for items list
     *
     * @param array $param id list
     */
    public function excelItemsList($param = null) {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $extract = unserialize($param);
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_items', 'i');
            $query->leftJoin('ek_item_packing', 'p', 'i.itemcode=p.itemcode');
            $query->leftJoin('ek_item_prices', 'c', 'i.itemcode=c.itemcode');
            $query->leftJoin('ek_company', 'y', 'i.coid=y.id');
            $query->leftJoin('ek_address_book', 'a', 'i.supplier=a.id');
            $data = $query
                    ->fields('i')->fields('p')->fields('c')->fields('y', ['name'])
                    ->fields('a', ['name'])
                    ->condition('i.id', $extract, 'IN')
                    ->orderBy('i.id', 'ASC')
                    ->execute();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_item_barcodes', 'b');
            $query->join('ek_items', 'i', 'i.itemcode=b.itemcode');
            $barcode_data = $query
                    ->fields('b', ['itemcode', 'barcode'])
                    ->condition('i.id', $extract, 'IN')
                    ->orderBy('b.id', 'ASC')
                    ->execute();
            $barcode = $barcode_data->fetchAll(\PDO::FETCH_ASSOC);
            $labels = [
                'selling_price_label' => $this->settings->get('selling_price_label'),
                'promo_price_label' => $this->settings->get('promo_price_label'),
                'discount_price_label' => $this->settings->get('discount_price_label'),
                'exp_selling_price_label' => $this->settings->get('exp_selling_price_label'),
                'exp_promo_price_label' => $this->settings->get('exp_promo_price_label'),
                'exp_discount_price_label' => $this->settings->get('exp_discount_price_label')
            ];
            $markup = array();
            include_once drupal_get_path('module', 'ek_products') . '/excel_items_list.inc';
        }
        return ['#markup' => $markup];
    }

    public function deleteproducts(Request $request, $id) {
        $query = "SELECT coid,i.itemcode,description1, active,units from {ek_items} i "
                . "INNER JOIN {ek_item_packing} p on i.itemcode=p.itemcode where i.id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        $access = AccessCheck::GetCompanyByUser();
        $coid = implode(',', $access);

        $del = 0;
        $usage = [];
        if (!in_array($data->coid, $access)) {
            $del = 1;
            $message = $this->t('You are not authorized to delete this item.');
        } elseif ($data->units <> 0) {
            $del = 1;
            $message = $this->t('The stock value for this item is not null: @u units. It cannot be deleted.', array('@u' => $data->units));
        } elseif ($this->moduleHandler->moduleExists('ek_sales') || $this->moduleHandler->moduleExists('ek_logistics')) {
            
            if ($this->moduleHandler->moduleExists('ek_sales')) {
                $query = 'SELECT count(id) from {ek_sales_invoice_details} WHERE itemdetail=:id';
                $invoice = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();
                if ($invoice > 0) {
                    $del = 2;
                    $usage[] = $this->t('Invoice');
                }
                $query = 'SELECT count(id) from {ek_sales_purchase_details} WHERE itemdetail=:id';
                $purchase = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();
                if ($purchase > 0) {
                    $del = 2;
                    $usage[] = $this->t('Purchase');
                }
                $query = 'SELECT count(id) from {ek_sales_quotation_details} WHERE itemid=:itemcode';
                $quotation = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':itemcode' => $data->itemcode))
                        ->fetchField();
                if ($quotation > 0) {
                    $del = 2;
                    $usage[] = $this->t('Quotation');
                }
            }

            if ($this->moduleHandler->moduleExists('ek_logistics')) {
                $query = 'SELECT count(id) FROM {ek_logi_delivery_details} WHERE itemcode=:itemcode';
                $delivery = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':itemcode' => $data->itemcode))
                        ->fetchField();
                if ($delivery > 0) {
                    $del = 2;
                    $usage[] = $this->t('Delivery');
                }
                $query = 'SELECT count(id) FROM {ek_logi_receiving_details} WHERE itemcode=:itemcode';
                $delivery = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':itemcode' => $data->itemcode))
                        ->fetchField();
                if ($delivery > 0) {
                    $del = 2;
                    $usage[] = $this->t('Receiving');
                }
            }
        }

        if ($del == 1 || $del == 2) {
            $modules = implode(', ', $usage);
            $items['type'] = 'delete';
            if ($del == 1) {
                $items['message'] = ['#markup' => $message];
            } else {
                $items['message'] = ['#markup' => $this->t('@document cannot be deleted.', array('@document' => $this->t('Item')))];
                $items['description'] = ['#markup' => $this->t('Used in @m', ['@m' => $modules])];
            }

            $url = Url::fromRoute('ek_products.view', ['id' => $id], [])->toString();
            $items['link'] = ['#markup' => $this->t("<a href=\"@url\">Back</a>", ['@url' => $url])];
            $build = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        } else {
            $form_builder = $this->formBuilder();
            $build['delete'] = $form_builder->getForm('Drupal\ek_products\Form\DeleteItem', $id);
        }


        return $build;
    }

    /**
     * Return a complete item data card.
     *
     */
    public function viewproducts(Request $request, $id) {

        //return the data
        $items = array();

        $url_search = Url::fromRoute('ek_products.search', array(), array())->toString();
        $items['search'] = $this->t('<a href="@url">New search</a>', array('@url' => $url_search));
        $url_list = Url::fromRoute('ek_products.list', array(), array())->toString();
        $items['list'] = $this->t('<a href="@url">List</a>', array('@url' => $url_list));

        $url_pdf = Url::fromRoute('ek_item.pdf', array('id' => $id), array())->toString();
        $items['pdf'] = $this->t('<a href="@url" target="_blank">Print</a>', array('@url' => $url_pdf));

        $query = "SELECT * from {ek_items} where id=:id";
        $r = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchAssoc();

        $query = "SELECT name from {ek_company} where id=:id";
        $items['company'] = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $r['coid']))
                ->fetchField();
        $items['itemcode'] = $r['itemcode'];
        $items['id'] = $r['id'];
        $items['type'] = $r['type'];
        $items['description1'] = $r['description1'];
        $items['description2'] = $r['description2'];
        $items['supplier_code'] = $r['supplier_code'];
        $s = array(0 => $this->t('active'), 1 => $this->t('active'));
        $items['active'] = $r['active'];
        $items['collection'] = $r['collection'];
        $items['department'] = $r['department'];
        $items['family'] = $r['family'];
        $items['size'] = $r['size'];
        $items['color'] = $r['color'];

        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $query = "SELECT name from {ek_address_book} where id=:id";
            $items['supplier'] = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $r['supplier']))->fetchField();
        } else {
            $items['supplier'] = '';
        }

        $items['stamp'] = date('Y-m-d', $r['stamp']);

        $query = "SELECT * from {ek_item_packing} where itemcode=:id";
        $k = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $items['itemcode']))
                ->fetchAssoc();

        $items['units'] = $k['units'];
        $items['unit_measure'] = $k['unit_measure'];
        $items['item_size'] = $k['item_size'];
        $items['pack_size'] = $k['pack_size'];
        $items['qty_pack'] = $k['qty_pack'];
        $items['c20'] = $k['c20'];
        $items['c40'] = $k['c40'];
        $items['min_order'] = $k['min_order'];


        $query = "SELECT * from {ek_item_prices} where itemcode=:id";
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $items['itemcode']))
                ->fetchAssoc();

        $items['purchase_price'] = $p['purchase_price'];
        $items['currency'] = $p['currency'];
        $items['date_purchase'] = date('Y-m-d', $p['date_purchase']);
        $items['selling_price'] = $p['selling_price'];
        $items['selling_price_label'] = $this->settings->get('selling_price_label');
        $items['promo_price'] = $p['promo_price'];
        $items['promo_price_label'] = $this->settings->get('promo_price_label');
        $items['discount_price'] = $p['discount_price'];
        $items['discount_price_label'] = $this->settings->get('discount_price_label');
        $items['exp_selling_price'] = $p['exp_selling_price'];
        $items['exp_selling_price_label'] = $this->settings->get('exp_selling_price_label');
        $items['exp_promo_price'] = $p['exp_promo_price'];
        $items['exp_promo_price_label'] = $this->settings->get('exp_promo_price_label');
        $items['exp_discount_price'] = $p['exp_discount_price'];
        $items['exp_discount_price_label'] = $this->settings->get('exp_discount_price_label');
        $items['loc_currency'] = $p['loc_currency'];
        $items['exp_currency'] = $p['exp_currency'];

        $query = "SELECT count(id) from {ek_item_barcodes} where itemcode=:id";
        $c = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $items['itemcode']))
                ->fetchField();

        $items['barcodes'] = array();
        if ($c > 0) {
            $query = "SELECT * from {ek_item_barcodes} where itemcode=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $items['itemcode']));
            if (class_exists('TCPDF2DBarcode')) {
                $add_barcode = true;
                include_once drupal_get_path('module', 'ek_products') . '/code.inc';
            }
            $barcodes = array();
            while ($r = $data->fetchAssoc()) {
                $barcodes['id'] = $r['id'];
                $barcodes['barcode'] = $r['barcode'];
                $barcodes['encode'] = $r['encode'];

                if ($add_barcode) {
                    $barcodes['image'] = barcode($r['barcode'], $r['encode'], [1, 20], 'black', 'html');
                }

                array_push($items['barcodes'], $barcodes);
            }
        }
        $items['pictures'] = array();

        $query = "SELECT count(id) from {ek_item_images} where itemcode=:id";
        $i = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $items['itemcode']))
                ->fetchField();
        if ($i > 0) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_item_images', 'i');
            $query->fields('i');
            $query->condition('itemcode', $items['itemcode'], '=');
            $query->orderBy('id', 'ASC');
            $data = $query->execute();
            $picture = array();
            while ($i = $data->fetchAssoc()) {
                $thumb = "private://products/images/" . $items['id'] . "/100/100x100_" . basename($i['uri']);
                $dir = "private://products/images/" . $items['id'] . "/";
                if (!file_exists($thumb) && file_exists($dir . basename($i['uri']))) {
                    $filesystem = \Drupal::service('file_system');
                    $dir = "private://products/images/" . $items['id'] . "/100/";
                    $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                    $filesystem->copy($i['uri'], $thumb, 'FILE_EXISTS_REPLACE');
                    //Resize after copy
                    $image_factory = \Drupal::service('image.factory');
                    $image = $image_factory->get($thumb);
                    $image->scale(100);
                    $image->save();
                }
                if ($i['uri'] <> '') {
                    $mod = serialize(['content' => 'img', 'id' => $i['id'], 'width' => '50%']);
                    $route = Url::fromRoute('ek_products_modal', ['param' => $mod])->toString();
                    $picture['element'] = "<a href='" . $route . "' class='use-ajax'><img class='thumbnail' src="
                            . \Drupal::service('file_url_generator')->generateAbsoluteString($thumb) . "></a>";
                } else {
                    $picture['element'] = '';
                }

                array_push($items['pictures'], $picture);
            }
        }


        $form_builder = $this->formBuilder();
        $form = $form_builder->getForm('Drupal\ek_products\Form\UploadForm', $items['id']);
        $items['upload_form'] = $form;

        return array(
            '#theme' => 'ek_products_card',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_products/ek_products_card'),
            ),
            '#cache' => [
                'tags' => ['item_card:' . $items['id']],
                'max-age' => 'PERMANENT',
            ],
        );
    }

    /**
     * @return
     * the item edition page.
     *
     */
    public function editproducts(Request $request, $id = null) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, null);
        $title = $this->t('Edit item  <small>@id</small>', array('@id' => $id));

        return array(
            '#theme' => 'ek_products_form',
            '#items' => $response,
            '#title' => $title,
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    /**
     * @return
     * the clone item edition page.
     *
     */
    public function cloneproducts(Request $request, $id = null) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, 'clone');
        $title = $this->t('Clone item <small>@id</small>', array('@id' => $id));

        return array(
            '#theme' => 'ek_products_form',
            '#items' => $response,
            '#title' => $title,
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    /**
     * @Return
     * the new item form page.
     *
     */
    public function newproducts(Request $request, $id = null) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, null);


        return array(
            '#theme' => 'ek_products_form',
            '#items' => $response,
            '#title' => $this->t('Create item'),
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    /**
     * Return tags and classification of items.
     *
     */
    public function tags(Request $request, $opt = null) {
        switch ($opt) {
            case 'type':
            default:
                $query = "SELECT DISTINCT type FROM {ek_items} WHERE type like :text";
                break;
            case 'department':
                $query = "SELECT DISTINCT department FROM {ek_items} WHERE department like :text";
                break;
            case 'family':
                $query = "SELECT DISTINCT family FROM {ek_items} WHERE family like :text";
                break;
            case 'collection':
                $query = "SELECT DISTINCT collection FROM {ek_items} WHERE collection like :text";
                break;
            case 'color':
                $query = "SELECT DISTINCT color FROM {ek_items} WHERE color like :text";
                break;
            case 'measure':
                $query = "SELECT DISTINCT unit_measure FROM {ek_item_packing} WHERE unit_measure like :text";
                break;
        }

        $a = array(':text' => $request->query->get('q') . '%');

        $data = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();
        return new JsonResponse($data);
    }

    /**
     * Return pdf document with all item details
     * @param id : item id
     */
    public function pdfitem($id) {
        if (!class_exists('TCPDF')) {
            $markup = ['#markup' => $this->t('Pdf library not available, please contact administrator.')];
            return $markup;
        } else {
            $access = AccessCheck::GetCompanyByUser();
            $i = Database::getConnection('external_db', 'external_db')
                    ->select('ek_items', 'i');

            $i->leftJoin('ek_item_packing', 'p', 'i.itemcode = p.itemcode');
            $i->leftJoin('ek_item_prices', 'c', 'i.itemcode = c.itemcode');
            $i->fields('i');
            $i->fields('p');
            $i->fields('c');
            $i->condition('i.id', $id, '=');
            $i->condition('coid', $access, 'IN');
            $result = $i->execute()->fetchObject();

            $query = "SELECT name,logo from {ek_company} where id=:id";
            $co = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $result->coid))
                    ->fetchObject();
            $items['company'] = $co->name;
            $items['company_logo'] = $co->logo;
            $items['itemcode'] = $result->itemcode;
            $items['id'] = $result->id;
            $items['type'] = $result->type;
            $items['description1'] = $result->description1;
            $items['description2'] = $result->description2;
            $items['supplier_code'] = $result->supplier_code;
            $s = array(0 => $this->t('active'), 1 => $this->t('active'));
            $items['active'] = $result->active;
            $items['collection'] = $result->collection;
            $items['department'] = $result->department;
            $items['family'] = $result->family;
            $items['size'] = $result->size;
            $items['color'] = $result->color;
            $items['supplier'] = '';
            if ($this->moduleHandler->moduleExists('ek_address_book')) {
                $query = "SELECT name from {ek_address_book} where id=:id";
                $items['supplier'] = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $result->supplier))
                        ->fetchField();
            }

            $items['stamp'] = date('Y-m-d', $result->stamp);

            //packing

            $items['units'] = $result->units;
            $items['unit_measure'] = $result->unit_measure;
            $items['item_size'] = $result->item_size;
            $items['pack_size'] = $result->pack_size;
            $items['qty_pack'] = $result->qty_pack;
            $items['c20'] = $result->c20;
            $items['c40'] = $result->c40;
            $items['min_order'] = $result->min_order;

            //prices

            $items['purchase_price'] = $result->purchase_price;
            $items['currency'] = $result->currency;
            $items['date_purchase'] = date('Y-m-d', $result->date_purchase);
            $items['selling_price'] = $result->selling_price;
            $items['selling_price_label'] = $this->settings->get('selling_price_label');
            $items['promo_price'] = $result->promo_price;
            $items['promo_price_label'] = $this->settings->get('promo_price_label');
            $items['discount_price'] = $result->discount_price;
            $items['discount_price_label'] = $this->settings->get('discount_price_label');
            $items['exp_selling_price'] = $result->exp_selling_price;
            $items['exp_selling_price_label'] = $this->settings->get('exp_selling_price_label');
            $items['exp_promo_price'] = $result->exp_promo_price;
            $items['exp_promo_price_label'] = $this->settings->get('exp_promo_price_label');
            $items['exp_discount_price_label'] = $this->settings->get('exp_discount_price_label');
            $items['exp_discount_price'] = $result->exp_discount_price;
            $items['loc_currency'] = $result->loc_currency;
            $items['exp_currency'] = $result->exp_currency;

            //barcode
            $query = "SELECT count(id) from {ek_item_barcodes} where itemcode=:id";
            $c = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $result->itemcode))
                    ->fetchField();

            $items['barcodes'] = array();
            if ($c > 0) {
                $query = "SELECT * from {ek_item_barcodes} where itemcode=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $result->itemcode));
                if (class_exists('TCPDF2DBarcode')) {
                    $add_barcode = true;
                    include_once drupal_get_path('module', 'ek_products') . '/code.inc';
                }
                $barcodes = array();
                while ($r = $data->fetchAssoc()) {
                    $barcodes['id'] = $r['id'];
                    $barcodes['barcode'] = $r['barcode'];
                    $barcodes['encode'] = $r['encode'];

                    if ($add_barcode) {
                        $barcodes['image'] = barcode($r['barcode'], $r['encode'], [1, 20], 'black', 'html');
                    }

                    array_push($items['barcodes'], $barcodes);
                }
            }
            $items['pictures'] = array();

            $query = "SELECT count(id) from {ek_item_images} where itemcode=:id";
            $c = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $items['itemcode']))
                    ->fetchField();
            if ($c > 0) {
                $query = "SELECT * from {ek_item_images} where itemcode=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $items['itemcode']));
                $picture = array();
                while ($r = $data->fetchAssoc()) {
                    if ($r['uri'] != '') {
                        $picture['uri'] = $r['uri'];
                    }

                    array_push($items['pictures'], $picture);
                }
            }

            //insert pdf
            include_once drupal_get_path('module', 'ek_products') . '/pdf_item.inc';
            return new \Symfony\Component\HttpFoundation\Response('', 204);
        }
    }

    /**
     * AJAX callback handler.
     * @param string $param
     */
    public function modal($param) {
        return $this->dialog(true, $param);
    }

    /**
     * AJAX callback handler.
     * @param string $param
     */
    public function nonModal($param) {
        return $this->dialog(false, $param);
    }

    /**
     * Util to render dialog in ajax callback.
     *
     * @param bool $is_modal
     *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
     * @param string $param
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = false, $param = null) {
        $opt = unserialize($param);
        $content = [];
        $options = '';
        switch ($opt['content']) {

            case 'img':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_item_images', 'i');
                $query->fields('i', ['uri']);
                $query->condition('id', $opt['id'], '=');
                $url = $query->execute()->fetchField();
                $title = $this->t('Image');
                $options = array('width' => $opt['width'],);
                $content['#markup'] = "<img src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($url) . ">";
                break;
        }

        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $response = new AjaxResponse();


        if ($is_modal) {
            $response->addCommand(new OpenModalDialogCommand($title, $content, $options));
        } else {
            $selector = '#ajax-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $options));
        }
        return $response;
    }

}
