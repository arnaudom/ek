<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\EditProductsForm.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\SafeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_products\ItemSettings;

/**
 * Provides an item edit form.
 */
class EditProductsForm extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
        $this->settings = new ItemSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_products_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {


        if (isset($id) && !$id == NULL) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $id,
            );

            $query = "SELECT * from ek_items WHERE id=:id";
            $r = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchAssoc();

            $query = "SELECT * from ek_item_barcodes WHERE itemcode=:id";
            $rb_data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r['itemcode']));

            $query = "SELECT * from ek_item_packing WHERE itemcode=:id";
            $rp = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r['itemcode']))->fetchAssoc();

            $query = "SELECT * from ek_item_prices WHERE itemcode=:id";
            $rs = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r['itemcode']))->fetchAssoc();

            $query = "SELECT * from ek_item_images WHERE itemcode=:id";
            $ri_data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $r['itemcode']));

            $form['itemcode'] = array(
                '#type' => 'hidden',
                '#default_value' => $r['itemcode'],
            );
            
                if(!NULL == $clone) {
                    $form['clone'] = array(
                       '#type' => 'hidden',
                       '#default_value' => 1,
                    );    
                    $form['info'] = array(
                       '#type' => 'item',
                       '#markup' => "<div class='messages messages--warning'>" . t('Item will be duplicated under different itemcode') . '</div>',
                    );                      
                    
                }
        } else {

            $form['new_item'] = array(
                '#type' => 'hidden',
                '#default_value' => 1,
            );

            $rb_data = NULL;
            $ri_data = NULL;
        }

        $form['active'] = array(
            '#type' => 'select',
            '#options' => array(0 => t('stop'), 1 => t('active')),
            '#default_value' => isset($r['active']) ? $r['active'] : '1',
            '#required' => TRUE,
        );

        if ($this->moduleHandler->moduleExists('ek_admin')) {
            $coid = Database::getConnection('external_db', 'external_db')->query("SELECT id,name from {ek_company} order by name")->fetchAllKeyed();
            $form['coid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $coid,
                '#required' => TRUE,
                '#default_value' => isset($r['coid']) ? $r['coid'] : NULL,
            );
        } else {

            $form['coid'] = array(
                '#type' => 'hidden',
                '#required' => TRUE,
                '#default_value' => 1,
            );
        }

        $form['type'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 150,
            '#default_value' => isset($r['type']) ? $r['type'] : null,
            '#description' => isset($r['type']) ? t('Item type') : t('Item type. The first 3 letters of the type will be used to generate an item code for new item.'),
            '#required' => TRUE,
            '#disabled' => isset($r['type']) ? TRUE : FALSE,
            '#autocomplete_route_name' => 'ek_look_up_item_type',
        );



        $form['description1'] = array(
            '#type' => 'textarea',
            '#default_value' => isset($r['description1']) ? $r['description1'] : null,
            '#rows' => 3,
            '#attributes' => array('placeholder' => t('Main description')),
        );

        //second description can be used to add formatted text
        //that can be used or html display or pdf forms for instance
        $form['description2'] = array(
            '#type' => 'text_format',
            '#default_value' => isset($r['description2']) ? $r['description2'] : null,
            '#rows' => 2,
            '#attributes' => array('placeholder' => t('Extended description')),
            '#format' => isset($r->format) ? $r->format : 'restricted_html',
        );

        $form['supplier_code'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 255,
            '#default_value' => isset($r['supplier_code']) ? $r['supplier_code'] : null,
            '#attributes' => array('placeholder' => t('supplier item code if any')),
            '#title' => t('supplier item code'),
        );

        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $supplier = array('' => t('Not applicable'));
            $supplier += \Drupal\ek_address_book\AddressBookData::addresslist(2);
            if (!empty($supplier)) {
                $form['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $supplier,
                    '#default_value' => isset($r['supplier']) ? $r['supplier'] : NULL,
                    '#title' => t('supplier name'),
                );
            } else {
                $new = l(t('supplier'), 'new_contact');
                $form['supplier'] = array(
                    '#markup' => t("You do not have any $new in your record."),
                    '#default_value' => 0,
                );
            }
        } else {

            $form['supplier'] = array(
                '#markup' => t('You do not have any supplier list.'),
                '#default_value' => 0,
            );
        }

        $form['department'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 150,
            '#default_value' => isset($r['department']) ? $r['department'] : null,
            '#description' => t('item department'),
            '#autocomplete_route_name' => 'ek_look_up_item_department',
        );

        $form['family'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 150,
            '#default_value' => isset($r['family']) ? $r['family'] : null,
            '#description' => t('item family'),
            '#autocomplete_route_name' => 'ek_look_up_item_family',
        );


        $form['collection'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 150,
            '#default_value' => isset($r['collection']) ? $r['collection'] : null,
            '#description' => t('item collection'),
            '#autocomplete_route_name' => 'ek_look_up_item_collection',
        );


        $form['color'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 150,
            '#default_value' => isset($r['color']) ? $r['color'] : null,
            '#description' => t('item color'),
            '#autocomplete_route_name' => 'ek_look_up_item_color',
        );


        $form['size'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['size']) ? $r['size'] : null,
            '#attributes' => array('placeholder' => t('size')),
        );


