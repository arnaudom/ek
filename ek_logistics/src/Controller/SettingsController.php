<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Controller\
 */

namespace Drupal\ek_logistics\Controller;

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
    public function update() {
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_logistics') . '/' . 'update.php';
        return array('#markup' => $markup);
    }

    /**
     * Return settings management form
     *
     */
    public function settings(Request $request) {
        return $this->formBuilder->getForm('Drupal\ek_logistics\Form\Settings');
    }

    /**
     * Return settings forms customization
     * @return array
     */
    public function settingsFormCustomize(Request $request) {
        
        $coids = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $build['settings_forms'] = $this->formBuilder->getForm('Drupal\ek_logistics\Form\SettingsFormCustomize', $coids);
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
        $head->ddate = date('Y-m-d');
        $head->type = 'DOCUMENT';
        $head->po = '123456';
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
        $head->ordered_quantity = 16;
                        
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
        $client->email = 'jdoe@example.com';
        $client_card = new \stdClass();
        $client_card->contact_name = 'John Doe';
        $client_card->salutation = 'Mr.';
        
        // delivery
        $items = [];
        $items[] = [
           'itemcode' => 'code-1',
           'item' => 'Item description',
           'barcode1' => '1234567891012',
           'supplier_code' => 's-code-1',
           'value' => 10,
           'quantity' => 12,
           'unit_measure' => 'Pcs'
            
        ];
        $items[] = [
           'itemcode' => 'code-2',
           'item' => '- Lorem ipsum dolor sit amet, consectetur adipiscing elit 
            a) sed do eiusmod tempor incididunt ut labore et dolore
            b) magna aliqua. Ut enim ad minim veniam, quis nostrud',
           'barcode1' => '1234567891015',
           'supplier_code' => 's-code-2',
           'value' => 25,
           'quantity' => 3,
           'unit_measure' => 'Box'
            
        ];
        $items[] = [
           'itemcode' => 'code-3',
           'item' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
           'barcode1' => '1234567891016',
           'barcode2' => '1234567891017',
           'supplier_code' => 's-code-3',
           'value' => 0.51,
           'quantity' => 1000,
           'unit_measure' => 'Box'
            
        ];
        
        
        $coid = $_SESSION['prev'][key($_SESSION['prev'])]['source'];
        if ($coid == '0') {  
            $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_logistics') . "/" . key($_SESSION['prev']);
        } else {
            $filesystem = \Drupal::service('file_system');
           
            $path = \Drupal\Core\StreamWrapper\PublicStream::basePath() . "/" . key($_SESSION['prev']);
            $filesystem->copy("private://logistics/templates/". $coid . '/pdf/' . key($_SESSION['prev']), $path, 
                    \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE );
            $template = $path ;
        }

        $custom = $_SESSION['prev'][key($_SESSION['prev'])];
        if($custom['feature']['border'] == 1) {
            // force stamp display with border for visual adjustment
            $stamp = 2;
        }

        include_once $template;  
        header('Cache-Control: private');
        header('Content-Type: application/pdf');
        $f = "custom.pdf";
        echo $pdf->Output($f,"I");

        exit ;
        
        return [];
    }
    
}

//class
