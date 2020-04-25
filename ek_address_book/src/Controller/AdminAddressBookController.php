<?php
/**
* @file
* Contains \Drupal\ek\Controller\AdminAddressBookController.
*/
namespace Drupal\ek_address_book\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
* Controller routines for ek module routes.
*/
class AdminAddressBookController extends ControllerBase
{
 
/* The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
    protected $moduleHandler;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
      $container->get('module_handler')
    );
    }

    /**
     * Constructs a  object.
     *
     *   The moduleexist service.
     */
    public function __construct(ModuleHandler $module_handler)
    {
        $this->moduleHandler = $module_handler;
    }


    /**
       * Administrate address book
       *
    */
    public function admin(Request $request, $id = null)
    {
        return array();
    }


    /**
       * export address book
       * @param int $id
       *
    */
    public function export(Request $request, $id = null)
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $response = ['#markup' => t('Excel library not available, please contact administrator.')];
        } else {
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_address_book\Form\ExportAddressBook');
        }
        return $response;
    }

    /**
       * import address book
       * @return file download cvs format
       * @param int $id
       *
    */
    public function import(Request $request, $id = null)
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $response = ['#markup' => t('Excel library not available, please contact administrator.')];
        } else {
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_address_book\Form\ImportAddressBook');
        }
        return $response;
    }
}