//
//logistic data
//

        $form['logistic'] = array(
            '#type' => 'details',
            '#title' => t('Logistics'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,
        );

        $form['logistic']['units'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rp['units']) ? $rp['units'] : null,
            '#attributes' => array('placeholder' => t('stock in units')),
            '#description' => t('stock in units'),
            '#prefix' => '<div class="container-inline">',
        );


        $form['logistic']['unit_measure'] = array(
            '#type' => 'textfield',
            '#size' => 15,
            '#default_value' => isset($rp['unit_measure']) ? $rp['unit_measure'] : null,
            '#description' => t('unit measure'),
            '#autocomplete_route_name' => 'ek_look_up_item_measure',
            '#suffix' => '</div>',
        );


        $form['logistic']['item_size'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 255,
            '#default_value' => isset($rp['item_size']) ? $rp['item_size'] : null,
            '#attributes' => array('placeholder' => t('item size')),
            '#description' => t('item size'),
        );

        $form['logistic']['pack_size'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 255,
            '#default_value' => isset($rp['pack_size']) ? $rp['pack_size'] : null,
            '#attributes' => array('placeholder' => t('pack size')),
            '#description' => t('pack size'),
        );

        $form['logistic']['qty_pack'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rp['qty_pack']) ? $rp['qty_pack'] : null,
            '#attributes' => array('placeholder' => t('quantity per pack')),
            '#description' => t('quantity per pack'),
        );

        $form['logistic']['c20'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rp['c20']) ? $rp['c20'] : null,
            '#attributes' => array('placeholder' => t('20ft quantity')),
            '#description' => t('quantity per 20ft container'),
        );

        $form['logistic']['c40'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rp['c40']) ? $rp['c40'] : null,
            '#attributes' => array('placeholder' => t('40ft quantity')),
            '#description' => t('quantity per 40ft container'),
        );

        $form['logistic']['min_order'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rp['min_order']) ? $rp['min_order'] : null,
            '#attributes' => array('placeholder' => t('minimum order')),
            '#description' => t('minimum order quantity'),
        );

        /*
          $form['logistic']['logistic_cost'] = array(
          '#type' => 'textfield',
          '#size' => 50,
          '#maxlength' => 255,
          '#default_value' => isset($rp['logistic_cost']) ? $rp['logistic_cost'] :null,
          '#attributes' => array('placeholder'=>t('cost')),
          '#description' => t('logistic cost'),
          );
         */

