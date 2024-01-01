<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\InstallController
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for ek module routes.
 */
class InstallController extends ControllerBase
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
     * Constructs  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * used by administrator to migrate data from older versions
     * @return array
     * render Html
     */
    public function update()
    {
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/' . 'update.php';
        return array('#markup' => $markup);
    }

    /**
     * install required data tables in a separate database
     * @return array
     * render Html
     */
    public function install()
    {
        /**/
        $query = "CREATE TABLE IF NOT EXISTS `ek_finance` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`settings` TEXT NULL COMMENT 'settings object',
	PRIMARY KEY (`id`)
        )
        COMMENT='settings for finance and accounts'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Finance settings table installed <br/>';
        }

        try {
            $query = "INSERT INTO `ek_finance` (`id`, `settings`) VALUES
          (1, '')";
            $db = Database::getConnection('external_db', 'external_db')->query($query);
        } catch (Exception $e) {
            $markup .= '<br/><b>Caught exception for settings: ' . $e->getMessage() . "</b>\n";
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_accounts` (
        `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `aid` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'account id',
        `aname` VARCHAR(50) NULL DEFAULT '255' COMMENT 'account name',
        `atype` VARCHAR(50) NULL DEFAULT NULL COMMENT 'account type',
        `astatus` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'account status 0 = inactive 1 = active',
        `coid` VARCHAR(3) NULL DEFAULT NULL COMMENT 'company id reference',
        `link` VARCHAR(10) NULL DEFAULT NULL COMMENT 'link to other aid',
        `balance` DOUBLE NOT NULL DEFAULT '0' COMMENT 'balance value in  currency',
        `balance_base` DOUBLE NOT NULL DEFAULT '0' COMMENT 'balance in base currency',
        `balance_date` VARCHAR(10) NULL DEFAULT NULL COMMENT 'balance date',
      PRIMARY KEY (`id`),
      INDEX `aid` (`aid`)
    )
    COMMENT='financial accounts references'
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance accounts chart table installed <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_bank` (
        `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'bank name' COLLATE 'utf8_unicode_ci',
        `address1` VARCHAR(255) NULL DEFAULT '' COMMENT 'address line 1' COLLATE 'utf8_unicode_ci',
        `address2` VARCHAR(255) NULL DEFAULT '' COMMENT 'address line 2' COLLATE 'utf8_unicode_ci',
        `postcode` VARCHAR(45) NULL DEFAULT '' COMMENT 'post code' COLLATE 'utf8_unicode_ci',
        `country` VARCHAR(45) NULL DEFAULT '' COMMENT 'country' COLLATE 'utf8_unicode_ci',
        `contact` VARCHAR(100) NULL DEFAULT '' COMMENT 'contact name' COLLATE 'utf8_unicode_ci',
        `telephone` VARCHAR(45) NULL DEFAULT '' COMMENT 'telephone No' COLLATE 'utf8_unicode_ci',
        `fax` VARCHAR(45) NULL DEFAULT '' COMMENT 'fax No' COLLATE 'utf8_unicode_ci',
        `email` VARCHAR(45) NULL DEFAULT '' COMMENT 'contact email' COLLATE 'utf8_unicode_ci',
        `account1` VARCHAR(45) NULL DEFAULT '' COMMENT 'account No 1' COLLATE 'utf8_unicode_ci',
        `account2` VARCHAR(45) NULL DEFAULT '' COMMENT 'account No 2' COLLATE 'utf8_unicode_ci',
        `swift` VARCHAR(45) NULL DEFAULT '' COMMENT 'swift or BIC' COLLATE 'utf8_unicode_ci',
        `bank_code` VARCHAR(45) NULL DEFAULT '' COMMENT 'bank code' COLLATE 'utf8_unicode_ci',
        `coid` VARCHAR(5) NOT NULL COMMENT 'company id' COLLATE 'utf8_unicode_ci',
        `abid` INT(10) UNSIGNED NULL COMMENT 'Address book id' COLLATE 'utf8_unicode_ci',
        PRIMARY KEY (`id`)
      )
      COMMENT='Bank entities'
      COLLATE='utf8_unicode_ci'
      ENGINE=InnoDB
      AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance bank table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_bank_accounts` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `account_ref` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'account No reference',
          `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'currency of account',
          `bid` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'bank id reference from bank table',
          `aid` INT(5) NULL DEFAULT NULL COMMENT 'account id from chart',
          `active` SMALLINT(1) NULL DEFAULT '1' COMMENT 'Active status',
          `beneficiary` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Beneficiary name',
          PRIMARY KEY (`id`)
        )
        COMMENT='list of bank acounts'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance bank accounts table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_bank_transactions` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `account_ref` VARCHAR(45) NOT NULL COMMENT 'account No reference from bank accounts table' COLLATE 'utf8_unicode_ci',
          `date_transaction` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'date of transaction record' COLLATE 'utf8_unicode_ci',
          `year_transaction` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'year of transaction' COLLATE 'utf8_unicode_ci',
          `type` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'type (debit, credit)' COLLATE 'utf8_unicode_ci',
          `currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'currency' COLLATE 'utf8_unicode_ci',
          `amount` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'value of transaction' COLLATE 'utf8_unicode_ci',
          `description` TINYTEXT NULL COMMENT 'description' COLLATE 'utf8_unicode_ci',
          PRIMARY KEY (`id`)
        )
        COMMENT='listing of bank transactions. Generated after reconciliation'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=3";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance bank transactions table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_cash` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `date` VARCHAR(20) NOT NULL DEFAULT '0000-00-00' COMMENT 'transaction record',
          `pay_date` VARCHAR(20) NOT NULL DEFAULT '0000-00-00' COMMENT 'transaction date',
          `type` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'debit, credit' COLLATE 'utf8_unicode_ci',
          `amount` DOUBLE NULL DEFAULT NULL COMMENT 'value in currency',
          `cashamount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'value in base currency',
          `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'transaction currency' COLLATE 'utf8_unicode_ci',
          `coid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'company id' COLLATE 'utf8_unicode_ci',
          `baid` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'bank account id',
          `uid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'user id, employee id' COLLATE 'utf8_unicode_ci',
          `comment` TINYTEXT NULL COMMENT 'comment' COLLATE 'utf8_unicode_ci',
          `reconcile` TINYINT(3) NOT NULL DEFAULT '0' COMMENT 'reconciliation status 0 = not reconciled',
          PRIMARY KEY (`id`)
        )
        COMMENT='Use to compile cash allocations'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";


        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance cash transactions table installed <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_currency` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'currency code',
          `name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'currency name',
          `rate` DOUBLE NOT NULL DEFAULT '0' COMMENT 'exchange rate against base currency',
          `active` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'status 1 = active 0 = inactive',
          `date` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'date of exchange value',
          PRIMARY KEY (`id`)
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance currencies transactions table installed <br/>';
        }

        try {
            $query = "
            INSERT INTO `ek_currency` (`id`, `currency`, `name`, `rate`, `active`, `date`) VALUES
            (1, 'BIF', 'Franc Burundi', 0, 0, ''),
            (2, 'XAF', 'Franc CFA', 0, 0, ''),
            (3, 'DJF', 'Franc Djibouti', 0, 0, ''),
            (4, 'ETB', 'BIRR', 0, 0, ''),
            (5, 'USD', 'US dollar', 0, 0, ''),
            (6, 'SCR', 'Roupie Seychelles', 0, 0, ''),
            (7, 'EUR', 'EURO', 0, 0, ''),
            (8, 'KES', 'Shilling kenyan', 0, 0, ''),
            (9, 'ZAR', 'Rand Commercial', 0, 0, ''),
            (10, 'DZD', 'Dinar Algerian', 0, 0, ''),
            (11, 'SAR', 'Riyal', 0, 0, ''),
            (12, 'AUD', 'Dollar Austalian', 0, 0, ''),
            (13, 'BHD', 'Dinar Bahrein', 0, 0, ''),
            (14, 'BDT', 'Taka', 0, 0, ''),
            (15, 'BMD', 'Dollar Bermuda', 0, 0, ''),
            (16, 'BWP', 'Pula', 0, 0, ''),
            (17, 'BND', 'Dollar Brunei', 0, 0, ''),
            (18, 'CAD', 'Dollar Canadien', 0, 0, ''),
            (19, 'CVE', 'Escudo', 0, 0, ''),
            (20, 'CYP', 'Livre Cypriote', 0, 0, ''),
            (21, 'NZD', 'Dollar New Zealand', 0, 0, ''),
            (22, 'DKK', 'Couronne Danish', 0, 0, ''),
            (23, 'EGP', 'Livre Egyptian', 0, 0, ''),
            (24, 'AED', 'Dirham Emirates', 0, 0, ''),
            (25, 'FJD', 'Dollar Fidji', 0, 0, ''),
            (26, 'GMD', 'Dalaisie', 0, 0, ''),
            (27, 'GRD', 'Livre Sterling', 0, 0, ''),
            (28, 'ISK', 'Couronne Island', 0, 0, ''),
            (29, 'JPY', 'Yen', 0, 0, ''),
            (30, 'JOD', 'Dinar Jordanian', 0, 0, ''),
            (31, 'KWD', 'Dinar Koweit', 0, 0, ''),
            (32, 'LRD', 'Dollar Liberian', 0, 0, ''),
            (33, 'LYD', 'Dinar Libyan', 0, 0, ''),
            (34, 'CHF', 'Franc Swiss', 0, 0, ''),
            (35, 'LTL', 'Litas', 0, 0, ''),
            (36, 'HKD', 'Dollar Hong Kong', 0, 0, ''),
            (37, 'MYR', 'Ringgit', 0, 0, ''),
            (38, 'MTP', 'Livre Malta', 0, 0, ''),
            (39, 'MAD', 'Dirha', 0, 0, ''),
            (40, 'MUR', 'Roupie Ile Maurice', 0, 0, ''),
            (41, 'MRO', 'Ouguyia', 0, 0, ''),
            (42, 'NAD', 'Dollar Namibien', 0, 0, ''),
            (43, 'NOK', 'Couronne Norvegian', 0, 0, ''),
            (44, 'OMR', 'Rial Omani', 0, 0, ''),
            (45, 'PGK', 'Kina', 0, 0, ''),
            (46, 'PHP', 'Peso Philippin', 0, 0, ''),
            (47, 'QAR', 'Riyal de Qatar', 0, 0, ''),
            (48, 'VUV', 'Vatu', 0, 0, ''),
            (49, 'SGD', 'Dollar Singapour', 0, 0, ''),
            (50, 'LKR', 'Roupie Cingalaise', 0, 0, ''),
            (51, 'SEK', 'Couronne Swedish', 0, 0, ''),
            (53, 'SYP', 'Livre Syrienne', 0, 0, ''),
            (54, 'TWD', 'Dollar Taiwan', 0, 0, ''),
            (55, 'THB', 'Baht', 0, 0, ''),
            (56, 'TND', 'Dinar Tunisien', 0, 0, ''),
            (57, 'RMB', 'Reminbi', 0, 0, ''),
            (58, 'VND', 'Dong Vietnam', 0, 0, ''),
            (59, 'IDR', 'Roupia Indonesia', 0, 0, ''),
            (60, 'AFN', 'Afghan Afghani', 0, 0, ''),
            (61, 'ALL', 'Albanian Lek', 0, 0, ''),
            (62, 'AMD', 'Armenian Dram', 0, 0, ''),
            (63, 'ANG', 'Netherlands Antillian Guilder', 0, 0, ''),
            (64, 'AOA', 'Angolan Kwanza', 0, 0, ''),
            (65, 'ARS', 'Argentine Peso', 0, 0, ''),
            (66, 'AWG', 'Aruban Guilder', 0, 0, ''),
            (67, 'AZN', 'Azerbaijanian Manat', 0, 0, ''),
            (68, 'BAM', 'Bosnia-Herzegovina Convertible Marks', 0, 0, ''),
            (69, 'BBD', 'Barbados Dollar', 0, 0, ''),
            (70, 'BGN', 'Bulgarian Lev', 0, 0, ''),
            (71, 'BOB', 'Bolivian Boliviano', 0, 0, ''),
            (72, 'BOV', 'Bolivian Mvdol', 0, 0, ''),
            (73, 'BRL', 'Brazilian Real', 0, 0, ''),
            (74, 'BSD', 'Bahamian Dollar', 0, 0, ''),
            (75, 'BTN', 'Bhutan Ngultrum', 0, 0, ''),
            (76, 'BYR', 'Belarussian Ruble', 0, 0, ''),
            (77, 'BZD', 'Belize Dollar', 0, 0, ''),
            (78, 'CDF', 'Franc Congolais', 0, 0, ''),
            (79, 'CHE', 'Swiss WIR Euro', 0, 0, ''),
            (80, 'CHW', 'Swiss WIR Franc', 0, 0, ''),
            (81, 'CLF', 'Chiliean Unidades de formento', 0, 0, ''),
            (82, 'CLP', 'Chilean Peso', 0, 0, ''),
            (83, 'CNY', 'Chinese Yuan Renminbi', 0, 0, ''),
            (84, 'COP', 'Colombian Peso', 0, 0, ''),
            (85, 'COU', 'Colombian Unidad de Valor Real', 0, 0, ''),
            (86, 'CRC', 'Costa Rican Colon', 0, 0, ''),
            (87, 'CUP', 'Cuban Peso', 0, 0, ''),
            (88, 'CZK', 'Czech Koruna', 0, 0, ''),
            (89, 'DOP', 'Dominican Peso', 0, 0, ''),
            (90, 'EEK', 'Estonian Kroon', 0, 0, ''),
            (91, 'ERN', 'Eritrean Nakfa', 0, 0, ''),
            (92, 'FKP', 'Falkland Islands Pound', 0, 0, ''),
            (93, 'GBP', 'United Kingdom Pound Sterling', 0, 0, ''),
            (94, 'GEL', 'Georgian Lari', 0, 0, ''),
            (95, 'GHS', 'Ghanean Cedi', 0, 0, ''),
            (96, 'GIP', 'Gibraltar pound', 0, 0, ''),
            (97, 'GNF', 'Guinea Franc', 0, 0, ''),
            (98, 'GTQ', 'Guatemala Quetzal', 0, 0, ''),
            (99, 'GYD', 'Guyana Dollar', 0, 0, ''),
            (100, 'HNL', 'Honduran Lempira', 0, 0, ''),
            (101, 'HRK', 'Croatian Kuna', 0, 0, ''),
            (102, 'HTG', 'Haiti Gourde', 0, 0, ''),
            (103, 'HUF', 'Hungarian Forint', 0, 0, ''),
            (104, 'ILS', 'New Israeli Shekel', 0, 0, ''),
            (105, 'INR', 'Indian Rupee', 0, 0, ''),
            (106, 'IQD', 'Iraqi Dinar', 0, 0, ''),
            (107, 'IRR', 'Iranian Rial', 0, 0, ''),
            (108, 'JMD', 'Jamaican Dollar', 0, 0, ''),
            (109, 'KGS', 'Kyrgyzstan Som', 0, 0, ''),
            (110, 'KHR', 'Cambodian Riel', 0, 0, ''),
            (111, 'KMF', 'Comoro Franc', 0, 0, ''),
            (112, 'KPW', 'North Korean Won', 0, 0, ''),
            (113, 'KRW', 'South Korean Won', 0, 0, ''),
            (114, 'KYD', 'Cayman Islands Dollar', 0, 0, ''),
            (115, 'KZT', 'Kazakhstan Tenge', 0, 0, ''),
            (116, 'LAK', 'Laos Kip', 0, 0, ''),
            (117, 'LBP', 'Lebanese Pound', 0, 0, ''),
            (118, 'LSL', 'Lesotho Loti', 0, 0, ''),
            (119, 'LVL', 'Latvian Lats', 0, 0, ''),
            (120, 'MDL', 'Moldovan Leu', 0, 0, ''),
            (121, 'MGA', 'Malagasy Ariary', 0, 0, ''),
            (122, 'MKD', 'Macedonian Denar', 0, 0, ''),
            (123, 'MMK', 'Myanmar Kyat', 0, 0, ''),
            (124, 'MNT', 'Mongolian Tugrik', 0, 0, ''),
            (125, 'MOP', 'Macau Pataca', 0, 0, ''),
            (126, 'MTL', 'Maltese Lira', 0, 0, ''),
            (127, 'MVR', 'Maldives Rufiyaa', 0, 0, ''),
            (128, 'MWK', 'Malawian Kwacha', 0, 0, ''),
            (129, 'MXN', 'Mexican Peso', 0, 0, ''),
            (130, 'MXV', 'Mexican Unidad de Inversion', 0, 0, ''),
            (131, 'MZN', 'Mozambican Metical', 0, 0, ''),
            (132, 'NGN', 'Nigerian Naira', 0, 0, ''),
            (133, 'NIO', 'Nicaraguan Cordoba Oro', 0, 0, ''),
            (134, 'NPR', 'Nepalese Rupee', 0, 0, ''),
            (135, 'PAB', 'Panamanian Balboa', 0, 0, ''),
            (136, 'PEN', 'Peruvian Nuevo Sol', 0, 0, ''),
            (137, 'PKR', 'Pakistan Rupee', 0, 0, ''),
            (138, 'PLN', 'Polish Zloty', 0, 0, ''),
            (139, 'PYG', 'Paraguayan Guarani', 0, 0, ''),
            (140, 'RON', 'Romanian New Leu', 0, 0, ''),
            (141, 'RSD', 'Serbian Dinar', 0, 0, ''),
            (142, 'RUB', 'Russian Ruble', 0, 0, ''),
            (143, 'RWF', 'Rwandan Franc', 0, 0, ''),
            (144, 'SBD', 'Solomon Islands Dollar', 0, 0, ''),
            (145, 'SDG', 'Sudanese Pound', 0, 0, ''),
            (146, 'SHP', 'Saint Helena Pound', 0, 0, ''),
            (147, 'SKK', 'Slovak Koruna', 0, 0, ''),
            (148, 'SLL', 'Sierra Leonean Leone', 0, 0, ''),
            (149, 'SOS', 'Somali Shilling', 0, 0, ''),
            (150, 'SRD', 'Surinam Dollar', 0, 0, ''),
            (151, 'STD', 'São Tomean Dobra', 0, 0, ''),
            (152, 'SZL', 'Swazi Lilangeni', 0, 0, ''),
            (153, 'TJS', 'Tajikistan Somoni', 0, 0, ''),
            (154, 'TMM', 'Turkmenistan Manat', 0, 0, ''),
            (155, 'TOP', 'Tongan Pa\'anga (TOP)', 0, 0, ''),
            (156, 'TRY', 'Turkish Lira', 0, 0, ''),
            (157, 'TTD', 'Trinidad and Tobago Dollar', 0, 0, ''),
            (158, 'TZS', 'Tanzanian Shilling', 0, 0, ''),
            (159, 'UAH', 'Ukrainian Hryvnia', 0, 0, ''),
            (160, 'UGX', 'Uganda Shilling', 0, 0, ''),
            (161, 'USN', 'US dollar next day', 0, 0, ''),
            (162, 'USS', 'US dollar same day', 0, 0, ''),
            (163, 'UYU', 'Peso Uruguayo', 0, 0, ''),
            (164, 'UZS', 'Uzbekistan Som', 0, 0, ''),
            (165, 'VEB', 'Venezuelan bolivar', 0, 0, ''),
            (166, 'WST', 'Samoan Tala', 0, 0, ''),
            (167, 'XAG', 'Silver', 0, 0, ''),
            (168, 'XAU', 'Gold', 0, 0, ''),
            (169, 'XBA', 'European Composite Unit', 0, 0, ''),
            (170, 'XBB', 'European Monetary Unit', 0, 0, ''),
            (171, 'XBC', 'European Unit of Account 9', 0, 0, ''),
            (172, 'XBD', 'European Unit of Account 17', 0, 0, ''),
            (173, 'XCD', 'East Caribbean Dollar', 0, 0, ''),
            (174, 'XDR', 'IMF Special Drawing Rights', 0, 0, ''),
            (175, 'XFO', 'BIS Gold franc', 0, 0, ''),
            (176, 'XFU', 'UIC franc', 0, 0, ''),
            (177, 'XOF', 'CFA Franc BCEAO', 0, 0, ''),
            (178, 'XPD', 'Palladium', 0, 0, ''),
            (179, 'XPF', 'CFP franc', 0, 0, ''),
            (180, 'XPT', 'Platinum', 0, 0, ''),
            (181, 'YER', 'Yemeni Rial', 0, 0, ''),
            (182, 'ZMK', 'Zambia Kwacha', 0, 0, ''),
            (183, 'ZWD', 'Zimbabwe Dollar', 0, 0, '')";

            $db = Database::getConnection('external_db', 'external_db')->query($query);
            if ($db) {
                $markup .= 'Finance currencies data updated <br/>';
            }
        } catch (Exception $e) {
            $markup .= '<br/><b>Caught exception for currencies: ' . $e->getMessage() . "</b>\n";
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_expenses` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `class` VARCHAR(200) NOT NULL COMMENT 'Class of account' COLLATE 'utf8_unicode_ci',
          `type` VARCHAR(200) NOT NULL COMMENT 'account id from chart' COLLATE 'utf8_unicode_ci',
          `allocation` VARCHAR(200) NOT NULL COMMENT 'allocated expenses coid' COLLATE 'utf8_unicode_ci',
          `company` VARCHAR(3) NULL DEFAULT NULL COMMENT 'payment coid reference' COLLATE 'utf8_unicode_ci',
          `localcurrency` DOUBLE NOT NULL DEFAULT '0' COMMENT 'value local currency',
          `rate` DOUBLE NOT NULL DEFAULT '0' COMMENT 'exchange rate',
          `amount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'amount base currency',
          `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'currency code' COLLATE 'utf8_unicode_ci',
          `amount_paid` DOUBLE NULL DEFAULT NULL COMMENT 'paid amount value',
          `tax` DOUBLE NULL DEFAULT NULL COMMENT 'tax value if any',
          `year` VARCHAR(10) NULL NULL DEFAULT '0' COMMENT 'transaction year',
          `month` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'transaction month',
          `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' COLLATE 'utf8_unicode_ci',
          `pcode` VARCHAR(45) NULL DEFAULT '' COMMENT 'project code reference' COLLATE 'utf8_general_ci',
          `clientname` VARCHAR(100) NULL DEFAULT NULL COMMENT 'client reference' COLLATE 'utf8_unicode_ci',
          `suppliername` VARCHAR(250) NULL DEFAULT NULL COMMENT 'supplier reference' COLLATE 'utf8_unicode_ci',
          `receipt` VARCHAR(45) NULL DEFAULT '' COMMENT 'receipt status' COLLATE 'utf8_unicode_ci',
          `employee` VARCHAR(45) NULL DEFAULT '' COMMENT 'uid reference' COLLATE 'utf8_unicode_ci',
          `status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'payment status' COLLATE 'utf8_unicode_ci',
          `cash` CHAR(15) NOT NULL COMMENT 'payment account reference' COLLATE 'utf8_unicode_ci',
          `pdate` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'payment date' COLLATE 'utf8_unicode_ci',
          `reconcile` VARCHAR(2) NOT NULL DEFAULT '0' COMMENT 'reconciliation status' COLLATE 'utf8_unicode_ci',
          `attachment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'file attached uri' COLLATE 'utf8_unicode_ci',
          PRIMARY KEY (`id`)
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance expenses table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_expenses_memo` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'memo serial No',
          `category` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '1=internal 2 = purchase, 3=claim,4=advance,5=perso',
          `entity` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'entity claiming',
          `entity_to` VARCHAR(5) NULL DEFAULT NULL COMMENT 'entity paying',
          `client` VARCHAR(45) NULL DEFAULT '' COMMENT 'if client related',
          `pcode` VARCHAR(45) NULL DEFAULT '' COMMENT 'pcode if proejct related',
          `mission` TINYTEXT NULL COMMENT 'a title/description',
          `budget` VARCHAR(45) NULL DEFAULT '' COMMENT 'Y=budgeted,N=not budgeted',
          `refund` VARCHAR(45) NULL DEFAULT '' COMMENT 'action refund',
          `invoice` VARCHAR(45) NULL DEFAULT '' COMMENT 'action invoice',
          `date` VARCHAR(20) NOT NULL DEFAULT '0000-00-00' COMMENT 'date',
          `pdate` VARCHAR(20) NULL DEFAULT '0000-00-00' COMMENT 'payment date',
          `status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'status  0=not paid,1=partial,2=paid',
          `value` DOUBLE UNSIGNED NOT NULL COMMENT 'amount in local currency',
          `currency` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'selected currency',
          `value_base` DOUBLE UNSIGNED NOT NULL COMMENT 'amount in base currency',
          `amount_paid` DOUBLE NULL DEFAULT '0' COMMENT 'amount paid local currency',
          `amount_paid_base` DOUBLE NULL DEFAULT '0' COMMENT 'amount paid base currency',
          `comment` TEXT NULL COMMENT 'comment.',
          `reconcile` TINYINT(4) NOT NULL DEFAULT '0' COMMENT 'reconcile status, 0 not reconciled.',
          `post` TINYINT(2) NOT NULL DEFAULT '0' COMMENT 'post status, 0 =not recorded in expenses, 1 not received.',
          `auth` VARCHAR(10) NOT NULL DEFAULT '0' COMMENT 'authorization setting 0=not required 1=pending 2=auth 3=reject | authorizer.',
          PRIMARY KEY (`id`)
        )
        COMMENT='record of memo'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);

        $query = "CREATE TABLE IF NOT EXISTS `ek_expenses_memo_list` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'main table serial ref',
          `aid` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'aid reference from ek_accounts',
          `description` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'user added description',
          `amount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'line value',
          `value_base` DOUBLE NOT NULL COMMENT 'line value in base currency',
          `receipt` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'recipt reference',
          PRIMARY KEY (`id`)
        )
        COMMENT='details of memo'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";


        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance expenses memo tables installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_expenses_memo_documents` (
          `id` INT(10) NOT NULL AUTO_INCREMENT,
          `serial` VARCHAR(100) NOT NULL DEFAULT '0' COMMENT 'memo serial reference',
          `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'uri of file' COLLATE 'utf8mb4_unicode_ci',
          `doc_date` INT(11) NULL DEFAULT NULL COMMENT 'date uploaded',
          PRIMARY KEY (`id`)
        )
        COMMENT='references for receipts uploaded'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance expenses memo documents table installed <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_journal` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `aid` VARCHAR(10) NOT NULL DEFAULT '0' COMMENT 'account id',
          `count` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'Increment count by coid',
          `exchange` VARCHAR(10) NOT NULL DEFAULT '0' COMMENT 'multi currency exchange ',
          `coid` VARCHAR(10) NOT NULL DEFAULT '0' COMMENT 'company id',
          `type` VARCHAR(10) NOT NULL DEFAULT '0' COMMENT 'Debit / credit',
          `source` VARCHAR(50) NOT NULL DEFAULT '0' COMMENT 'related transactions type',
          `reference` VARCHAR(50) NOT NULL DEFAULT '0' COMMENT 'related transactions id',
          `date` VARCHAR(50) NOT NULL DEFAULT '0' COMMENT 'transaction date',
          `value` DOUBLE NOT NULL DEFAULT '0' COMMENT 'value of transaction',
          `reconcile` VARCHAR(2) NOT NULL DEFAULT '0' COMMENT 'reconcile status 0 = not reconciled 1 = reconciled',
          `currency` VARCHAR(3) NULL DEFAULT NULL COMMENT 'currency code for transaction',
          `comment` VARCHAR(250) NULL DEFAULT NULL COMMENT 'comment',
          PRIMARY KEY (`id`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance journal table installed <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_journal_reco_history` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `date` DATE NOT NULL COMMENT 'date of reconciliation',
          `type` VARCHAR(3) NOT NULL COMMENT 'typ of reconciliation' COLLATE 'utf8_unicode_ci',
          `aid` INT(10) NOT NULL COMMENT 'account id from chart',
          `coid` INT(10) NOT NULL COMMENT 'company id',
          `data` TEXT NOT NULL COMMENT 'serialized data' COLLATE 'utf8_unicode_ci',
          `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'File attachment' COLLATE 'utf8mb4_unicode_ci',
          PRIMARY KEY (`id`)
        )
        COMMENT='record reconciliation reports'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1;";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance reconciliation reports table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_journal_trail` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key: Unique ID.',
            `jid` INT(10) UNSIGNED NOT NULL COMMENT 'Journal primary ID.',
            `username` VARCHAR(255) NULL DEFAULT NULL COMMENT 'User name.',
            `action` SMALLINT(6) NULL DEFAULT NULL COMMENT '1 save, 2 edit, 3 delete',
            `timestamp` INT(11) NOT NULL DEFAULT '0' COMMENT 'Unix timestamp of when event occurred.',
            PRIMARY KEY (`id`)
        )
        COMMENT='Stores user history per journal entry.'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Finance journal trail installed<br/>';
        }
        
        $query = "CREATE TABLE IF NOT EXISTS `ek_yearly_budget` (
            `reference` VARCHAR(25) NOT NULL COMMENT 'account-country-year-month' COLLATE 'utf8_unicode_ci',
            `value_base` DOUBLE UNSIGNED NULL DEFAULT NULL COMMENT 'budget value in base currency',
            UNIQUE INDEX `Index 1` (`reference`)
        )
        COMMENT='Budget data'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ;";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Budget table installed<br/>';
        }
        
        $query = "CREATE TABLE IF NOT EXISTS `ek_address_book_bank` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `abid` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'Address book id',
            `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
            `address1` VARCHAR(255) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `address2` VARCHAR(255) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `postcode` VARCHAR(45) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `country` VARCHAR(45) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `account` VARCHAR(45) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `swift` VARCHAR(45) NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
            `bank_code` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Bank code' COLLATE 'utf8mb4_unicode_ci',
            PRIMARY KEY (`id`)
            )
            COMMENT='Bank entities for AB'
            COLLATE='utf8mb4_unicode_ci'
            ENGINE=InnoDB
        ;";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'AB bank table installed<br/>';
        }
        

        $link = Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));

        return array(
            '#title' => $this->t('Installation of Ek_finance module'),
            '#markup' => $markup
                );
    }
}
