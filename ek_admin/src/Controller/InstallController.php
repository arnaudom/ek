<?php
/**
* @file
* Contains \Drupal\ek-admin\Controller\InstallController
*/

namespace Drupal\ek_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
* Controller routines for ek module routes.
*/
class InstallController extends ControllerBase {

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
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('module_handler')
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
 * data update upon migration from older system
 * @return Form
 *
*/

 public function migrate() {
   include_once drupal_get_path('module', 'ek_admin') . '/' . 'migrate.php';
  return  array('#markup' => $markup) ;
 
 }

/**
 * data merge 
 * combine data from other database tables with current data tables
 * @return Form
*/

 public function merge() {
     
        $form_builder = $this->formBuilder();
        $form = $form_builder->getForm('Drupal\ek_admin\Form\Merge');
        
        return array(
            $form,
            '#title' => t('Merge data'),
        );
 
 }
 
 
/**
 * install required tables in a separate database
 * 
*/

 public function install() {

    $query = "CREATE TABLE IF NOT EXISTS `ek_admin_settings` (
            `coid` INT NULL COMMENT 'company id, 0 = global',
            `settings` BLOB NULL COMMENT 'settings serialized array',
            UNIQUE INDEX `Index 1` (`coid`)
    )
    COMMENT='global and per company settings references'
    COLLATE='utf8_general_ci'
    ENGINE=InnoDB";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup = 'Settings table installed <br/>';
    
    $query = "INSERT INTO `ek_admin_settings` (`coid`) VALUES (0)";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup = 'Settings table updated <br/>';    