//
//prices data
//

        $form['price'] = array(
            '#type' => 'details',
            '#title' => t('Prices'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,
        );

        $form['price']['purchase_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['purchase_price']) ? $rs['purchase_price'] : 0,
            '#attributes' => array(),
            '#title' => t('purchase price'),
            '#prefix' => '<div class="container-inline">',
        );

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $query = "SELECT id,currency from {ek_currency} where active=:a order by currency";
            $currency = array('--' => '--');
            $currency += Database::getConnection('external_db', 'external_db')->query($query, array(':a' => 1))->fetchAllKeyed();
            $form['price']['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($currency, $currency),
                '#default_value' => isset($rs['currency']) ? $rs['currency'] : NULL,
                '#title' => t('purchase currency'),
                '#suffix' => '</div>',
            );
        } else {
            $currency = array('ALL' => 'Albania Lek', 'AFN' => 'Afghanistan Afghani', 'ARS' => 'Argentina Peso', 'AWG' => 'Aruba Guilder', 'AUD' => 'Australia Dollar', 'AZN' => 'Azerbaijan New Manat', 'BSD' => 'Bahamas Dollar', 'BBD' => 'Barbados Dollar', 'BDT' => 'Bangladeshi taka', 'BYR' => 'Belarus Ruble', 'BZD' => 'Belize Dollar', 'BMD' => 'Bermuda Dollar', 'BOB' => 'Bolivia Boliviano', 'BAM' => 'Bosnia and Herzegovina Convertible Marka', 'BWP' => 'Botswana Pula', 'BGN' => 'Bulgaria Lev', 'BRL' => 'Brazil Real', 'BND' => 'Brunei Darussalam Dollar', 'KHR' => 'Cambodia Riel', 'CAD' => 'Canada Dollar', 'KYD' => 'Cayman Islands Dollar', 'CLP' => 'Chile Peso', 'CNY' => 'China Yuan Renminbi', 'COP' => 'Colombia Peso', 'CRC' => 'Costa Rica Colon', 'HRK' => 'Croatia Kuna', 'CUP' => 'Cuba Peso', 'CZK' => 'Czech Republic Koruna', 'DKK' => 'Denmark Krone', 'DOP' => 'Dominican Republic Peso', 'XCD' => 'East Caribbean Dollar', 'EGP' => 'Egypt Pound', 'SVC' => 'El Salvador Colon', 'EEK' => 'Estonia Kroon', 'EUR' => 'Euro', 'FKP' => 'Falkland Islands (Malvinas) Pound', 'FJD' => 'Fiji Dollar', 'GHC' => 'Ghana Cedis', 'GIP' => 'Gibraltar Pound', 'GTQ' => 'Guatemala Quetzal', 'GGP' => 'Guernsey Pound', 'GYD' => 'Guyana Dollar', 'HNL' => 'Honduras Lempira', 'HKD' => 'Hong Kong Dollar', 'HUF' => 'Hungary Forint', 'ISK' => 'Iceland Krona', 'INR' => 'India Rupee', 'IDR' => 'Indonesia Rupiah', 'IRR' => 'Iran Rial', 'IMP' => 'Isle of Man Pound', 'ILS' => 'Israel Shekel', 'JMD' => 'Jamaica Dollar', 'JPY' => 'Japan Yen', 'JEP' => 'Jersey Pound', 'KZT' => 'Kazakhstan Tenge', 'KPW' => 'Korea (North) Won', 'KRW' => 'Korea (South) Won', 'KGS' => 'Kyrgyzstan Som', 'LAK' => 'Laos Kip', 'LVL' => 'Latvia Lat', 'LBP' => 'Lebanon Pound', 'LRD' => 'Liberia Dollar', 'LTL' => 'Lithuania Litas', 'MKD' => 'Macedonia Denar', 'MYR' => 'Malaysia Ringgit', 'MUR' => 'Mauritius Rupee', 'MXN' => 'Mexico Peso', 'MNT' => 'Mongolia Tughrik', 'MZN' => 'Mozambique Metical', 'NAD' => 'Namibia Dollar', 'NPR' => 'Nepal Rupee', 'ANG' => 'Netherlands Antilles Guilder', 'NZD' => 'New Zealand Dollar', 'NIO' => 'Nicaragua Cordoba', 'NGN' => 'Nigeria Naira', 'NOK' => 'Norway Krone', 'OMR' => 'Oman Rial', 'PKR' => 'Pakistan Rupee', 'PAB' => 'Panama Balboa', 'PYG' => 'Paraguay Guarani', 'PEN' => 'Peru Nuevo Sol', 'PHP' => 'Philippines Peso', 'PLN' => 'Poland Zloty', 'QAR' => 'Qatar Riyal', 'RON' => 'Romania New Leu', 'RUB' => 'Russia Ruble', 'SHP' => 'Saint Helena Pound', 'SAR' => 'Saudi Arabia Riyal', 'RSD' => 'Serbia Dinar', 'SCR' => 'Seychelles Rupee', 'SGD' => 'Singapore Dollar', 'SBD' => 'Solomon Islands Dollar', 'SOS' => 'Somalia Shilling', 'ZAR' => 'South Africa Rand', 'LKR' => 'Sri Lanka Rupee', 'SEK' => 'Sweden Krona', 'CHF' => 'Switzerland Franc', 'SRD' => 'Suriname Dollar', 'SYP' => 'Syria Pound', 'TWD' => 'Taiwan New Dollar', 'THB' => 'Thailand Baht', 'TTD' => 'Trinidad and Tobago Dollar', 'TRY' => 'Turkey Lira', 'TRL' => 'Turkey Lira', 'TVD' => 'Tuvalu Dollar', 'UAH' => 'Ukraine Hryvna', 'GBP' => 'United Kingdom Pound', 'USD' => 'United States Dollar', 'UYU' => 'Uruguay Peso', 'UZS' => 'Uzbekistan Som', 'VEF' => 'Venezuela Bolivar', 'VND' => 'Viet Nam Dong', 'YER' => 'Yemen Rial', 'ZWD' => 'Zimbabwe Dollar');

            $form['price']['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $currency,
                '#default_value' => isset($rs['currency']) ? $rs['currency'] : NULL,
                '#title' => t('purchase currency'),
                '#suffix' => '</div>',
            );
        }

        $form['price']['date_purchase'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($rs['date_purchase']) ? date('Y-m-d', $rs['date_purchase']) : date('Y-m-d'),
            '#description' => t('date purchase'),
            
        );



        $form['price']['loc_currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($currency, $currency),
            '#default_value' => isset($rs['loc_currency']) ? $rs['loc_currency'] : NULL,
            '#title' => t('local prices currency'),
        );

        $form['price']['selling_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['selling_price']) ? $rs['selling_price'] : 0,
            '#attributes' => array('class' => array('amount')),
            '#title' => $this->settings->get('selling_price_label'),
            '#prefix' => '<div class="">',
        );


        $form['price']['promo_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['promo_price']) ? $rs['promo_price'] : 0,
            '#attributes' => array('class' => array('amount')),
            '#title' => $this->settings->get('promo_price_label'),
        );

        $form['price']['discount_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['discount_price']) ? $rs['discount_price'] : 0,
            '#attributes' => array('class' => array('amount')),
            '#title' => $this->settings->get('discount_price_label'),
            '#suffix' => '</div>',
        );



        $form['price']['exp_currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($currency, $currency),
            '#default_value' => isset($rs['exp_currency']) ? $rs['exp_currency'] : NULL,
            '#title' => t('export currency'),
        );

        $form['price']['exp_selling_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['exp_selling_price']) ? $rs['exp_selling_price'] : 0,
            '#attributes' => array('class' => array('amount')),
            '#title' => $this->settings->get('exp_selling_price_label'),
            '#prefix' => '<div class="">',
        );


        $form['price']['exp_promo_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['promo_price']) ? $rs['exp_promo_price'] : 0,
            '#attributes' => array('placeholder' => t('promotion price'), 'class' => array('amount')),
            '#title' => $this->settings->get('exp_promo_price_label'),
        );

        $form['price']['exp_discount_price'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($rs['exp_discount_price']) ? $rs['exp_discount_price'] : 0,
            '#attributes' => array('class' => array('amount')),
            '#title' => $this->settings->get('exp_discount_price_label'),
            '#suffix' => '</div>',
        );






