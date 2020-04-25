<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\TaxController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_admin\CompanySettings;

/**
 * Controller routines for ek module routes.
 */
class TaxController extends ControllerBase
{

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

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
                $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a TaxController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  retrieve tax collected and paid per company and period
     * @return array
     *  render Html
     *
     */
    public function report(Request $request)
    {
        $items = array();
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterTax');

        if (isset($_SESSION['taxfilter']['filter']) && $_SESSION['taxfilter']['filter'] == 1) {
            $show = 1;
            $coid = $_SESSION['taxfilter']['coid'];
            $from = $_SESSION['taxfilter']['from'];
            $to = $_SESSION['taxfilter']['to'];
            $settings = new CompanySettings($coid);
            $stax1 = $settings->get('stax_collect');
            $stax2 = $settings->get('stax_deduct');
            $staxc = $settings->get('stax_collect_aid');
            $staxd = $settings->get('stax_deduct_aid');
            $wtaxc = $settings->get('wtax_collect_aid');//not yet implemented
            $wtaxd = $settings->get('wtax_deduct_aid');//not yet implemented

            if ($staxc == '' && $staxd == '' && $wtaxc == '' && $wtaxd == '') {
                $items['alert1'] = t('You do not have any tax account set.');
                $show = 0;
            } elseif ($stax1 == '0' || $stax2 == '0') {
                $show = 0;
                if ($stax1 == '0') {
                    $items['alert2'] = t('Taxes are not collectible.');
                }
                if ($stax2 == '0') {
                    $items['alert3'] = t('Taxes are not deductible.');
                }
            }

            if ($show == 1) {
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
                include_once drupal_get_path('module', 'ek_finance') . '/taxes.inc';

                $param = serialize(
                    array(
                            'coid' => $coid,
                            'from' => $from,
                            'to' => $to,
                            'baseCurrency' => $baseCurrency,
                            'stax1' => $stax1,
                            'stax2' => $stax2,
                            'staxc' => $staxc,
                            'staxd' => $staxd,
                            'wtaxc' => $wtaxc,
                            'wtaxd' => $wtaxd,
                        )
                );
                if ($show == 1) {
                    $excel = Url::fromRoute('ek_finance_tax_excel', array('param' => $param), array())->toString();
                    $items['excel'] = array(
                        '#markup' => "<a href='" . $excel . "' title='". t('Excel download') . "'><span class='ico excel green'/></a>",
                    );


                    $items['table_1'] = $table_1;
                    $items['table_2'] = $table_2;
                }
                
                //verify if localized module is installed and include link to
                //custom forms
                $query = "SELECT code, status FROM {ek_country} c INNER JOIN "
                        . "{ek_company} co ON co.country = c.name "
                        . "WHERE co.id = :coid";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':coid' => $coid])
                        ->fetchObject();
                
                $try_module = "ek_finance_" . strtolower($data->code);
                if (\Drupal::moduleHandler()->moduleExists($try_module)) {
                    $local = Url::fromRoute('ek_finance_tax_' . strtolower($data->code), array('param' => $param), array())->toString();
                    $items['local'] =  array(
                            '#markup' => "<a href='" . $local . "'>" . t('Custom tax forms') . "</a>",
                        );
                }
            }
        }

        return array(
            '#theme' => 'ek_finance_tax',
            '#items' => $items,
            '#attached' => array(
                'library' => array('ek_finance/ek_finance_css', 'ek_finance/ek_finance.dialog','ek_admin/ek_admin_css'),
            ),
        );
    }

    /**
     *  @return file extract tax collected and paid in excel format
     *
     *  @param array $param
     *      serialized array
     *      Keys:   'coid' (int company id), 'from' (string date),
     *              'to' (string date),'baseCurrency' (string code),
     *              'stax1' (int account number), 'stax2' (int account number),
     *              'staxc' (int account number),'staxd' (int account number),
     *              'wtaxc' => (int account number), 'wtaxd' (int account number)
     *
     *  @return Object
     *      PhpExcel object download
     *      or markup if error
     */
    public function exceltax($param)
    {
        $markup = array();
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = t('Excel library not available, please contact administrator.');
        } else {
            include_once drupal_get_path('module', 'ek_finance') . '/excel_tax.inc';
        }
        return ['#markup' => $markup];
    }
}

//class
