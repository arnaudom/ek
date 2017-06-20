<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_products\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Extension\ModuleHandler;
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
            '#title' => t('Items'),
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    /**
     * Util to return item description callback.
     * @param option
     *  image: to return an image link with reponse
     * @param id
     *  a company id to filter by company, 0 for include all
     * @param term
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxlookupitem(Request $request, $id) {

        $term = $request->query->get('q');
        $option = $request->query->get('option');

        if ($id == '0') {
           
            $a = array(':t0' => "$term%", ':t1' => "$term%", ':t2' => "$term%", ':t3' => "$term%", ':t4' => "$term%");
            $query = "SELECT distinct ek_items.id, ek_items.itemcode, description1, barcode,supplier_code,uri FROM {ek_items} "
                    . "LEFT JOIN {ek_item_barcodes} "
                    . "ON ek_items.itemcode=ek_item_barcodes.itemcode "
                    . "LEFT JOIN {ek_item_images} "
                    . "ON ek_items.itemcode=ek_item_images.itemcode "
                    . "WHERE ek_items.id like :t0 "
                    . "OR ek_items.itemcode like :t1 "
                    . "OR description1 like :t2 "
                    . "OR barcode like :t3 "
                    . "OR supplier_code like :t4";
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
             
                    
        } else {
            /* */
            $a = array(':c' => $id, ':t0' => "$text%", ':t1' => "$text%", ':t2' => "$text%", ':t3' => "%$text%", ':t4' => "$text%");
            $query = "SELECT distinct ek_items.id, ek_items.itemcode, description1, barcode,supplier_code "
                    . "FROM {ek_items} "
                    . "LEFT JOIN {ek_item_barcodes} "
                    . "ON ek_items.itemcode=ek_item_barcodes.itemcode "
                    . "WHERE ek_items.coid =:c "
                    . "AND (ek_items.id like :t0 "
                    . "OR ek_items.itemcode like :t1 "
                    . "OR description1 like :t2 "
                    . "OR barcode like :t3 "
                    . "OR supplier_code like :t4)";
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
             
            
        }
        
        $name = array();
        while ($r = $data->fetchObject()) {

            if (strlen($r->description1) > 30) {
                $desc = substr($r->description1, 0, 30) . "...";
            } else {
                $desc = $r->description1;
            }
            
            if($option == 'image') {
                $line = [];
                if ($r->uri) {
                         $pic = "<img class='product_thumbnail' src='"
                        . file_create_url($r->uri) . "'>";
                    } else {
                        $default = file_create_url(drupal_get_path('module', 'ek_products') . '/css/images/default.jpg');
                        $pic = "<img class='product_thumbnail' src='"
                        . $default . "'>";
                    }
                    $line['picture'] = isset($pic) ? $pic : '';
                    $line['description'] = $desc;
                    $line['name'] = $r->id . " " . $r->itemcode . " " . $r->barcode . " " . $desc . " " .$r->supplier_code;
                    $line['id'] = $r->id;
                    
                    $name[] = $line;
                
            } else {
                $name[] = $r->id . " " . $r->itemcode . " " . $r->barcode . " " . $desc . " " .$r->supplier_code;
            }
           
        }
        return new JsonResponse($name);
    }

    public function listproducts(Request $request) {

        $form_builder = $this->formBuilder();
        $build['filter_items'] = $form_builder->getForm('Drupal\ek_products\Form\FilterItemsList');

        $header = array(
            'id' => array(
                'data' => $this->t('Id'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'itemcode' => array(
                'data' => $this->t('Item Code'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'name' => array(
                'data' => $this->t('Name'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'image' => array(
                'data' => '',
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => array(
                'data' => '',
                'width' => '15%',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
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

                    case 'type' :
                        $query->condition('type', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'department' :
                        $query->condition('department', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'family' :
                        $query->condition('family', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'collection' :
                        $query->condition('collection', $_SESSION['itemfilter']['tagvalue'], '=');
                        break;

                    case 'color' :
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
                
                $query = "SELECT uri FROM {ek_item_images WHERE itemcode=:i ORDER by id limit 1";
                $uri = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':i' => $r->itemcode))->fetchField();
                $img = '';
                if($uri) {
                    $img = "<a href='" . file_create_url($uri) . "' target='_blank'>"
                                . "<img class='thumbnail' src=" . file_create_url($uri) . "></a>";
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
                $links['del'] = array(
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
                '#markup' => "<a href='" . $excel . "' target='_blank'>" . t('Export') . "</a>",
            );
            $build['items_table'] = array(
                '#type' => 'table',
                '#header' => $header,
                '#rows' => $options,
                '#attributes' => array('id' => 'items_table'),
                '#empty' => $this->t('No item found'),
                '#attached' => array(
                    'library' => array('ek_products/ek_products_css'),
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
    public function excelItemsList($param = NULL) {
        $markup = array();    
        if (!class_exists('PHPExcel')) {
            $markup = t('Excel library not available, please contact administrator.');
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
        $form_builder = $this->formBuilder();
        $build['delete'] = $form_builder->getForm('Drupal\ek_products\Form\DeleteItem', $id);
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
        $items['search'] = t('<a href="@url">New search</a>', array('@url' => $url_search));
        $url_list = Url::fromRoute('ek_products.list', array(), array())->toString();
        $items['list'] = t('<a href="@url">List</a>', array('@url' => $url_list));

        $url_pdf = Url::fromRoute('ek_item.pdf', array('id' => $id), array())->toString();
        $items['pdf'] =t('<a href="@url" target="_blank">Print</a>', array('@url' => $url_pdf));
        
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
        $s = array(0 => t('active'), 1 => t('active'));
        $items['active'] = $r['active'];
        $items['collection'] = $r['collection'];
        $items['department'] = $r['department'];
        $items['family'] = $r['family'];
        $items['size'] = $r['size'];
        $items['color'] = $r['color'];

        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $query = "SELECT name from {ek_address_book} where id=:id";
            $items['supplier'] = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r['supplier']))->fetchField();
        } else {
            $items['supplier'] = '';
        }

        $items['stamp'] = date('Y-m-d', $r['stamp']);


        $query = "SELECT * from {ek_item_packing} where itemcode=:id";
        $r = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $r['itemcode']))
                ->fetchAssoc();

        $items['units'] = $r['units'];
        $items['unit_measure'] = $r['unit_measure'];
        $items['item_size'] = $r['item_size'];
        $items['pack_size'] = $r['pack_size'];
        $items['qty_pack'] = $r['qty_pack'];
        $items['c20'] = $r['c20'];
        $items['c40'] = $r['c40'];
        $items['min_order'] = $r['min_order'];


        $query = "SELECT * from {ek_item_prices} where itemcode=:id";
        $r = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $r['itemcode']))
                ->fetchAssoc();

        $items['purchase_price'] = $r['purchase_price'];
        $items['currency'] = $r['currency'];
        $items['date_purchase'] = date('Y-m-d', $r['date_purchase']);
        $items['selling_price'] = $r['selling_price'];
        $items['selling_price_label'] = $this->settings->get('selling_price_label');
        $items['promo_price'] = $r['promo_price'];
        $items['promo_price_label'] = $this->settings->get('promo_price_label');
        $items['discount_price'] = $r['discount_price'];
        $items['discount_price_label'] = $this->settings->get('discount_price_label');
        $items['exp_selling_price'] = $r['exp_selling_price'];
        $items['exp_selling_price_label'] = $this->settings->get('exp_selling_price_label');
        $items['exp_promo_price'] = $r['exp_promo_price'];
        $items['exp_promo_price_label'] = $this->settings->get('exp_promo_price_label');
        $items['exp_discount_price'] = $r['exp_discount_price'];
        $items['exp_discount_price_label'] = $this->settings->get('exp_discount_price_label');
        $items['loc_currency'] = $r['loc_currency'];
        $items['exp_currency'] = $r['exp_currency'];
        $query = "SELECT count(id) from {ek_item_barcodes} where itemcode=:id";
        $c = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $r['itemcode']))
                ->fetchField();

        $items['barcodes'] = array();
        if ($c > 0) {
            $query = "SELECT * from {ek_item_barcodes} where itemcode=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $r['itemcode']));
            if (class_exists('TCPDF2DBarcode')) {
                $add_barcode = TRUE;
                include_once drupal_get_path('module', 'ek_products') . '/code.inc';
            }
            $barcodes = array();
            while ($r = $data->fetchAssoc()) {
                
                $barcodes['id'] = $r['id'];
                $barcodes['barcode'] = $r['barcode'];
                $barcodes['encode'] = $r['encode'];
                
                if ($add_barcode) {
                  
                   $barcodes['image'] = barcode($r['barcode'], $r['encode'], [1,20], 'black', 'html');
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

                
                if ($r['uri'] <> '') {
                    $picture['element'] = "<a href='" . file_create_url($r['uri']) . "' target='_blank'><img class='thumbnail' src="
                            . file_create_url($r['uri']) . "></a>";
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
        );
    }

    /**
     * @return 
     * the item edition page.
     *
     */
    public function editproducts(Request $request, $id = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, NULL);
        $title = t('Edit item  <small>@id</small>', array('@id' => $id));
        
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
    public function cloneproducts(Request $request, $id = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, 'clone');
        $title = t('Clone item <small>@id</small>', array('@id' => $id));
        
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
    public function newproducts(Request $request, $id = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_products\Form\EditProductsForm', $id, NULL);


        return array(
            '#theme' => 'ek_products_form',
            '#items' => $response,
            '#title' => t('Create item'),
            '#attached' => array(
                'library' => array('ek_products/ek_products_css'),
            ),
        );
    }

    /**
     * Return tags and classification of items.
     *
     */
    public function tags(Request $request, $opt = NULL) {

        switch ($opt) {
            case 'type' :
            default :
                $query = "SELECT DISTINCT type FROM {ek_items} WHERE type like :text";
                break;
            case 'department' :
                $query = "SELECT DISTINCT department FROM {ek_items} WHERE department like :text";
                break;
            case 'family' :
                $query = "SELECT DISTINCT family FROM {ek_items} WHERE family like :text";
                break;
            case 'collection' :
                $query = "SELECT DISTINCT collection FROM {ek_items} WHERE collection like :text";
                break;
            case 'color' :
                $query = "SELECT DISTINCT color FROM {ek_items} WHERE color like :text";
                break;
            case 'measure' :
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
            $markup = ['#markup' => t('Pdf library not available, please contact administrator.')];
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
            $s = array(0 => t('active'), 1 => t('active'));
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
                    $add_barcode = TRUE;
                    include_once drupal_get_path('module', 'ek_products') . '/code.inc';
                }
                $barcodes = array();
                while ($r = $data->fetchAssoc()) {

                    $barcodes['id'] = $r['id'];
                    $barcodes['barcode'] = $r['barcode'];
                    $barcodes['encode'] = $r['encode'];

                    if ($add_barcode) {
                       $barcodes['image'] = barcode($r['barcode'], $r['encode'], [1,20], 'black', 'html');
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
        }
    }

}
