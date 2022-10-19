<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditEmployee.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to create or edit employee
 */
class EditEmployee extends FormBase
{
    
    /**
     * The file storage service.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $fileStorage;
    
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
    public function __construct(ModuleHandler $module_handler, EntityStorageInterface $file_storage)
    {
        $this->moduleHandler = $module_handler;
        $this->fileStorage = $file_storage;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('module_handler'),
                $container->get('entity_type.manager')->getStorage('file')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'employee_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        if (isset($id) && !$id == null) {
            $form_state->set('step', 2);
            
            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w');
            $query->fields('w');
            $query->condition('id', $id, '=');
            $r = $query->execute()->fetchObject();
            $form_state->set('coid', $r->company_id);
            if(isset($r->id)) {
                $ceid = $r->id;
                if($r->custom_id != ''){
                    $ceid = $r->custom_id;
                }
            }
        } else {
            $form['new'] = array(
                '#type' => 'hidden',
                '#default_value' => 1,
            );
            $ceid = '';
            
        }
        
        $company = AccessCheck::CompanyListByUid();
        
        if ($form_state->get('step') == '1') {
            $form['coid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
                '#title' => $this->t('company'),
                '#disabled' => $form_state->getValue('coid') ? true : false,
                '#required' => true,
            );

            if ($form_state->getValue('coid') == '') {
                $form['next'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Next >>'),
                    '#states' => array(
                        // Hide data fieldset when class is empty.
                        'invisible' => array(
                            "select[name='coid']" => array('value' => ''),
                        ),
                    ),
                );
            }
        }

        if ($form_state->get('step') == '2') {
            $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            if ($user->hasRole('administrator')) {
                $form['coid'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $company,
                    '#default_value' => $form_state->get('coid'),
                    '#title' => $this->t('company'),
                    '#required' => true,
                );
                $form['current_coid'] = array(
                    '#type' => 'hidden',
                    '#value' => $form_state->get('coid')

                );
            } else {
                $form['coid'] = array(
                    '#type' => 'hidden',
                    '#value' => $form_state->get('coid')

                );

                $form['company'] = array(
                    '#type' => 'item',
                    '#markup' => '<h1>' . $company[$form_state->get('coid')] . '</h1>',

                );
            }
            $form['active'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('working' => $this->t('Working'), 'resigned' => $this->t('Resigned'), 'absent' => $this->t('Absent')),
                '#default_value' => isset($r->active) ? $r->active : 'working',
                '#title' => $this->t('Working status'),
                '#required' => true,
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            );

            if (isset($id)) {
                $form['archive'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array('no' => $this->t('no'), 'yes' => $this->t('yes')),
                    '#default_value' => isset($r->archive) ? $r->archive : 'no',
                    '#title' => $this->t('Archive'),
                    '#required' => true,
                );
            } else {
                $form['archive'] = array(
                    '#type' => 'hidden',
                    '#value' =>'no',
                );
            }

            
            $form['image'] = [
                  '#title' => $this->t('Image'),
                  '#type' => 'managed_file',
                  '#description' => $this->t('Employee picture (image type allowed: png, jpg, gif)'),
                  //'#suffix' => '</div>',
                  '#upload_validators' => [
                    'file_validate_extensions' => ['png jpeg jpg gif'],
                    'file_validate_image_resolution' => ['400x400'],
                    'file_validate_size' => [500000],
                  ],

                ];
            

            /* current image if any */
            if (isset($r->picture)) {
                $image = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($r->picture) . "' target='_blank'>"
                        . "<img class='thumbnail' src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($r->picture) . "></a>";
                $form['image_delete'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('delete image'),
                    '#attributes' => array('onclick' => "jQuery('#current').toggleClass('delete');"),
                    '#prefix' => "<div class='container-inline cell'>",
                );

                //use to delete if upload new when submit
                $form["uri"] = array(
                    '#type' => "hidden",
                    '#value' => $r->picture,
                );

                $form["currentimage"] = array(
                    '#markup' => "<p id='current'style='padding:2px;'>" . $image . "</p>",
                    '#suffix' => '</div></div></div>',
                );
            } else {
                $form["item"] = [
                    '#type' => 'item',
                    '#suffix' => '</div></div></div>',
                ];
            }

