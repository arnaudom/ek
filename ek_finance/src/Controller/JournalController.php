<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\JournalController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_finance\Service\JournalService;

/**
 * Controller routines for ek module routes.
 */
class JournalController extends ControllerBase {

    protected $moduleHandler;
    protected $database;
    protected $formBuilder;
    protected $journal;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), 
                $container->get('form_builder'), 
                $container->get('module_handler'),
                $container->get('ek_finance.journal')
        );
    }

    /**
     * Constructs a JournalController object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(
        Connection $database, 
        FormBuilderInterface $form_builder, 
        ModuleHandler $module_handler,
        JournalService $journal) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
        $this->journal = $journal;
    }

    /**
     *  Display journal entries filter by date and company
     *
     * @return array
     *  render Html
     *
     */
    public function journal(Request $request) {
        $items = [];
        $jid = ($request->query->get('jid')) ? $request->query->get('jid') : null;
        $items['filter_journal'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterJournal', $jid);
        $items['data'] = [];
        $settings = new \Drupal\ek_finance\FinanceSettings();
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;

        //todo filter by module
        $folders = ['general', 'expense', 'receipt', 'payroll', 'invoice', 'pos', 'purchase', 'payment'];

        if (isset($_SESSION['jfilter']['filter']) && $_SESSION['jfilter']['filter'] == 1) {
            if (isset($_SESSION['jfilter']['jid']) && $_SESSION['jfilter']['jid'] != "") {
                //retrieve data by journal id
                $details = $this->journal->journalEntryDetails($_SESSION['jfilter']['jid']);
                $jid = $_SESSION['jfilter']['jid'];

                if ($details['id'] == '') {
                    $items['#markup'] = "<div class='messages messages--warning'>"
                            . $this->t('No data available')
                            . '</div>';
                    return $items;
                }
                if(isset($jid)) { 
                    $link = Url::fromRoute('ek_finance.extract.general_journal', [], ['absolute' => true, 'query' => ['jid' => $jid]])->toString();
                    $items['link'] = "<a href='" . $link . "' title='" . $this->t('Right click copy link') . "'><span class='link'/>link</a>";
                }
                $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
                if (in_array($details['coid'], $access)) {
                    $items['data'] = $this->journal->data_by_jid($_SESSION['jfilter']['jid']);
                    $items['rounding'] = $rounding;
                    return [
                        '#theme' => 'ek_finance_journal_by_id',
                        '#items' => $items,
                        '#attached' => [
                            'library' => ['ek_finance/ek_finance_css', 'ek_finance/ek_finance.journal', 'ek_admin/ek_admin_css'],
                        ],
                    ];
                } else {
                    //no access
                    $query = "SELECT name from {ek_company} WHERE id=:id";
                    $name = Database::getConnection('external_db', 'external_db')
                            ->query($query, [':id' => $details['coid']])
                            ->fetchField();

                    $buil['type'] = 'access';
                    $build['message'] = ['#markup' => $this->t('Denied access for @e to @p', ['@e' => $name, '@p' => \Drupal::currentUser()->getAccountName()])];
                    $items['alert'] = [
                        '#items' => $build,
                        '#theme' => 'ek_admin_message',
                        '#attached' => [
                            'library' => ['ek_admin/ek_admin_css'],
                        ],
                        '#cache' => ['max-age' => 0,],
                    ];
                    return $items;
                }
            } else {
                foreach ($folders as $folder) {
                    $data = array();

                    $data[$folder] = $this->journal->display(
                            [
                                'date1' => $_SESSION['jfilter']['from'],
                                'date2' => $_SESSION['jfilter']['to'],
                                'company' => $_SESSION['jfilter']['coid'],
                                'edit' => 0,
                                'source' => $folder
                            ]
                    );

                    $items['data'] += $data;
                }

                $param = serialize(
                        [
                            'date1' => $_SESSION['jfilter']['from'],
                            'date2' => $_SESSION['jfilter']['to'],
                            'company' => $_SESSION['jfilter']['coid'],
                            'baseCurrency' => $settings->get('baseCurrency'),
                            'rounding' => $rounding
                        ]
                );

                $items['rounding'] = $rounding;
                $excel = Url::fromRoute('ek_finance.extract.excel-journal', array('param' => $param), array())->toString();
                $items['excel'] = "<a href='" . $excel . "' title='" . $this->t('Excel download') . "'><span class='ico excel green'/></a>";
            }
        }
        

        return [
            '#theme' => 'ek_finance_journal',
            '#items' => $items,
            '#attached' => [
                'library' => ['ek_finance/ek_finance_css', 'ek_finance/ek_finance.journal', 'ek_admin/ek_admin_css'],
            ],
        ];
    }

    /**
     * Extract journal in excel format filter by date and company
     *
     * @param string $param
     *  serialized array
     *  keys : date1 (string), date2 (string), company (int, company id)
     * @return Object
     *  PhpExcel object download
     *
     */
    public function exceljournal(Request $request, $param = null) {
        $markup = [];
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $markup = $this->t('Excel library not available, please contact administrator.');
        } else {
            $param = unserialize($param);
            $summary = ($request->query->get('summary')) ? $request->query->get('summary') : null;
            $markup = array();
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/templates/excel_journal.inc';
        }
        return ['#markup' => $markup];
    }

    /**
     * Extract transaction history of given account and period
     *
     * @param array $param
     *  aid = account id
     *  coid = company id
     *  from = from date
     *  to = to date
     * @return array
     *  render Html
     *
     */
    public function history($param) {
        $history = $this->journal->history($param);
        return array(
            '#theme' => 'ek_journal_history',
            '#items' => unserialize($history),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
        );
    }

    /**
     * Generate audit data for journal data records
     *
     * @param string $audit
     *  the data that is audited
     * @return string - array $param
     *  parameter to pass to audit function
     *  render Html
     *
     */
    public function audit($audit, $param) {

        switch ($audit) {
            // param : [i/p]
            case 'currency':
                $audit = $this->journal->audit_currency($param);
                $audit['layout'] = 'currency';
                break;
            case 'chart':
                // param : coid
                $audit = $this->journal->audit_chart($param);
                $audit['layout'] = 'chart';
                $audit['title'] = $this->t('Chart structure in journal');
                break;
            case 'post-year' :
                // param : coid|year
                $audit = $this->journal->audit_newyear($param);
                $audit['layout'] = 'newyear';
                $audit['title'] = $this->t('Post new year report') . " " . explode('-', $param)[1] . " " 
                        . \Drupal\ek_admin\Access\AccessCheck::CompanyList()[explode('-', $param)[0]];
                break;
            
            case 'balancesheet':
            $parts = explode('-', $param);

            // ── Structural check: must have exactly 3 segments ─────────────────
            if (count($parts) !== 3) {
                return [
                    '#theme'  => 'ek_journal_audit',
                    '#items'  => [
                        'layout' => 'balancesheet',
                        'title'  => $this->t('Balance sheet audit'),
                        'error'  => $this->t('Invalid parameters. Expected format: id-year-month.'),
                    ],
                    '#attached' => ['library' => ['ek_finance/ek_finance']],
                ];
            }

            [$coid_raw, $year_raw, $month_raw] = $parts;

            $errors = [];

            // ── coid: must be a positive integer ───────────────────────────────
            $coid = filter_var($coid_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($coid === false) {
                $errors[] = $this->t('Company ID "@v" must be a positive integer.', ['@v' => $coid_raw]);
            }

            // ── year: must be a 4-digit year in a sensible range ───────────────
            $year = filter_var($year_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
            if ($year === false) {
                $errors[] = $this->t('Year "@v" must be a 4-digit year between 2000 and 2100.', ['@v' => $year_raw]);
            }

            // ── month: must be 1–12, normalised to zero-padded string ──────────
            $month_int = filter_var($month_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
            if ($month_int === false) {
                $errors[] = $this->t('Month "@v" must be a number between 1 and 12.', ['@v' => $month_raw]);
            } else {
                $month = str_pad((string) $month_int, 2, '0', STR_PAD_LEFT);
            }

            // ── company access check ────────────────────────────────────────────
            if ($coid !== false) {
                $companies = \Drupal\ek_admin\Access\AccessCheck::CompanyList();
                if (!isset($companies[$coid])) {
                    $errors[] = $this->t('Company ID @coid is not accessible.', ['@coid' => $coid]);
                }
            }

            // ── Return early if any validation failed ───────────────────────────
            if (!empty($errors)) {
                return [
                    '#theme'  => 'ek_journal_audit',
                    '#items'  => [
                        'layout' => 'balancesheet',
                        'title'  => $this->t('Balance sheet audit'),
                        'error'  => $errors,
                    ],
                    '#attached' => ['library' => ['ek_finance/ek_finance']],
                ];
            }

            // ── All clean — call the service ────────────────────────────────────
            $audit = $this->journal->auditBalanceSheet($coid, (string) $year, $month);
            $audit['layout'] = 'balancesheet';
            $audit['title']  = $this->t(
                'Balance sheet audit @year-@month — @company',
                [
                    '@year'    => $year,
                    '@month'   => $month,
                    '@company' => $companies[$coid],
                ]
            );
            break;
        }

        return [
            '#theme' => 'ek_journal_audit',
            '#items' => $audit,
            '#attached' => [
                'library' => ['ek_finance/ek_finance'],
            ],
            '#cache' => ['max-age' => 0,],
        ];
    }

}