//
//barcode data
//
        $encode = array('EAN-13', 'UPC-A', 'EAN-8','EAN5','EAN2', 'UPC-E', 
            'S205', 'I2O5', 'I25','I25 with checksum', 'S25', 'POSTNET', 'CODABAR', 
            'CODE11', 'CODE128','CODE128 A','CODE128 B','CODE128 C', 'CODE39','PLANET', 
            'CODE39 EXTENDED', 'CODE39 with checksum','CODE39 EXTENDED + CHECKSUM', 
            'CODE93', 'MSI', 'MSI with checksum', 'PHARMACODE', 'PHARMACODE TWO-TRACKS',
            'IMB - Onecode - USPS-B-3200', 'KIX', 'RMS4CC', 'CBC');

        $form['bc'] = array(
            '#type' => 'details',
            '#title' => t('Barcodes'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,
        );


        $form['bc']["barcode"] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#attributes' => array('placeholder' => t('new barcode')),
            '#description' => t('add new barcode'),
        );


        $form['bc']['encode'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($encode, $encode),
            '#title' => t('encoding'),
        );


        $i = 0;

        if ($rb_data) {
            while ($rb = $rb_data->fetchAssoc()) {

                //loop barcodes
                $form['bc']['line' . $i] = array(
                    '#markup' => '<hr>',
                );
                $form['bc']['bcid' . $i] = array(
                    '#type' => 'hidden',
                    '#default_value' => $rb['id'],
                );
                $form['bc']['bcvalue' . $i] = array(
                    '#type' => 'hidden',
                    '#default_value' => $rb['barcode'],
                );//used to validate only new entry
                
                $form['bc']['barcode_delete' . $i] = array(
                    '#type' => 'checkbox',
                    '#title' => t('delete barcode'),
                    '#attributes' => array('onclick' => "jQuery('#edit-barcode$i ').toggleClass( 'delete');"),
                );

                $form['bc']["barcode" . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => isset($rb['barcode']) ? $rb['barcode'] : null,
                    '#attributes' => array('placeholder' => t('barcode')),
                    '#description' => t('barcode'),
                );


                $form['bc']['encode' . $i] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array_combine($encode, $encode),
                    '#default_value' => isset($rb['encode']) ? $rb['encode'] : NULL,
                    '#title' => t('encoding'),
                );

                $i++;
            }

            $form['barcodes'] = array(
                '#type' => 'hidden',
                '#default_value' => $i,
            );
        }