    $query = "CREATE TABLE IF NOT EXISTS `ek_company` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `access` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serialized uid list access',
            `settings` BLOB NULL COMMENT 'holds accounts settings',
            `name` VARCHAR(45) NULL DEFAULT NULL COMMENT 'company name',
            `reg_number` VARCHAR(45) NULL DEFAULT NULL COMMENT 'company registration number',
            `address1` TINYTEXT NULL COMMENT 'address line 1',
            `address2` TINYTEXT NULL COMMENT 'address line 2',
            `address3` TINYTEXT NULL COMMENT 'alternate address line 1',
            `address4` TINYTEXT NULL COMMENT 'alternate address line 2',
            `city` VARCHAR(250) NULL DEFAULT NULL COMMENT 'city name',
            `city2` VARCHAR(250) NULL DEFAULT NULL COMMENT 'alternate city name',
            `postcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'postcode',
            `postcode2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'alternate postcode',
            `country` VARCHAR(45) NULL DEFAULT NULL COMMENT 'country name',
            `country2` VARCHAR(250) NULL DEFAULT NULL COMMENT 'alternate country name',
            `telephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'telephone',
            `telephone2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'altenate telephone',
            `fax` VARCHAR(45) NULL DEFAULT NULL COMMENT 'fax',
            `fax2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'alternate fax',
            `email` VARCHAR(45) NULL DEFAULT NULL COMMENT 'email contact',
            `contact` VARCHAR(45) NULL DEFAULT NULL COMMENT 'contact person',
            `mobile` VARCHAR(45) NULL DEFAULT NULL COMMENT 'mobile contact',
            `logo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'image',
            `favicon` VARCHAR(50) NULL DEFAULT NULL COMMENT 'image' COLLATE 'utf8_general_ci',
            `sign` VARCHAR(255) NULL DEFAULT NULL COMMENT 'image' COLLATE 'utf8_general_ci',
            `short` VARCHAR(10) NULL DEFAULT NULL COMMENT 'short name',
            `accounts_year` VARCHAR(10) NULL DEFAULT NULL COMMENT 'financial year',
            `accounts_month` VARCHAR(10) NULL DEFAULT NULL COMMENT 'financial month',
            `active` VARCHAR(1) NOT NULL COMMENT 'active or inactive setting',
            `itax_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'income tax number',
            `pension_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'fund number',
            `social_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'fund number',
            `vat_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'vat tax number',
                PRIMARY KEY (`id`)
              )
              COMMENT='List of companies / entities'
              COLLATE='latin1_swedish_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Company / entity table installed <br/>';
   
    $query = "CREATE TABLE IF NOT EXISTS `ek_company_documents` (
              `id` INT(5) NOT NULL AUTO_INCREMENT,
              `coid` INT(5) NULL DEFAULT NULL COMMENT 'company id',
              `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id',
              `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.',
              `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file',
              `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment',
              `date` INT(10) NULL DEFAULT '0' COMMENT 'document date',
              `size` INT(10) NULL DEFAULT '0' COMMENT 'document size',
              `share` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared uid',
              `deny` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of denied uid',
              PRIMARY KEY (`id`)
            )
            COMMENT='holds data about uploaded company document'
            COLLATE='latin1_swedish_ci'
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
            
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Company documents table installed <br/>';
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_country` (
                `id` SMALLINT(5) NOT NULL AUTO_INCREMENT,
                `access` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serialized list uid',
                `name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'country name',
                `code` VARCHAR(5) NULL DEFAULT NULL COMMENT 'country code',
                `entity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'organization entity',
                `status` VARCHAR(5) NULL DEFAULT '1' COMMENT 'status 1, 0',
                PRIMARY KEY (`id`)
              )
              COMMENT='Countries table'
              COLLATE='latin1_swedish_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
              
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Countries table installed <br/>';              
    $name = Database::getConnection('external_db', 'external_db')->query('SELECT name from {ek_country} WHERE id=:id', array(':id' => 1))->fetchField();
    
    if($name == '') {
    
    $query = "INSERT INTO `ek_country` (`id`, `access`, `name`, `code`, `entity`, `status`) VALUES
              (1, NULL, 'AFGHANISTAN  ', 'AF', '', '0'),
              (2, NULL, 'ALAND ISLANDS ', 'AX', '', '0'),
              (3, NULL, 'ALBANIA ', 'AL', '', '0'),
              (4, NULL, 'ALGERIA ', 'DZ', '', '0'),
              (5, NULL, 'AMERICAN SAMOA ', 'AS', '', '0'),
              (6, NULL, 'ANDORRA ', 'AD', '', '0'),
              (7, NULL, 'ANGOLA ', 'AO', '', '0'),
              (8, NULL, 'ANGUILLA ', 'AI', '', '0'),
              (9, NULL, 'ANTARCTICA ', 'AQ', '', '0'),
              (10, NULL, 'ANTIGUA AND BARBUDA ', 'AG', '', '0'),
              (11, NULL, 'ARGENTINA ', 'AR', '', '0'),
              (12, NULL, 'ARMENIA ', 'AM', '', '0'),
              (13, NULL, 'ARUBA ', 'AW', '', '0'),
              (14, NULL, 'AUSTRALIA ', 'AU', '', '0'),
              (15, NULL, 'AUSTRIA ', 'AT', '', '0'),
              (16, NULL, 'AZERBAIJAN ', 'AZ', '', '0'),
              (17, NULL, 'BAHAMAS ', 'BS', '', '0'),
              (18, NULL, 'BAHRAIN ', 'BH', '', '0'),
              (19, NULL, 'BANGLADESH ', 'BD', '', '0'),
              (20, NULL, 'BARBADOS ', 'BB', '', '0'),
              (21, NULL, 'BELARUS ', 'BY', '', '0'),
              (22, NULL, 'BELGIUM ', 'BE', '', '0'),
              (23, NULL, 'BELIZE ', 'BZ', '', '0'),
              (24, NULL, 'BENIN ', 'BJ', '', '0'),
              (25, NULL, 'BERMUDA ', 'BM', '', '0'),
              (26, NULL, 'BHUTAN ', 'BT', '', '0'),
              (27, NULL, 'BOLIVIA', 'BO', '', '0'),
              (28, NULL, 'BOSNIA AND HERZEGOVINA ', 'BA', '', '0'),
              (29, NULL, 'BOTSWANA ', 'BW', '', '0'),
              (30, NULL, 'BOUVET ISLAND ', 'BV', '', '0'),
              (31, NULL, 'BRAZIL ', 'BR', '', '0'),
              (32, NULL, 'BRITISH INDIAN OCEAN TERRITORY ', 'IO', '', '0'),
              (33, NULL, 'BRUNEI DARUSSALAM ', 'BN', '', '0'),
              (34, NULL, 'BULGARIA ', 'BG', '', '0'),
              (35, NULL, 'BURKINA FASO ', 'BF', '', '0'),
              (36, NULL, 'BURUNDI ', 'BI', '', '0'),
              (37, NULL, 'CAMBODIA ', 'KH', '', '0'),
              (38, NULL, 'CAMEROON ', 'CM', '', '0'),
              (39, NULL, 'CANADA ', 'CA', '', '0'),
              (40, NULL, 'CAPE VERDE ', 'CV', '', '0'),
              (41, NULL, 'CAYMAN ISLANDS ', 'KY', '', '0'),
              (42, NULL, 'CENTRAL AFRICAN REPUBLIC ', 'CF', '', '0'),
              (43, NULL, 'CHAD ', 'TD', '', '0'),
              (44, NULL, 'CHILE ', 'CL', '', '0'),
              (45, NULL, 'CHINA ', 'CN', '', '0'),
              (46, NULL, 'CHRISTMAS ISLAND ', 'CX', '', '0'),
              (47, NULL, 'COCOS (KEELING) ISLANDS ', 'CC', '', '0'),
              (48, NULL, 'COLOMBIA ', 'CO', '', '0'),
              (49, NULL, 'COMOROS ', 'KM', '', '0'),
              (50, NULL, 'CONGO ', 'CG', '', '0'),
              (51, NULL, 'CONGO', 'CG', '', '0'),
              (52, NULL, 'COOK ISLANDS ', 'CK', '', '0'),
              (53, NULL, 'COSTA RICA ', 'CR', '', '0'),
              (54, NULL, 'COTE D\'IVOIRE ', 'CI', '', '0'),
              (55, NULL, 'CROATIA ', 'HR', '', '0'),
              (56, NULL, 'CUBA ', 'CU', '', '0'),
              (57, NULL, 'CYPRUS ', 'CY', '', '0'),
              (58, NULL, 'CZECH REPUBLIC ', 'CZ', '', '0'),
              (59, NULL, 'DENMARK ', 'DK', '', '0'),
              (60, NULL, 'DJIBOUTI ', 'DJ', '', '0'),
              (61, NULL, 'DOMINICA ', 'DM', '', '0'),
              (62, NULL, 'DOMINICAN REPUBLIC ', 'DO', '', '0'),
              (63, NULL, 'ECUADOR ', 'EC', '', '0'),
              (64, NULL, 'EGYPT ', 'EG', '', '0'),
              (65, NULL, 'EL SALVADOR ', 'SV', '', '0'),
              (66, NULL, 'EQUATORIAL GUINEA ', 'GQ', '', '0'),
              (67, NULL, 'ERITREA ', 'ER', '', '0'),
              (68, NULL, 'ESTONIA ', 'EE', '', '0'),
              (69, NULL, 'ETHIOPIA ', 'ET', '', '0'),
              (70, NULL, 'FALKLAND ISLANDS (MALVINAS) ', 'FK', '', '0'),
              (71, NULL, 'FAROE ISLANDS ', 'FO', '', '0'),
              (72, NULL, 'FIJI ', 'FJ', '', '0'),
              (73, NULL, 'FINLAND ', 'FI', '', '0'),
              (74, NULL, 'FRANCE ', 'FR', '', '0'),
              (75, NULL, 'FRENCH GUIANA ', 'GF', '', '0'),
              (76, NULL, 'FRENCH POLYNESIA ', 'PF', '', '0'),
              (77, NULL, 'FRENCH SOUTHERN TERRITORIES ', 'TF', '', '0'),
              (78, NULL, 'GABON ', 'GA', '', '0'),
              (79, NULL, 'GAMBIA ', 'GM', '', '0'),
              (80, NULL, 'GEORGIA ', 'GE', '', '0'),
              (81, NULL, 'GERMANY ', 'DE', '', '0'),
              (82, NULL, 'GHANA ', 'GH', '', '0'),
              (83, NULL, 'GIBRALTAR ', 'GI', '', '0'),
              (84, NULL, 'GREECE ', 'GR', '', '0'),
              (85, NULL, 'GREENLAND ', 'GL', '', '0'),
              (86, NULL, 'GRENADA ', 'GD', '', '0'),
              (87, NULL, 'GUADELOUPE ', 'GP', '', '0'),
              (88, NULL, 'GUAM ', 'GU', '', '0'),
              (89, NULL, 'GUATEMALA ', 'GT', '', '0'),
              (90, NULL, 'GUERNSEY ', 'GG', '', '0'),
              (91, NULL, 'GUINEA ', 'GN', '', '0'),
              (92, NULL, 'GUINEA-BISSAU ', 'GW', '', '0'),
              (93, NULL, 'GUYANA ', 'GY', '', '0'),
              (94, NULL, 'HAITI ', 'HT', '', '0'),
              (95, NULL, 'HEARD ISLAND AND MCDONALD ISLANDS ', 'HM', '', '0'),
              (96, NULL, 'HOLY SEE (VATICAN CITY STATE) ', 'VA', '', '0'),
              (97, NULL, 'HONDURAS ', 'HN', '', '0'),
              (98, NULL, 'HONG KONG ', 'HK', '', '0'),
              (99, NULL, 'HUNGARY ', 'HU', '', '0'),
              (100, NULL, 'ICELAND ', 'IS', '', '0'),
              (101, NULL, 'INDIA ', 'IN', '', '0'),
              (102, NULL, 'INDONESIA ', 'ID', '', '0'),
              (103, NULL, 'ISLAMIC REPUBLIC OF IRAN', 'IR', '', '0'),
              (104, NULL, 'IRAQ ', 'IQ', '', '0'),
              (105, NULL, 'IRELAND ', 'IE', '', '0'),
              (106, NULL, 'ISLE OF MAN ', 'IM', '', '0'),
              (107, NULL, 'ISRAEL ', 'IL', '', '0'),
              (108, NULL, 'ITALY ', 'IT', '', '0'),
              (109, NULL, 'JAMAICA ', 'JM', '', '0'),
              (110, NULL, 'JAPAN ', 'JP', '', '0'),
              (111, NULL, 'JERSEY ', 'JE', '', '0'),
              (112, NULL, 'JORDAN ', 'JO', '', '0'),
              (113, NULL, 'KAZAKHSTAN ', 'KZ', '', '0'),
              (114, NULL, 'KENYA ', 'KE', '', '0'),
              (115, NULL, 'KIRIBATI ', 'KI', '', '0'),
              (116, NULL, 'DEMOCRATIC PEOPLE\'S REPUBLIC OF KOREA', 'KP', '', '0'),
              (117, NULL, 'REPUBLIC OF KOREA', 'KR', '', '0'),
              (118, NULL, 'KUWAIT ', 'KW', '', '0'),
              (119, NULL, 'KYRGYZSTAN ', 'KG', '', '0'),
              (120, NULL, 'LAO PEOPLE\'S DEMOCRATIC REPUBLIC ', 'LA', '', '0'),
              (121, NULL, 'LATVIA ', 'LV', '', '0'),
              (122, NULL, 'LEBANON ', 'LB', '', '0'),
              (123, NULL, 'LESOTHO ', 'LS', '', '0'),
              (124, NULL, 'LIBERIA ', 'LR', '', '0'),
              (125, NULL, 'LIBYAN ARAB JAMAHIRIYA ', 'LY', '', '0'),
              (126, NULL, 'LIECHTENSTEIN ', 'LI', '', '0'),
              (127, NULL, 'LITHUANIA ', 'LT', '', '0'),
              (128, NULL, 'LUXEMBOURG ', 'LU', '', '0'),
              (129, NULL, 'MACAO ', 'MO', '', '0'),
              (130, NULL, 'THE FORMER YUGOSLAV REPUBLIC OFMACEDONIA', 'MK', '', '0'),
              (131, NULL, 'MADAGASCAR ', 'MG', '', '0'),
              (132, NULL, 'MALAWI ', 'MW', '', '0'),
              (133, NULL, 'MALAYSIA ', 'MY', '', '0'),
              (134, NULL, 'MALDIVES ', 'MV', '', '0'),
              (135, NULL, 'MALI ', 'ML', '', '0'),
              (136, NULL, 'MALTA ', 'MT', '', '0'),
              (137, NULL, 'MARSHALL ISLANDS ', 'MH', '', '0'),
              (138, NULL, 'MARTINIQUE ', 'MQ', '', '0'),
              (139, NULL, 'MAURITANIA ', 'MR', '', '0'),
              (140, NULL, 'MAURITIUS ', 'MU', '', '0'),
              (141, NULL, 'MAYOTTE ', 'YT', '', '0'),
              (142, NULL, 'MEXICO ', 'MX', '', '0'),
              (143, NULL, 'FEDERATED STATES OF MICRONESIA', 'FM', '', '0'),
              (144, NULL, 'REPUBLIC OF MOLDOVA', 'MD', '', '0'),
              (145, NULL, 'MONACO ', 'MC', '', '0'),
              (146, NULL, 'MONGOLIA ', 'MN', '', '0'),
              (147, NULL, 'MONTENEGRO ', 'ME', '', '0'),
              (148, NULL, 'MONTSERRAT ', 'MS', '', '0'),
              (149, NULL, 'MOROCCO ', 'MA', '', '0'),
              (150, NULL, 'MOZAMBIQUE ', 'MZ', '', '0'),
              (151, NULL, 'MYANMAR ', 'MM', '', '0'),
              (152, NULL, 'NAMIBIA ', 'NA', '', '0'),
              (153, NULL, 'NAURU ', 'NR', '', '0'),
              (154, NULL, 'NEPAL ', 'NP', '', '0'),
              (155, NULL, 'NETHERLANDS ', 'NL', '', '0'),
              (156, NULL, 'NETHERLANDS ANTILLES ', 'AN', '', '0'),
              (157, NULL, 'NEW CALEDONIA ', 'NC', '', '0'),
              (158, NULL, 'NEW ZEALAND ', 'NZ', '', '0'),
              (159, NULL, 'NICARAGUA ', 'NI', '', '0'),
              (160, NULL, 'NIGER ', 'NE', '', '0'),
              (161, NULL, 'NIGERIA ', 'NG', '', '0'),
              (162, NULL, 'NIUE ', 'NU', '', '0'),
              (163, NULL, 'NORFOLK ISLAND ', 'NF', '', '0'),
              (164, NULL, 'NORTHERN MARIANA ISLANDS ', 'MP', '', '0'),
              (165, NULL, 'NORWAY ', 'NO', '', '0'),
              (166, NULL, 'OMAN ', 'OM', '', '0'),
              (167, NULL, 'PAKISTAN ', 'PK', '', '0'),
              (168, NULL, 'PALAU ', 'PW', '', '0'),
              (169, NULL, 'PALESTINIAN TERRITORY', 'PS', '', '0'),
              (170, NULL, 'PANAMA ', 'PA', '', '0'),
              (171, NULL, 'PAPUA NEW GUINEA ', 'PG', '', '0'),
              (172, NULL, 'PARAGUAY ', 'PY', '', '0'),
              (173, NULL, 'PERU ', 'PE', '', '0'),
              (174, NULL, 'PHILIPPINES ', 'PH', '', '0'),
              (175, NULL, 'PITCAIRN ', 'PN', '', '0'),
              (176, NULL, 'POLAND ', 'PL', '', '0'),
              (177, NULL, 'PORTUGAL ', 'PT', '', '0'),
              (178, NULL, 'PUERTO RICO ', 'PR', '', '0'),
              (179, NULL, 'QATAR ', 'QA', '', '0'),
              (180, NULL, 'REUNION ', 'RE', '', '0'),
              (181, NULL, 'ROMANIA ', 'RO', '', '0'),
              (182, NULL, 'RUSSIAN FEDERATION ', 'RU', '', '0'),
              (183, NULL, 'RWANDA ', 'RW', '', '0'),
              (184, NULL, 'SAINT BARTH?LEMY ', 'BL', '', '0'),
              (185, NULL, 'SAINT HELENA', 'SH', '', '0'),
              (186, NULL, 'SAINT KITTS AND NEVIS ', 'KN', '', '0'),
              (187, NULL, 'SAINT LUCIA ', 'LC', '', '0'),
              (188, NULL, 'SAINT MARTIN ', 'MF', '', '0'),
              (189, NULL, 'SAINT PIERRE AND MIQUELON ', 'PM', '', '0'),
              (190, NULL, 'SAINT VINCENT AND THE GRENADINES ', 'VC', '', '0'),
              (191, NULL, 'SAMOA ', 'WS', '', '0'),
              (192, NULL, 'SAN MARINO ', 'SM', '', '0'),
              (193, NULL, 'SAO TOME AND PRINCIPE ', 'ST', '', '0'),
              (194, NULL, 'SAUDI ARABIA ', 'SA', '', '0'),
              (195, NULL, 'SENEGAL ', 'SN', '', '0'),
              (196, NULL, 'SERBIA ', 'RS', '', '0'),
              (197, NULL, 'SEYCHELLES ', 'SC', '', '0'),
              (198, NULL, 'SIERRA LEONE ', 'SL', '', '0'),
              (199, NULL, 'SINGAPORE ', 'SG', '', '0'),
              (200, NULL, 'SLOVAKIA ', 'SK', '', '0'),
              (201, NULL, 'SLOVENIA ', 'SI', '', '0'),
              (202, NULL, 'SOLOMON ISLANDS ', 'SB', '', '0'),
              (203, NULL, 'SOMALIA ', 'SO', '', '0'),
              (204, NULL, 'SOUTH AFRICA ', 'ZA', '', '0'),
              (205, NULL, 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS ', 'GS', '', '0'),
              (206, NULL, 'SPAIN ', 'ES', '', '0'),
              (207, NULL, 'SRI LANKA ', 'LK', '', '0'),
              (208, NULL, 'SUDAN ', 'SD', '', '0'),
              (209, NULL, 'SURINAME ', 'SR', '', '0'),
              (210, NULL, 'SVALBARD AND JAN MAYEN ', 'SJ', '', '0'),
              (211, NULL, 'SWAZILAND ', 'SZ', '', '0'),
              (212, NULL, 'SWEDEN ', 'SE', '', '0'),
              (213, NULL, 'SWITZERLAND ', 'CH', '', '0'),
              (214, NULL, 'SYRIAN ARAB REPUBLIC ', 'SY', '', '0'),
              (215, NULL, 'TAIWAN', 'TW', '', '0'),
              (216, NULL, 'TAJIKISTAN ', 'TJ', '', '0'),
              (217, NULL, 'UNITED REPUBLIC OF TANZANIA', 'TZ', '', '0'),
              (218, NULL, 'THAILAND ', 'TH', '', '0'),
              (219, NULL, 'TIMOR-LESTE ', 'TL', '', '0'),
              (220, NULL, 'TOGO ', 'TG', '', '0'),
              (221, NULL, 'TOKELAU ', 'TK', '', '0'),
              (222, NULL, 'TONGA ', 'TO', '', '0'),
              (223, NULL, 'TRINIDAD AND TOBAGO ', 'TT', '', '0'),
              (224, NULL, 'TUNISIA ', 'TN', '', '0'),
              (225, NULL, 'TURKEY ', 'TR', '', '0'),
              (226, NULL, 'TURKMENISTAN ', 'TM', '', '0'),
              (227, NULL, 'TURKS AND CAICOS ISLANDS ', 'TC', '', '0'),
              (228, NULL, 'TUVALU ', 'TV', '', '0'),
              (229, NULL, 'UGANDA ', 'UG', '', '0'),
              (230, NULL, 'UKRAINE ', 'UA', '', '0'),
              (231, NULL, 'UNITED ARAB EMIRATES ', 'AE', '', '0'),
              (232, NULL, 'UNITED KINGDOM ', 'GB', '', '0'),
              (233, NULL, 'UNITED STATES ', 'US', '', '0'),
              (234, NULL, 'UNITED STATES MINOR OUTLYING ISLANDS ', 'UM', '', '0'),
              (235, NULL, 'URUGUAY ', 'UY', '', '0'),
              (236, NULL, 'UZBEKISTAN ', 'UZ', '', '0'),
              (237, NULL, 'VANUATU ', 'VU', '', '0'),
              (238, NULL, 'VATICAN CITY STATE ', 'VA', '', '0'),
              (239, NULL, 'VENEZUELA', 'VE', '', '0'),
              (240, NULL, 'VIET NAM ', 'VN', '', '0'),
              (241, NULL, 'BRITISH VIRGIN ISLANDS', 'VG', '', '0'),
              (242, NULL, 'U.S. VIRGIN ISLANDS', 'VI', '', '0'),
              (243, NULL, 'WALLIS AND FUTUNA ', 'WF', '', '0'),
              (244, NULL, 'WESTERN SAHARA ', 'EH', '', '0'),
              (245, NULL, 'YEMEN ', 'YE', '', '0'),
              (246, NULL, 'ZAMBIA ', 'ZM', '', '0'),
              (247, NULL, 'ZIMBABWE ', 'ZW ', '', '0') ";                 

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Countries data updated <br/>'; 
    }
    
    
    $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
    $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
       
    return  array(
      '#title'=> t('Installation of Ek_admin module'),
      '#markup' => $markup
      ) ;
 
 }


   
} //class