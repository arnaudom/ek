<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Controller\
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;





/**
 * Controller routines for ek module routes.
 */
class SettingsController extends ControllerBase {
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
     * data update
     *
     */
    public function majour() {
        include_once drupal_get_path('module', 'ek_sales') . '/' . 'majour.php';
    }

    /**
     * Return settings global
     * @return array
     */
    public function settings(Request $request) {
        $build['settings_global'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\Settings');
        return $build;
    }

    /**
     * Return settings forms management
     * @return array
     */
    public function settingsForms(Request $request) {
        $build['settings_forms'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SettingsForms');
        return $build;
    }

    /**
     * Return settings forms customization
     * @return array
     */
    public function settingsFormCustomize(Request $request) {
        $build['settings_forms'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SettingsFormCustomize');
        return $build;
    }

    /**
     * Return settings forms customization
     * @return array
     */
    public function settingsFormPreview(Request $request) {
        
        
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_company')
            ->fields('ek_company')
            ->condition('id' , 1);
        $company = $query->execute()->fetchObject();
        
        $head = new \stdClass();
        $head->serial = 'ABC-01-01-123';
        $head->id = '123';
        $head->date = date('Y-m-d');
        $head->pay_date = date('Y-m-d');
        $head->amountreceived = 1234;
        $head->due = 30;
        $head->title = 'DOCUMENT';
        $head->po_no = '123456';
        $head->comment = "Lorem ipsum dolor sit amet, consectetur adipiscing "
                . "elit, sed do eiusmod tempor incididunt ut labore et dolore "
                . "magna aliqua. Ut enim ad minim veniam, quis nostrud "
                . "exercitation ullamco laboris nisi ut aliquip ex ea "
                . "commodo consequat. Duis aute irure dolor in "
                . "reprehenderit in voluptate velit esse cillum "
                . "dolore eu fugiat nulla pariatur.";
        $head->pcode = 'XYZ-01-01-UUU-8';
        $head->currency = 'CUR';
        $head->taxvalue = 5;
        $head->tax = 'Tax';
        $head->status = 0;
        $head->pay_date = date('Y-m-d');
        
        $bank = new \stdClass();
        $bank->name = 'MyBank LLP';
        $bank->address1 = 'address line 1';
        $bank->address1 = 'address line 2';
        $bank->account_ref = '88-8881-188';
        $bank->swift = 'MYB-VVV-XXX';
        $bank->postcode = '112113';
        $bank->country = 'Country';
        
        $client = new \stdClass();
        $client->name = 'CLIENT NAME';
        $client->reg = 'xx-abcs-11';
        $client->address = 'address line 1';
        $client->address2 = 'address line 2';
        $client->state = 'State';
        $client->postcode = '112113';
        $client->city = 'City';
        $client->country = 'Country';
        $client->telephone = '123-123-4567';
        $client->fax = '123-123-4567';
        
        $client_card = new \stdClass();
        $client_card->contact_name = 'John Doe';
        $client_card->salutation = 'Mr.';
        
        // invoice and purchase
        $items = [];
        $items[] = [
           'item' => 'Description line',
           'value' => 0,
           'quantity' => 0,
           'total' => 0,
            
        ];
        $items[] = [
           'item' => 'item 1',
           'value' => '100',
           'quantity' => 1,
           'total' => 100,
           'unit_measure' => 'box',
            
        ];
        $items[] = [
           'item' => 'item 2',
           'value' => '50',
           'quantity' => 1,
           'total' => 50,
           'unit_measure' => 'box',
            
        ];
        $items[] = [
           'item' => '[sub total]',
           'value' => '100',
           'quantity' => 0,
           'total' => 0,
            
        ];
        $items[] = [
           'item' => 'Description line',
           'value' => 0,
           'quantity' => 0,
           'total' => 0,
            
        ];
        $items[] = [
           'item' => '- Lorem ipsum dolor sit amet, consectetur adipiscing elit 
                      a) sed do eiusmod tempor incididunt ut labore et dolore
                      b) magna aliqua. Ut enim ad minim veniam, quis nostrud',
           'value' => 25,
           'quantity' => 2,
           'total' => 50,
           'unit_measure' => '',
            
        ];
        $items['taxable'] = 200;
        $items['taxamount'] = 10;

        // quotation
        if( strstr(key($_SESSION['prev']), 'quotation')) {
            $items['reference'] = 'ABC-01-01-123';
            $items['column_active2'] = 1;
            $items['column_name2'] = 'col 6';
            $items['column_active3'] = 1;
            $items['column_name3'] = 'col 7';
            $items['lines'][] = [
               'item' => 'Description line',
               'value' => 0,
               'quantity' => 0,
               'total' => 0,
            ];
            $items['lines'][] = [
               'item' => 'item 1',
               'value' => 100,
               'unit' => 1,
               'total' => 100,
               'unit_measure' => 'box',
            ];
            $items['lines'][] = [
               'item' => 'item 2',
               'value' => 50,
               'unit' => 1,
               'total' => 50,
               'unit_measure' => 'box',
               'column_2' => 'Lorem ipsum dolor',
               'column_3' => 'sed do eiusmod',
            ];
            $items['lines'][] = [
               'item' => 'sub_total',
               'value' => 0,
               'unit' => 0,
               'total' => 0,
            ];
        }
        $source = $_SESSION['prev'][key($_SESSION['prev'])]['source'];
        if ($source == '0') {  
            $template = drupal_get_path('module', 'ek_sales') . "/" . key($_SESSION['prev']);
        } else {
            $filesystem = \Drupal::service('file_system');
           
            $path = \Drupal\Core\StreamWrapper\PublicStream::basePath() . "/" . key($_SESSION['prev']);
            $filesystem->copy("private://sales/templates/". $source . '/' . key($_SESSION['prev']), $path, 
                    \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE );
            $template = $path ;
        }

        $custom = $_SESSION['prev'][key($_SESSION['prev'])];
        if($custom['feature']['border'] == 1) {
            // force stamp display with border for visual adjustment
            $stamp = 2;
        }
        if($custom['body']['border'] == 1) {
            // force sign display with border for visual adjustment
            $signature = 1;
            $s_pos = 10;
        }
 
        include_once $template;  
        header('Cache-Control: private');
        header('Content-Type: application/pdf');
        $f = "custom.pdf";
        echo $pdf->Output($f,"I");

        exit ;
        
        return [];
    }
    
    /*
     * Return settings serial management form
     * @return array
     *
     */

    public function settingsSerial() {
        $build['settings_forms'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\SerialFormat');
        return $build;
    }

}