//
//image data / can delete only , upload ajax via item card
//non new item only
//


        if (isset($id) && !$id == NULL) {
            $i = 0;
            $form['images'] = array(
                '#type' => 'details',
                '#title' => t('Images'),
                '#collapsible' => TRUE,
                '#collapsed' => FALSE,
            );
            if ($ri_data) {
                while ($ri = $ri_data->fetchAssoc()) {

                    //loop images

                    $form['images']['imageid' . $i] = array(
                        '#type' => 'hidden',
                        '#default_value' => $ri['id'],
                    );

                    $image = "<a href='" . file_create_url($ri['uri']) . "' target='_blank'><img class='thumbnail' src=" . file_create_url($ri['uri']) . "></a>";

                    $form['images']["image" . $i] = array(
                        '#markup' => "<div style='padding:2px;'>" . $image . "</div>",
                    );
                    $form['images']['image_delete' . $i] = array(
                        '#type' => 'checkbox',
                        '#title' => t('delete image'),
                        '#attributes' => array('onclick' => "jQuery('#edit-image$i ').toggleClass( 'delete');"),
                    );

                    $i++;
                }
                $form['images_count'] = array(
                    '#type' => 'hidden',
                    '#default_value' => $i,
                );
            }
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array(
                '#type' => 'submit', 
                '#value' => $clone ? $this->t('Clone') : $this->t('Record'),
            );



        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        parent::validateForm($form, $form_state);

        //check prices are numeric
        if (!is_numeric($form_state->getValue('selling_price'))) {
            $form_state->setErrorByName('selling_price', $this->t('Price value is wrong'));
        }
        if (!is_numeric($form_state->getValue('promo_price'))) {
            $form_state->setErrorByName('promo_price', $this->t('Price value is wrong'));
        }
        if (!is_numeric($form_state->getValue('discount_price'))) {
            $form_state->setErrorByName('discount_price', $this->t('Price value is wrong'));
        }
        if (!is_numeric($form_state->getValue('exp_selling_price'))) {
            $form_state->setErrorByName('exp_selling_price', $this->t('Price value is wrong'));
        }
        if (!is_numeric($form_state->getValue('exp_promo_price'))) {
            $form_state->setErrorByName('exp_promo_price', $this->t('Price value is wrong'));
        }
        if (!is_numeric($form_state->getValue('exp_discount_price'))) {
            $form_state->setErrorByName('exp_discount_price', $this->t('Price value is wrong'));
        }

        //check duplicate barcodes
        if ($form_state->getValue('barcode') != '') {
            $query = "SELECT id from {ek_item_barcodes} where barcode = :b";
            $check = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':b' => $form_state->getValue('barcode' . $i)))
                    ->fetchField();
            if (is_numeric($check)) {
                $form_state->setErrorByName('barcode', $this->t('Barcode already exist'). ' ' . $form_state->getValue('barcode' . $i));
            }
        }

        for ($i = 0; $i <= $form_state->getValue('barcodes'); $i++) {
            if ($form_state->getValue('barcode_delete' . $i) != 1 
                    && $form_state->getValue('barcode' . $i) != $form_state->getValue('bcvalue' . $i)) {
                $query = "SELECT id from {ek_item_barcodes} where barcode = :b and id<>:id";
                $check = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':b' => $form_state->getValue('barcode' . $i), ':id' => $form_state->getValue('bcid' . $i)))
                        ->fetchField();

                if (is_numeric($check)) {
                    $form_state->setErrorByName('barcode' . $i, $this->t('Barcode already exist') . ' ' . $form_state->getValue('barcode' . $i));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ( ($form_state->getValue('new_item') && $form_state->getValue('new_item') == 1)
                || $form_state->getValue('clone') == 1
                ) {
            //create new itemcode
            $id = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_items}")
                    ->fetchField();
            $id++;
            $t_code = str_replace(" ", "", $form_state->getValue('type'));//remove blank space if any
            $itemcode = strtoupper(substr($t_code, 0, 3)) . $id;//take 3 first letters
        }

