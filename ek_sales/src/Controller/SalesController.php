<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\SalesController.
 */

namespace Drupal\ek_sales\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\Journal;


/**
 * Controller routines for ek module routes.
 */
class SalesController extends ControllerBase {
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
     * Return main 
     *
     */
    public function ManageSales(Request $request) {

        return array('#markup' => '');
    }

    /**
     * Return sales data page
     * data by address book entry
     *
     */
    public function DataSales(Request $request, $abid) {

        $items = array();
        $query = "SELECT name,c.comment from {ek_address_book} a LEFT JOIN {ek_address_book_comment} c "
                . "ON a.id=c.abid where id=:id";
        $ab = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $abid))
                ->fetchObject();
        $items['name'] = $ab->name;
        $items['link'] = Url::fromRoute('ek_address_book.view', array('id' => $abid))->toString();

        //upload form for documents
        $items['form'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\UploadForm', $abid);

        //comments
        $items['comment'] = html_entity_decode($ab->comment, ENT_QUOTES, "utf-8");
        $param_edit = 'comment|' . $abid . '|address_book|50%';
        $link = Url::fromRoute('ek_sales_modal', ['param' => $param_edit])->toString();
        $items['edit_comment'] = t('<a href="@url" class="@c"  >[ edit ]</a>', array('@url' => $link, '@c' => 'use-ajax red '));


        //projects linked
        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $query = "SELECT p.id,p.status,date,pcode,pname,level,priority,cid,last_modified,c.name
                FROM {ek_project} p
                INNER JOIN {ek_country} c
                ON p.cid=c.id
                 WHERE client_id= :abid
                 order by date";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':abid' => $abid));
            $items['projects'] = array();
            while ($d = $data->fetchObject()) {

                $items['projects'][] = array(
                    'link' => ProjectData::geturl($d->id),
                    'pcode' => $d->pcode,
                    'pname' => $d->pname,
                    'date' => $d->date,
                    'last_modified' => date('Y-m-d', $d->last_modified),
                    'country' => $d->name,
                    'status' => $d->status,
                    'level' => $d->level,
                    'priority' => $d->priority,
                );
            }
        }
        //reports
        if ($this->moduleHandler->moduleExists('ek_intelligence')) {
            $query = "SELECT id,serial,edit FROM {ek_ireports} WHERE abid=:c";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => $abid));
            $items['reports'] = array();
            while ($d = $data->fetchObject()) {
                $link = Url::fromRoute('ek_intelligence.read', ['id' => $d->id])->toString();
                $items['reports'][] = array(
                    'serial' => '<a href="' . $link . '">' . $d->serial . '</a>',
                    'edit' => date('Y-m-d', $d->edit),
                );
            }
        }

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            //statistics cases
            $query = "SELECT count(pcode) as sum, status FROM {ek_project}"
                    . " WHERE client_id=:abid group by status";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':abid' => $abid));
            $total = 0;
            $items['category_statistics'] = array();
            while ($d = $data->fetchObject()) {
                $total += $d->sum;
                $items['category_statistics'][$d->status] = $d->sum;
            }
            $items['category_statistics']['total'] = $total;

            $items['category_year_statistics'] = array();
            $query = "SELECT id,type FROM {ek_project_type}";
            $type = Database::getConnection('external_db', 'external_db')
                            ->query($query)->fetchAllKeyed();

            for ($y = date('Y') - 4; $y <= date('Y'); $y++) {
                $total = 0;
                $query = "SELECT count(pcode) as sum, category FROM {ek_project} WHERE "
                        . "client_id=:abid ANd date like :d group by category";

                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':abid' => $abid, ':d' => $y . '%'));

                $items['category_year_statistics'][$y] = array();
                while ($d = $data->fetchObject()) {
                    $items['category_year_statistics'][$y][$type[$d->category]] = $d->sum;
                }
            }
        }
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            //statistics finance
            $settings = new FinanceSettings();
            $items['baseCurrency'] = $settings->get('baseCurrency');
            $query = "SELECT sum(totalbase) FROM {ek_invoice_details} d "
                    . "INNER JOIN {ek_invoice} i ON d.serial=i.serial "
                    . "WHERE i.client=:abid ";
            $a = array(
                ':abid' => $abid,
            );
            $items['total_income'] = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();

            $query = "SELECT date,pay_date FROM {ek_invoice} "
                    . "WHERE client = :abid and status=:s";

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':abid' => $abid, ':s' => 1));

            $af = array();

            while ($d = $data->fetchObject()) {
                $long = round((strtotime($d->pay_date) - strtotime($d->date)) / (24 * 60 * 60), 0);
                array_push($af, $long);
            }
            $items['payment_performance'] = array(
                'max' => max($af),
                'min' => min($af),
                'avg' => round((array_sum($af) / count($af)), 1)
            );
        }

        return array(
            '#items' => $items,
            '#title' => t('Sales data'),
            '#theme' => 'ek_sales_data',
            '#attached' => array(
                'drupalSettings' => array('abid' => $abid),
                'library' => array('ek_sales/ek_sales_docs_updater'),
            ),
        );
    }

    /**
     * Return data called to update documents for sales data
     *
     */
    public function load(Request $request) {

        $query = "SELECT * FROM {ek_sales_documents} WHERE abid = :c order by id";
        $list = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':c' => $request->get('abid')));


        //build list of documents
        $t = '';
        $i = 0;
        if (isset($list)) {
            while ($l = $list->fetchObject()) {
                $i++;
                $share = explode(',', $l->share);
                $deny = explode(',', $l->deny);

                if ($l->share == '0' || ( in_array(\Drupal::currentUser()->id(), $share) && !in_array(\Drupal::currentUser()->id(), $deny) )) {

                    $extension = explode(".", $l->filename);
                    $extension = array_pop($extension);
                    $image = 'modules/ek_sales/art/icons/' . $extension . ".png";
                    $uri = $l->uri;

                    if (file_exists($image)) {
                        $image = "<IMG src='../../$image'>";
                    } else {
                        $image = "<IMG src='../../modules/ek_sales/art/icons/file.png'>";
                    }

                    if ($l->fid == '0') { //file was deleted
                        $del_button = '[-]';
                        $doc = $l->filename;
                        $mail_button = '';
                    } else {
                        if (file_exists(file_uri_target($uri))) {
                            //file not on server (archived?) TODO ERROR file path not detected
                            $del_button = '';
                            $l->comment = t('Document not available. Please contact administrator');
                            $doc = $l->filename;
                            $mail_button = '';
                        } else {
                            //file exist

                            $route = Url::fromRoute('ek_sales_delete_file', array('id' => $l->id))->toString();
                            $del_button = "<a id='d$i' href='" . $route . "' class='use-ajax red' title='" . t('delete the file') . "'>[x]</a>";
                            $doc = "<a href='" . file_create_url($uri) . "' target='_blank' >" . $l->filename . "</a>";
                        }
                    }


                    if ($l->fid != '0') {

                        $param_access = 'access|' . $l->id . '|sales_doc';
                        $link = Url::fromRoute('ek_sales_modal', ['param' => $param_access])->toString();
                        $share_button = t('<a href="@url" class="@c"  >[ access ]</a>', array('@url' => $link, '@c' => 'use-ajax red '));
                    } else {
                        $share_button = '';
                    }
                    if ($l->fid != '0') {
                        //show only non deleted files. keep option to display deleted one( remove condition above)
                        $t .= "<tr id='sd" . $l->id . "'>
                          <td>" . $image . " " . $doc . "</td>
                          <td>" . date('Y-m-d', $l->date) . "</td>
                          <td>" . $l->comment . "</td>
                          <td>" . $del_button . "</td>
                          <td>" . $share_button . "</td>

                      </tr>
                      
                      ";
                    }
                } //in array
            }
        }
        return new JsonResponse(array('list' => $t));
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function modal($param) {
        return $this->dialog(TRUE, $param);
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function nonModal($param) {
        return $this->dialog(FALSE, $param);
    }

    /**
     * Util to render dialog in ajax callback.
     *
     * @param bool $is_modal
     *   (optional) TRUE if modal, FALSE if plain dialog. Defaults to FALSE.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An ajax response object.
     */
    protected function dialog($is_modal = FALSE, $param = NULL) {

        $param = explode('|', $param);
        $content = '';
        switch ($param[0]) {

            case 'access' :
                $id = $param[1];
                $type = $param[2];
                $content = $this->formBuilder->getForm('Drupal\ek_sales\Form\DocAccessEdit', $id, $type);
                $options = array('width' => '30%',);
                break;

            case 'comment' :
                $content = $this->formBuilder->getForm('Drupal\ek_sales\Form\SalesFieldEdit', $param[1], 'comment');
                $options = array('width' => $param[3],);

                break;
            
            case 'invoice':
                $id = $param[1];
                $options = array('width' => '30%',);
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
                $query = 'SELECT currency,amount,amountreceived,pay_date,amountbase,balancebase,taxvalue '
                        . 'FROM {ek_invoice} WHERE id=:id';
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchObject();
                $gross = $data->amount + (round($data->amount * $data->taxvalue / 100, 2));
                $bal = $gross - $data->amountreceived;
                $base = $data->amountbase - $data->balancebase;

                $content['#markup'] = "<table>"
                        . "<tbody>"
                        . "<tr>"
                        . "<td>" . t('Receivable') . "</td><td>" . $data->currency . " " . number_format($gross, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Received') . "</td><td>" . $data->currency . " " . number_format($data->amountreceived, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Balance') . "</td><td>" . $data->currency . " " . number_format($bal, 2) . "<td>"
                        . "</tbody></table><br/>";

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    //extract journal transactions;
                    $journal = new Journal();
                    $content['#markup'].= $journal->entity_history(array('entity' => 'invoice', 'id' => $id));
                }
                break;
            
            case 'purchase':
                $id = $param[1];
                $options = array('width' => '30%',);
                $settings = new FinanceSettings();
                $baseCurrency = $settings->get('baseCurrency');
                $query = 'SELECT currency,amount,amountpaid,pdate,amountbc,balancebc,taxvalue '
                        . 'FROM {ek_purchase} WHERE id=:id';
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchObject();
                $gross = $data->amount + (round($data->amount * $data->taxvalue / 100, 2));
                $bal = $gross - $data->amountpaid;
                $base = $data->amountbc - $data->balancebc;

                $content['#markup'] = "<table>"
                        . "<tbody>"
                        . "<tr>"
                        . "<td>" . t('Payable') . "</td><td>" . $data->currency . " " . number_format($gross, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Paid') . "</td><td>" . $data->currency . " " . number_format($data->amountpaid, 2) . "<td>"
                        . "</tr>"
                        . "<tr>"
                        . "<td>" . t('Balance') . "</td><td>" . $data->currency . " " . number_format($bal, 2) . "<td>"
                        . "</tbody></table><br/>";

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    //extract journal transactions;
                    $content['#markup'].= Journal::entity_history(array('entity' => 'purchase', 'id' => $id));
                }
                break;                
                
                
                
        }

        $response = new AjaxResponse();
        $title = ucfirst($this->t($param[0]));
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';


        if ($is_modal) {
            $dialog = new OpenModalDialogCommand($title, $content, $options);
            $response->addCommand($dialog);
        } else {
            $selector = '#ajax-text-dialog-wrapper-1';
            $response->addCommand(new OpenDialogCommand($selector, $title, $content));
        }
        return $response;
    }

    /**
     * delete a file from list 
     *
     */
    public function deletefile(Request $request, $id) {

        //$id = $request->query->get('id');
        $p = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_sales_documents} WHERE id=:f", array(':f' => $id))
                ->fetchObject();
        $fields = array(
            'uri' => date('U'),
            'fid' => 0,
            'comment' => t('deleted by') . ' ' . \Drupal::currentUser()->getUsername(),
            'date' => time()
        );
        $delete = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_documents')->fields($fields)->condition('id', $id)
                ->execute();
        if ($delete) {

            $query = "SELECT * FROM {file_managed} WHERE uri=:u";
            $file = db_query($query, [':u' => $p->uri])->fetchObject();
            file_delete($file->fid);
        }


        $log = $p->pcode . '|' . \Drupal::currentUser()->id() . '|delete|' . $p->filename;
        \Drupal::logger('ek_sales')->notice($log);

        $response = new AjaxResponse();
        $response->addCommand(new RemoveCommand('#sd' . $id));
        return $response;
    }

    /**
     * Return ajax user autocomplete data
     *
     */
    public function userautocomplete(Request $request) {

        $text = $request->query->get('term');
        $name = array();

        $query = "SELECT distinct name from {users_field_data} WHERE mail like :t1 or name like :t2 ";
        $a = array(':t1' => "$text%", ':t2' => "$text%");
        $name = db_query($query, $a)->fetchCol();

        return new JsonResponse($name);
    }

    /**
     * @retun
     *  a from to reset a payment
     * @param $doc = document key i.e invoice|purchase
     * @param $id = id of doc
     *
     */
    public function ResetPayment($doc, $id) {
        
        
        $build['reset_pay'] = $this->formBuilder->getForm('Drupal\ek_sales\Form\ResetPay', $doc, $id);

        return $build;
    }
//end class  
}
