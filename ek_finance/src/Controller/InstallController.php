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
use Drupal\Component\Utility\Xss;
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
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * used by administrator to migrate data from older versions
     * @return array
     * render Html
     */
    public function migrate() {
        include_once drupal_get_path('module', 'ek_finance') . '/' . 'migrate.php';
        return array('#markup' => $markup);
    }

    /**
     * install required data tables in a separate database
     * @return array
     * render Html
     */
    public function install() {
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
        `coid` VARCHAR(5) NOT NULL COMMENT 'company id' COLLATE 'utf8_unicode_ci',
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
          (1, 'BIF', 'Franc Burundi', 0, NULL, ''),
          (2, 'XAF', 'Franc CFA', 0, NULL, ''),
          (3, 'DJF', 'Franc Djibouti', 0, NULL, ''),
          (4, 'ETB', 'BIRR', 0, NULL, ''),
          (5, 'USD', 'US dollar', 0, NULL, ''),
          (6, 'SCR', 'Roupie Seychelles', 0, NULL, ''),
          (7, 'EUR', 'EURO', 0, NULL, ''),
          (8, 'KES', 'Shilling kenyan', 0, NULL, ''),
          (9, 'ZAR', 'Rand Commercial', 0, NULL, ''),
          (10, 'DZD', 'Dinar Algerian', 0, NULL, ''),
          (11, 'SAR', 'Riyal', 0, NULL, ''),
          (12, 'AUD', 'Dollar Austalian', 0, NULL, ''),
          (13, 'BHD', 'Dinar Bahrein', 0, NULL, ''),
          (14, 'BDT', 'Taka', 0, NULL, ''),
          (15, 'BMD', 'Dollar Bermuda', 0, NULL, ''),
          (16, 'BWP', 'Pula', 0, NULL, ''),
          (17, 'BND', 'Dollar Brunei', 0, NULL, ''),
          (18, 'CAD', 'Dollar Canadien', 0, NULL, ''),
          (19, 'CVE', 'Escudo', 0, NULL, ''),
          (20, 'CYP', 'Livre Cypriote', 0, NULL, ''),
          (21, 'NZD', 'Dollar New Zealand', 0, NULL, ''),
          (22, 'DKK', 'Couronne Danish', 0, NULL, ''),
          (23, 'EGP', 'Livre Egyptian', 0, NULL, ''),
          (24, 'AED', 'Dirham Emirates', 0, NULL, ''),
          (25, 'FJD', 'Dollar Fidji', 0, NULL, ''),
          (26, 'GMD', 'Dalaisie', 0, NULL, ''),
          (27, 'GRD', 'Livre Sterling', 0, NULL, ''),
          (28, 'ISK', 'Couronne Island', 0, NULL, ''),
          (29, 'JPY', 'Yen', 0, NULL, ''),
          (30, 'JOD', 'Dinar Jordanian', 0, NULL, ''),
          (31, 'KWD', 'Dinar Koweit', 0, NULL, ''),
          (32, 'LRD', 'Dollar Liberian', 0, NULL, ''),
          (33, 'LYD', 'Dinar Libyan', 0, NULL, ''),
          (34, 'CHF', 'Franc Swiss', 0, NULL, ''),
          (35, 'LTL', 'Litas', 0, NULL, ''),
          (36, 'HKD', 'Dollar Hong Kong', 0, NULL, ''),
          (37, 'MYR', 'Ringgit', 0, NULL, ''),
          (38, 'MTP', 'Livre Malta', 0, NULL, ''),
          (39, 'MAD', 'Dirha', 0, NULL, ''),
          (40, 'MUR', 'Roupie Ile Maurice', 0, NULL, ''),
          (41, 'MRO', 'Ouguyia', 0, NULL, ''),
          (42, 'NAD', 'Dollar Namibien', 0, NULL, ''),
          (43, 'NOK', 'Couronne Norvegian', 0, NULL, ''),
          (44, 'OMR', 'Rial Omani', 0, NULL, ''),
          (45, 'PGK', 'Kina', 0, NULL, ''),
          (46, 'PHP', 'Peso Philippin', 0, NULL, ''),
          (47, 'QAR', 'Riyal de Qatar', 0, NULL, ''),
          (48, 'VUV', 'Vatu', 0, NULL, ''),
          (49, 'SGD', 'Dollar Singapour', 0, NULL, ''),
          (50, 'LKR', 'Roupie Cingalaise', 0, NULL, ''),
          (51, 'SEK', 'Couronne Swedish', 0, NULL, ''),
          (53, 'SYP', 'Livre Syrienne', 0, NULL, ''),
          (54, 'TWD', 'Dollar Taiwan', 0, NULL, ''),
          (55, 'THB', 'Bath', 0, NULL, ''),
          (56, 'TND', 'Dinar Tunisien', 0, NULL, ''),
          (57, 'CNY', 'Reminbi', 0, NULL, ''),
          (58, 'VND', 'Dong Vietnam', 0, NULL, ''),
          (59, 'IDR', 'Roupia Indonesia', 0, NULL, '')
        ";

            $db = Database::getConnection('external_db', 'external_db')->query($query);
            if ($db)
                $markup .= 'Finance currencies data updated <br/>';
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
          `amount_paid` DOUBLE UNSIGNED NULL DEFAULT NULL COMMENT 'paid amount value',
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
          `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'uri of file',
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

        $link = Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));

        return array(
            '#title' => t('Installation of Ek_finance module'),
            '#markup' => $markup
                );
    }

}