//main
        
        $dsc2 = $form_state->getValue('description2');
        $fields1 = array(
            'coid' => $form_state->getValue('coid'),
            'type' => $form_state->getValue('type'),
            'description1' => Xss::filter($form_state->getValue('description1')),
            'description2' => $dsc2['value'],
            'supplier_code' => $form_state->getValue('supplier_code'),
            'active' => $form_state->getValue('active'),
            'collection' => Xss::filter($form_state->getValue('collection')),
            'department' => Xss::filter($form_state->getValue('department')),
            'family' => Xss::filter($form_state->getValue('family')),
            'size' => Xss::filter($form_state->getValue('size')),
            'color' => Xss::filter($form_state->getValue('color')),
            'supplier' => $form_state->getValue('supplier'),
            'stamp' => strtotime('now'),
            'format' => $dsc2['format'],
        );

// item logistic
        $fields2 = array(
            'units' => Xss::filter($form_state->getValue('units')),
            'unit_measure' => Xss::filter($form_state->getValue('unit_measure')),
            'item_size' => Xss::filter($form_state->getValue('item_size')),
            'pack_size' => Xss::filter($form_state->getValue('pack_size')),
            'qty_pack' => Xss::filter($form_state->getValue('qty_pack')),
            'c20' => Xss::filter($form_state->getValue('c20')),
            'c40' => Xss::filter($form_state->getValue('c40')),
            'min_order' => Xss::filter($form_state->getValue('min_order')),
        );