            if (isset($id) && !$id == null) {
                $admin = explode(',', $r->administrator);
                if (in_array(\Drupal::currentUser()->id(), $admin) || in_array('administrator', \Drupal::currentUser()->getRoles())) {
                    $disable = false;
                } else {
                    $disable = true;
                }
            } else {
                $disable = false;
                $admin = [];
            }

            $form['custom_id'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#default_value' => $ceid,
                '#title' => $this->t('Given ID'),
            );
                        
            $form['note'] = array(
                '#type' => 'textarea',
                '#rows' => 2,
                '#default_value' => isset($r->note) ? $r->note : null,
                '#title' => $this->t('Note'),
            );
            
            $form['admin'] = array(
                '#type' => 'details',
                '#title' => $this->t('administrators'),
                '#collapsible' => true,
                '#open' => false,
                '#tree' => true,
            );
            
            $users =\Drupal\ek_admin\Access\AccessCheck::listUsers(0);
            if (isset($users)) {
                foreach ($users as $k => $v) {
                    $class = in_array($k, $admin) ? 'select' : '';
                    $obj = \Drupal\user\Entity\User::load($k);
                    if ($k > 1 && $obj->hasPermission('new_employee')) {
                        //restrict choice to permission
                        $role = '';
                        if ($obj) {
                            $role = $obj->getRoles();
                            $role = implode(',', $role);
                        }

                        $form['admin'][$k] = array(
                            '#type' => 'checkbox',
                            '#disabled' => $disable,
                            '#title' => $v . ' (' . $role . ')',
                            '#default_value' => in_array($k, $admin) ? 1 : 0,
                            '#attributes' => array('onclick' => "jQuery('#u" . $k . "' ).toggleClass('select');"),
                            '#prefix' => "<div id='u" . $k . "' class='" . $class . "'>",
                            '#suffix' => '</div>',
                        );
                    }
                }
            }

            $form[1] = array(
                '#type' => 'details',
                '#title' => $this->t('name and contact'),
                '#collapsible' => true,
                '#open' => true,
            );
            $form[1]['name'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => isset($r->name) ? $r->name : null,
                '#attributes' => array('placeholder' => $this->t('full name')),
                '#title' => $this->t('Name'),
                '#required' => true,
            );

            $form[1]['address'] = array(
                '#type' => 'textfield',
                '#size' => 80,
                '#maxlength' => 255,
                '#default_value' => isset($r->address) ? $r->address : null,
                '#attributes' => array('placeholder' => $this->t('address')),
                '#title' => $this->t('Address'),
                '#required' => true,
            );

            $form[1]['email'] = array(
                '#type' => 'textfield',
                '#size' => 40,
                '#maxlength' => 255,
                '#default_value' => isset($r->email) ? $r->email : null,
                '#attributes' => array('placeholder' => $this->t('@')),
                '#title' => $this->t('Email'),
                '#required' => true,
                '#prefix' => "<div class='container-inline'>",
            );

            $form[1]['telephone'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 25,
                '#default_value' => isset($r->telephone) ? $r->telephone : null,
                '#attributes' => array('placeholder' => $this->t('phone')),
                '#title' => $this->t('Telephone'),
                '#required' => true,
                '#suffix' => '</div>',
            );

            $form[2] = array(
                '#type' => 'details',
                '#title' => $this->t('identification references'),
                '#collapsible' => true,
            );
            $form[2]['sex'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('M' => $this->t('Male'), 'F' => $this->t('female')),
                '#default_value' => isset($r->sex) ? $r->sex : null,
                '#title' => $this->t('Sex'),
                '#required' => true,
            );

            $form[2]['birth'] = array(
                '#type' => 'date',
                '#size' => 12,
                '#default_value' => isset($r->birth) ? $r->birth : null,
                '#required' => true,
                '#title' => $this->t('Birth date'),
                '#prefix' => "<div class='container-inline'>",
            );