// item prices
        $fields3 = array(
            'purchase_price' => Xss::filter($form_state->getValue('purchase_price')),
            'currency' => $form_state->getValue('currency'),
            'date_purchase' => strtotime($form_state->getValue('date_purchase')),
            'selling_price' => Xss::filter($form_state->getValue('selling_price')),
            'promo_price' => Xss::filter($form_state->getValue('promo_price')),
            'discount_price' => Xss::filter($form_state->getValue('discount_price')),
            'exp_selling_price' => Xss::filter($form_state->getValue('exp_selling_price')),
            'exp_promo_price' => Xss::filter($form_state->getValue('exp_promo_price')),
            'exp_discount_price' => Xss::filter($form_state->getValue('exp_discount_price')),
            'loc_currency' => $form_state->getValue('loc_currency'),
            'exp_currency' => $form_state->getValue('exp_currency'),
        );


        if (!$form_state->getValue('for_id') || $form_state->getValue('clone') == 1) {

            $fields1['itemcode'] = $itemcode;
            $fields2['itemcode'] = $itemcode;
            $fields3['itemcode'] = $itemcode;

            $id = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_items')
                    ->fields($fields1)
                    ->execute();
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_packing')
                    ->fields($fields2)
                    ->execute();
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_prices')
                    ->fields($fields3)
                    ->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('purchase_price'),
                        'currency' => $form_state->getValue('currency'),
                        'type' => 'pp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('selling_price'),
                        'currency' => $form_state->getValue('loc_currency'),
                        'type' => 'sp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('promo_price'),
                        'currency' => $form_state->getValue('loc_currency'),
                        'type' => 'prp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('discount_price'),
                        'currency' => $form_state->getValue('loc_currency'),
                        'type' => 'dp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('exp_selling_price'),
                        'currency' => $form_state->getValue('exp_currency'),
                        'type' => 'esp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('exp_promo_price'),
                        'currency' => $form_state->getValue('exp_currency'),
                        'type' => 'eprp')
            )->execute();

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_price_history')
                    ->fields(
                    array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                        'price' => $form_state->getValue('exp_discount_price'),
                        'currency' => $form_state->getValue('exp_currency'),
                        'type' => 'edp')
            )->execute();
            
        } else {
            //update
            $itemcode = $form_state->getValue('itemcode');
            //update existing
            $update = Database::getConnection('external_db', 'external_db')->update('ek_items')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->fields($fields1)
                    ->execute();

            $update = Database::getConnection('external_db', 'external_db')->update('ek_item_packing')
                    ->condition('itemcode', $form_state->getValue('itemcode'))
                    ->fields($fields2)
                    ->execute();

            $id = $form_state->getValue('for_id');

            // price history
            $current = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT * from {ek_item_prices} where itemcode=:id', array(':id' => $itemcode))
                    ->fetchAssoc();


            if ($current['purchase_price'] <> $form_state->getValue('purchase_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('purchase_price'),
                            'currency' => $form_state->getValue('currency'),
                            'type' => 'pp')
                )->execute();
            }

            if ($current['selling_price'] <> $form_state->getValue('selling_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('selling_price'),
                            'currency' => $form_state->getValue('loc_currency'),
                            'type' => 'sp')
                )->execute();
            }

            if ($current['promo_price'] <> $form_state->getValue('promo_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('promo_price'),
                            'currency' => $form_state->getValue('loc_currency'),
                            'type' => 'prp')
                )->execute();
            }

            if ($current['discount_price'] <> $form_state->getValue('discount_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('discount_price'),
                            'currency' => $form_state->getValue('loc_currency'),
                            'type' => 'dp')
                )->execute();
            }

            if ($current['exp_discount_price'] <> $form_state->getValue('exp_discount_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('exp_discount_price'),
                            'currency' => $form_state->getValue('exp_currency'),
                            'type' => 'edp')
                )->execute();
            }

            if ($current['exp_promo_price'] <> $form_state->getValue('exp_promo_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('exp_promo_price'),
                            'currency' => $form_state->getValue('exp_currency'),
                            'type' => 'eprp')
                )->execute();
            }

            if ($current['exp_selling_price'] <> $form_state->getValue('exp_selling_price')) {

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_price_history')
                        ->fields(
                        array('itemcode' => $itemcode, 'date' => date('Y-m-d'),
                            'price' => $form_state->getValue('exp_selling_price'),
                            'currency' => $form_state->getValue('exp_currency'),
                            'type' => 'esp')
                )->execute();
            }

            $update = Database::getConnection('external_db', 'external_db')->update('ek_item_prices')
                    ->condition('itemcode', $form_state->getValue('itemcode'))
                    ->fields($fields3)
                    ->execute();
        }


        // barcodes
        //new
        if (($form_state->getValue('barcode')) && !$form_state->getValue('barcode') == '') {
            $fields4 = array(
                'itemcode' => $itemcode,
                'barcode' => $form_state->getValue('barcode'),
                'encode' => $form_state->getValue('encode'),
            );
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_item_barcodes')
                    ->fields($fields4)
                    ->execute();
        }

        //old
        if (($form_state->getValue('barcodes')) && $form_state->getValue('barcodes') >= 0) {

            for ($i = 0; $i <= $form_state->getValue('barcodes'); $i++) {

                if ($form_state->getValue('barcode_delete' . $i) == 1) {

                    Database::getConnection('external_db', 'external_db')->delete('ek_item_barcodes')
                            ->condition('id', $form_state->getValue('bcid' . $i))
                            ->execute();
                } else {
                    $fields5 = array(
                        'barcode' => $form_state->getValue('barcode' . $i),
                        'encode' => $form_state->getValue('encode' . $i),
                    );
                    $update = Database::getConnection('external_db', 'external_db')->update('ek_item_barcodes')
                            ->condition('id', $form_state->getValue('bcid' . $i))
                            ->fields($fields5)
                            ->execute();
                }
            }
        }

        // images
        if (($form_state->getValue('images_count')) && $form_state->getValue('images_count') >= 0) {

            for ($i = 0; $i <= $form_state->getValue('images_count'); $i++) {

                if ($form_state->getValue('image_delete' . $i) == 1) {

                    $uri = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT uri from {ek_item_images} where id=:id", array(':id' => $form_state->getValue('imageid' . $i)))
                            ->fetchField();
                    file_unmanaged_delete($uri);

                    Database::getConnection('external_db', 'external_db')->delete('ek_item_images')
                            ->condition('id', $form_state->getValue('imageid' . $i))
                            ->execute();
                }
            }
        }


        if (isset($insert) || isset($update)){
            \Drupal::messenger()->addStatus(t('The item is recorded'));
            $form_state->setRedirect('ek_products.view', array('id' => $id));
        }
    }

}