            $form[2]['ic_no'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->ic_no) ? $r->ic_no : null,
                '#attributes' => array('placeholder' => $this->t('IC')),
                '#title' => $this->t('Identification No.'),
                '#required' => true,
                '#suffix' => '</div>',
            );

            $form[2]['epf_no'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->epf_no) ? $r->epf_no : null,
                '#attributes' => array('placeholder' => $this->t('Retirement')),
                '#title' => $this->t('Retirement fund No.'),
                '#prefix' => "<div class='container-inline'>",
            );

            $form[2]['socso_no'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->socso_no) ? $r->socso_no : null,
                '#attributes' => array('placeholder' => $this->t('Social')),
                '#title' => $this->t('Social security No.'),
            );

            $form[2]['itax_no'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->itax_no) ? $r->itax_no : null,
                '#attributes' => array('placeholder' => $this->t('Tax')),
                '#title' => $this->t('Income tax No.'),
            );

            $itaxCat = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W');
            $form[2]['itax_c'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($itaxCat, $itaxCat),
                '#default_value' => isset($r->itax_c) ? $r->itax_c : null,
                //'#title' => $this->t(''),
                '#required' => false,
                '#suffix' => '</div>',
            );




            //////////
            //   3  //
            //////////
            $form[3] = array(
                '#type' => 'details',
                '#title' => $this->t('work status'),
                '#collapsible' => true,
            );

            //$origin = array(0 => '');
            $origin = [];
            $category = new HrSettings($form_state->get('coid'));
            if (!empty($category->HrCat[$form_state->get('coid')])) {
                $origin += $category->HrCat[$form_state->get('coid')];
            }

            $form[3]['origin'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $origin,
                '#default_value' => isset($r->origin) ? $r->origin : null,
                '#title' => $this->t('Category'),
                '#required' => true,
                //'#prefix' => "<div class='container-inline'>",
            );


            $form[3]['e_status'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('not confirmed' => $this->t('not confirmed'), 'confirmed' => $this->t('confirmed')),
                '#default_value' => isset($r->e_status) ? $r->e_status : null,
                '#title' => $this->t('Status'),
                '#required' => false,
                //'#suffix' => '</div>',
            );

            $query = "SELECT location from {ek_hr_location} WHERE coid=:id order by location";
            $a = array(':id' => $form_state->get('coid'));
            $loc = array();
            $loc[0] = '';
            $loc = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();
            $form[3]['location'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($loc, $loc),
                '#default_value' => isset($r->location) ? $r->location : 0,
                '#title' => $this->t('Location'),
                '#required' => false,
                '#prefix' => "<div class='container-inline'>",
            );

            $query = "SELECT sid,service_name,ek_company.name from {ek_hr_service} INNER JOIN {ek_company} ON ek_company.id=ek_hr_service.coid WHERE ek_company.id=:id order by service_name";
            $a = array(':id' => $form_state->get('coid'));
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
            $service = array();
            $service[0] = '';
            while ($d = $data->fetchObject()) {
                $service[$d->sid] = $d->service_name . " - " . $d->name;
            }

            $form[3]['service'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $service,
                '#default_value' => isset($r->service) ? $r->service : 0,
                '#title' => $this->t('Service'),
                '#required' => false,
                '#suffix' => '</div>',
            );

            $dir = "private://hr/data/" . $form_state->get('coid') . "/ranks/ranks.txt";
            if (file_exists($dir)) {
                $ranks = file_get_contents($dir);
                $ranks = str_replace("\r\n", "", $ranks);
                //  $rank = explode(",", $ranks);
                $chapters = explode("@", $ranks);
                $opt = array();
                foreach ($chapters as $title) {
                    //title = ADMINISTRATION, A1 General manager,  A2 Executive,  A3 Clerk,
                    $selects = explode(",", $title);
                    $s =  Xss::filter(trim($selects[0]));
                    $opt[$s] = [];
                    $rows = [];
                    foreach ($selects as $key => $row) {
                        if ($key != 0 && $row != null) {
                            $rows[] = Xss::filter(trim($row));
                        }
                    }
                    $opt[$selects[0]] = $rows;
                }
 
                $form[3]['rank'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $opt,
                    '#default_value' => isset($r->rank) ? $r->rank : 0,
                    '#title' => $this->t('Rank'),
                    '#required' => false,
                    '#attributes' => array('style' => array('width:150px;')),
                    //'#suffix' => '</div>',
                );
            } else {
                $form[3]['rank'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array(),
                    '#default_value' => '',
                    '#title' => $this->t('Rank'),
                    '#required' => false,
                    //'#suffix' => '</div>',
                );
            }


            $form[3]['start'] = array(
                '#type' => 'date',
                '#size' => 12,
                '#default_value' => isset($r->start) ? $r->start : null,
                '#required' => true,
                '#title' => $this->t('Start date'),
                '#prefix' => "<div class='container-inline'>",
            );
            $form[3]['resign'] = array(
                '#type' => 'date',
                '#size' => 12,
                '#default_value' => isset($r->resign) ? $r->resign : null,
                '#title' => $this->t('Resign date'),
                '#suffix' => '</div>',
            );
            $form[3]['contract_expiration'] = array(
                '#type' => 'date',
                '#size' => 12,
                '#default_value' => isset($r->contract_expiration) ? $r->contract_expiration : null,
                '#title' => $this->t('Contract expiration'),
            );
            $form[3]['aleave'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#maxlength' => 20,
                '#default_value' => isset($r->aleave) ? $r->aleave : null,
                '#attributes' => array('placeholder' => $this->t('days')),
                '#title' => $this->t('Annual leaves'),
                '#prefix' => "<div class='container-inline'>",
            );

            $form[3]['mcleave'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#maxlength' => 20,
                '#default_value' => isset($r->mcleave) ? $r->mcleave : null,
                '#attributes' => array('placeholder' => $this->t('days')),
                '#title' => $this->t('medical leaves'),
                '#suffix' => "</div>",
            );


            //////////
            //   4  //
            //////////
            $form[4] = array(
                '#type' => 'details',
                '#title' => $this->t('salary and payment'),
                '#collapsible' => true,
            );


            $form[4]['salary'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 20,
                '#default_value' => isset($r->salary) ? $r->salary : null,
                '#required' => true,
                '#attributes' => array('placeholder' => $this->t('value'), 'class' => array('amount')),
                '#title' => $this->t('Gross salary'),
                '#prefix' => "<div class='container-inline'>",
            );

            $form[4]['th_salary'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 20,
                '#default_value' => isset($r->th_salary) ? $r->th_salary : null,
                '#attributes' => array('placeholder' => $this->t('value'), 'class' => array('amount')),
                '#title' => $this->t('Other base salary'),
            );

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $CurrencyOptions = CurrencyData::listcurrency(1);
                $form[4]['currency'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $CurrencyOptions,
                    '#required' => true,
                    '#default_value' => isset($r->currency) ? $r->currency : null,
                    '#title' => $this->t('currency'),
                    '#suffix' => "</div>",
                );
            } else {
                $currency = array('ALL' => 'Albania Lek', 'AFN' => 'Afghanistan Afghani', 'ARS' => 'Argentina Peso', 'AWG' => 'Aruba Guilder', 'AUD' => 'Australia Dollar', 'AZN' => 'Azerbaijan New Manat', 'BSD' => 'Bahamas Dollar', 'BBD' => 'Barbados Dollar', 'BDT' => 'Bangladeshi taka', 'BYR' => 'Belarus Ruble', 'BZD' => 'Belize Dollar', 'BMD' => 'Bermuda Dollar', 'BOB' => 'Bolivia Boliviano', 'BAM' => 'Bosnia and Herzegovina Convertible Marka', 'BWP' => 'Botswana Pula', 'BGN' => 'Bulgaria Lev', 'BRL' => 'Brazil Real', 'BND' => 'Brunei Darussalam Dollar', 'KHR' => 'Cambodia Riel', 'CAD' => 'Canada Dollar', 'KYD' => 'Cayman Islands Dollar', 'CLP' => 'Chile Peso', 'CNY' => 'China Yuan Renminbi', 'COP' => 'Colombia Peso', 'CRC' => 'Costa Rica Colon', 'HRK' => 'Croatia Kuna', 'CUP' => 'Cuba Peso', 'CZK' => 'Czech Republic Koruna', 'DKK' => 'Denmark Krone', 'DOP' => 'Dominican Republic Peso', 'XCD' => 'East Caribbean Dollar', 'EGP' => 'Egypt Pound', 'SVC' => 'El Salvador Colon', 'EEK' => 'Estonia Kroon', 'EUR' => 'Euro', 'FKP' => 'Falkland Islands (Malvinas) Pound', 'FJD' => 'Fiji Dollar', 'GHC' => 'Ghana Cedis', 'GIP' => 'Gibraltar Pound', 'GTQ' => 'Guatemala Quetzal', 'GGP' => 'Guernsey Pound', 'GYD' => 'Guyana Dollar', 'HNL' => 'Honduras Lempira', 'HKD' => 'Hong Kong Dollar', 'HUF' => 'Hungary Forint', 'ISK' => 'Iceland Krona', 'INR' => 'India Rupee', 'IDR' => 'Indonesia Rupiah', 'IRR' => 'Iran Rial', 'IMP' => 'Isle of Man Pound', 'ILS' => 'Israel Shekel', 'JMD' => 'Jamaica Dollar', 'JPY' => 'Japan Yen', 'JEP' => 'Jersey Pound', 'KZT' => 'Kazakhstan Tenge', 'KPW' => 'Korea (North) Won', 'KRW' => 'Korea (South) Won', 'KGS' => 'Kyrgyzstan Som', 'LAK' => 'Laos Kip', 'LVL' => 'Latvia Lat', 'LBP' => 'Lebanon Pound', 'LRD' => 'Liberia Dollar', 'LTL' => 'Lithuania Litas', 'MKD' => 'Macedonia Denar', 'MYR' => 'Malaysia Ringgit', 'MUR' => 'Mauritius Rupee', 'MXN' => 'Mexico Peso', 'MNT' => 'Mongolia Tughrik', 'MZN' => 'Mozambique Metical', 'NAD' => 'Namibia Dollar', 'NPR' => 'Nepal Rupee', 'ANG' => 'Netherlands Antilles Guilder', 'NZD' => 'New Zealand Dollar', 'NIO' => 'Nicaragua Cordoba', 'NGN' => 'Nigeria Naira', 'NOK' => 'Norway Krone', 'OMR' => 'Oman Rial', 'PKR' => 'Pakistan Rupee', 'PAB' => 'Panama Balboa', 'PYG' => 'Paraguay Guarani', 'PEN' => 'Peru Nuevo Sol', 'PHP' => 'Philippines Peso', 'PLN' => 'Poland Zloty', 'QAR' => 'Qatar Riyal', 'RON' => 'Romania New Leu', 'RUB' => 'Russia Ruble', 'SHP' => 'Saint Helena Pound', 'SAR' => 'Saudi Arabia Riyal', 'RSD' => 'Serbia Dinar', 'SCR' => 'Seychelles Rupee', 'SGD' => 'Singapore Dollar', 'SBD' => 'Solomon Islands Dollar', 'SOS' => 'Somalia Shilling', 'ZAR' => 'South Africa Rand', 'LKR' => 'Sri Lanka Rupee', 'SEK' => 'Sweden Krona', 'CHF' => 'Switzerland Franc', 'SRD' => 'Suriname Dollar', 'SYP' => 'Syria Pound', 'TWD' => 'Taiwan New Dollar', 'THB' => 'Thailand Baht', 'TTD' => 'Trinidad and Tobago Dollar', 'TRY' => 'Turkey Lira', 'TRL' => 'Turkey Lira', 'TVD' => 'Tuvalu Dollar', 'UAH' => 'Ukraine Hryvna', 'GBP' => 'United Kingdom Pound', 'USD' => 'United States Dollar', 'UYU' => 'Uruguay Peso', 'UZS' => 'Uzbekistan Som', 'VEF' => 'Venezuela Bolivar', 'VND' => 'Viet Nam Dong', 'YER' => 'Yemen Rial', 'ZWD' => 'Zimbabwe Dollar');

                $form[4]['currency'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $currency,
                    '#required' => true,
                    '#default_value' => isset($r->currency) ? $r->currency : null,
                    '#title' => $this->t('currency'),
                    '#suffix' => "</div>",
                );
            }


            $form[4]['bank'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->bank) ? $r->bank : null,
                '#attributes' => array('placeholder' => $this->t('Bank name for payment')),
                '#title' => $this->t('Bank'),
                '#prefix' => "<div class='container-inline'>",
                '#autocomplete_route_name' => 'ek_hr.look_up_bank',
            );

            $form[4]['bank_account'] = array(
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 50,
                '#default_value' => isset($r->bank_account) ? $r->bank_account : null,
                '#attributes' => array('placeholder' => $this->t('Bank account for payment')),
                '#title' => $this->t('Bank account'),
                '#suffix' => '</div>',
            );

            $form[4]['bank_account_status'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('own' => $this->t('own'), 'x' => $this->t('third party')),
                '#default_value' => isset($r->bank_account_status) ? $r->bank_account_status : null,
                '#title' => $this->t('Type of account'),
                '#required' => false,
                '#prefix' => "<div class='container-inline'>",
            );

            $form[4]['thirdp'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 100,
                '#default_value' => isset($r->thirdp) ? $r->thirdp : null,
                '#attributes' => array('placeholder' => $this->t('name of account')),
                '#title' => $this->t('Name of account payment'),
                '#states' => array(
                    // Hide data fieldset on codition
                    'invisible' => array(
                        "select[name='bank_account_status']" => array('value' => 'own'),
                    ),
                ),
                '#suffix' => '</div>',
            );


            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );
            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );
        }//if

        $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
        

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $triggering_element = $form_state->getTriggeringElement();
        //don't validate form on tpm image submit
        if ($triggering_element['#name'] != 'image_upload_button'
                && $triggering_element['#name'] != 'image_remove_button') {
            if ($form_state->get('step') == 1) {
                $form_state->set('step', 2);
                $form_state->set('coid', $form_state->getValue('coid'));
                $form_state->setRebuild();
            } elseif ($form_state->get('step') == 2) {

            //check name / id
                if ($form_state->getValue('new') == 1) {
                    $query = "SELECT id FROM {ek_hr_workforce} WHERE company_id=:id AND name = :n";
                    $a = array(':id' => $form_state->getValue('coid'), ':n' => $form_state->getValue('name'));
                    $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)->fetchField();

                    if ($data > 0) {
                        $form_state->setErrorByName('name', $this->t('The employee name already exist for this company.'));
                    }
                
                    $query = "SELECT custom_id FROM {ek_hr_workforce} WHERE custom_id=:id ";
                    $a = array(':id' => $form_state->getValue('custom_id'));
                    $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)->fetchField();

                    if ($data > 0) {
                        $form_state->setErrorByName('custom_id', $this->t('The given ID already exist.'));
                    }
                } else {
                    $query = "SELECT custom_id FROM {ek_hr_workforce} WHERE custom_id=:cid and id<>:id";
                    $a = array(':cid' => $form_state->getValue('custom_id'), ':id' => $form_state->getValue('for_id'));
                    $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)->fetchField();

                    if ($data > 0) {
                        $form_state->setErrorByName('custom_id', $this->t('The given ID already exist.'));
                    }
                }
                
            
            
            

                if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
                    $form_state->setErrorByName('email', $this->t('Invalid email'));
                }
                if (!is_numeric($form_state->getValue('salary'))) {
                    $form_state->setErrorByName("salary", $this->t('incorrect value for salary'));
                }
                if (!is_numeric($form_state->getValue('th_salary'))) {
                    $form_state->setErrorByName("th_salary", $this->t('incorrect value for average salary'));
                }

                if (!is_numeric($form_state->getValue('aleave'))) {
                    $form_state->setErrorByName("aleave", $this->t('incorrect value for leaves'));
                }
                if (!is_numeric($form_state->getValue('mcleave'))) {
                    $form_state->setErrorByName("mcleave", $this->t('incorrect value for medical leaves'));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 2) {
            
            //image
            //first delete current if requested
            $del = false;
            if ($form_state->getValue('image_delete') == 1) {
                \Drupal::service('file_system')->delete($form_state->getValue('uri'));
                $thumb = "private://hr/pictures/" . $form_state->getValue('coid') . "/40/40x40_" . basename($form_state->getValue('uri'));
                if (file_exists($thumb)) {
                    \Drupal::service('file_system')->delete($thumb);
                }
                \Drupal::messenger()->addStatus(t("Old picture deleted"));
                $image = '';
                $del = true;
            } else {
                $image = $form_state->getValue('uri');
            }
            
            //second, upload if any image is available
            $fid = $form_state->getValue(['image', 0]);
            if (!empty($fid)) {
                $file = $this->fileStorage->load($fid);
                $name = $file->getFileName();
                $dir = "private://hr/pictures/" . $form_state->getValue('coid');
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $image = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                    
                \Drupal::messenger()->addStatus(t("New Picture uploaded"));
                //remove old if any
                if (!$del && $form_state->getValue('uri') != '') {
                    \Drupal::service('file_system')->delete($form_state->getValue('uri'));
                }
            }
           
            /**/
            $admin = array();

            foreach ($form_state->getValue('admin') as $key => $value) {
                if ($value == 1) {
                    $admin[] = $key;
                }
            }

            if (!empty($admin)) {
                $admin = implode(',', $admin);
            } else {
                $admin = 0;
            }


            $fields = array(
                'company_id' => $form_state->getValue('coid'),
                'origin' => $form_state->getValue('origin'),
                'name' => Xss::filter($form_state->getValue('name')),
                'custom_id' => Xss::filter($form_state->getValue('custom_id')),
                'given_name' => '',
                'surname' => '',
                'email' => $form_state->getValue('email'),
                'address' => Xss::filter($form_state->getValue('address')),
                'telephone' => Xss::filter($form_state->getValue('telephone')),
                'sex' => $form_state->getValue('sex'),
                'rank' => $form_state->getValue('rank'),
                'ic_no' => Xss::filter($form_state->getValue('ic_no')),
                'ic_type' => '',
                'birth' => $form_state->getValue('birth'),
                'epf_no' => Xss::filter($form_state->getValue('epf_no')),
                'socso_no' => Xss::filter($form_state->getValue('socso_no')),
                'itax_no' => Xss::filter($form_state->getValue('itax_no')),
                'itax_c' => Xss::filter($form_state->getValue('itax_c')),
                'e_status' => $form_state->getValue('e_status'),
                'location' => $form_state->getValue('location'),
                'service' => $form_state->getValue('service'),
                'bank' => Xss::filter($form_state->getValue('bank')),
                'bank_account' => Xss::filter($form_state->getValue('bank_account')),
                'bank_account_status' => $form_state->getValue('bank_account_status'),
                'thirdp' => $form_state->getValue('thirdp'),
                'active' => $form_state->getValue('active'),
                'start' => $form_state->getValue('start'),
                'resign' => $form_state->getValue('resign'),
                'contract_expiration' => $form_state->getValue('contract_expiration'),
                'currency' => $form_state->getValue('currency'),
                'salary' => $form_state->getValue('salary'),
                'th_salary' => $form_state->getValue('th_salary'),
                'aleave' => $form_state->getValue('aleave'),
                'mcleave' => $form_state->getValue('mcleave'),
                'archive' => $form_state->getValue('archive'),
                'picture' => $image,
                'administrator' => $admin,
                'default_ps' => '',
                'note' => Xss::filter($form_state->getValue('note')),
            );

            if ($form_state->getValue('new') == 1) {
                // insert
                $db = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_hr_workforce')
                        ->fields($fields)
                        ->execute();
                
                
                $url = \Drupal\Core\Url::fromRoute('ek_hr.employee.view', array('id' => $db), array())->toString();
                \Drupal::messenger()->addStatus(t('Data updated. <a href="@url">View</a>', ['@url' => $url]));
            } else {
                //update
                $db = Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce')
                        ->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
                
                $url = \Drupal::messenger()->addStatus(t("Data updated"));
                $form_state->setRedirect('ek_hr.employee.view', ['id' => $form_state->getValue('for_id')]);
                
                if (null !== $form_state->getValue('current_coid')) {
                    if ($form_state->getValue('current_coid') != $form_state->getValue('coid')) {
                        \Drupal::messenger()->addWarning(t('Company was changed'));
                    }
                }
            }
            Cache::invalidateTags(['payroll_stat_block','employee_data_view']);
        }//step 2
    }

    /**
     * Callback
     */
    public function ajaxlookupbank(Request $request)
    {
        //autocomplete bank name if available
        $query = "SELECT DISTINCT bank from {ek_hr_workforce} WHERE bank like :b order by bank";
        $text = $request->query->get('q') . '%';

        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':b' => $text))->fetchCol();
        return new JsonResponse($data);
    }
}
