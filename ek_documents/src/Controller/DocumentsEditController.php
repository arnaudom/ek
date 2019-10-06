<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\
 */

namespace Drupal\ek_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_documents\DocumentsData;

/**
 * Controller routines for ek module routes.
 */
class DocumentsEditController extends ControllerBase {
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
     * Return ajax load / display docs
     *
     */
    public function load(Request $request) {


        //verify if the folder structure exist and create
        $dir = "private://documents/users/" . \Drupal::currentUser()->id();
        file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        //load mydocs
        $list = [];
        $path = drupal_get_path('module', 'ek_documents');
        if ($request->get('get') == 'myDocs') {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $data = DocumentsData::my_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $data = DocumentsData::my_documents('date', $from, $to, null);
                }
            } else {
                $data = DocumentsData::my_documents('any');
            }
        } elseif ($request->get('get') == 'sharedDocs') {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $data = DocumentsData::shared_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $data = DocumentsData::shared_documents('date', $from, $to, null);
                }
            } else {
                $data = DocumentsData::shared_documents('any');
            }
        } else {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $data = DocumentsData::common_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $data = DocumentsData::common_documents('date', $from, $to, null);
                }
            } else {
                $data = DocumentsData::common_documents('any');
            }
        }


        if ($data) {  
            $modules = [];
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                
                $modules['project'] = 1;
            }
            
            $template = 'ek_documents_block_view';//default
            if (isset($_COOKIE["list-type"]) && $_COOKIE["list-type"] == 0) {
                $template = 'ek_documents_list_view';
            }
            
            $render = ['#theme' => $template, '#items' => $data, '#modules' => $modules];
            $list =  \Drupal::service('renderer')->render($render);
        } 
        
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        $response->setData(array('list' => $list));
        return $response;
    }


    /**
     * Return ajax upload 
     *
     */
    public function upload(Request $request, $id) {
        return array();
    }

    /**
     * Return ajax delete confirmation alert
     * My documents
     */
    public function delete(Request $request, $id) {

        $query = 'SELECT filename FROM {ek_documents} WHERE id=:id';
        $file = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchField();
        $content = array('content' =>
            array('#markup' =>
                "<div><a href='documents/delete-confirm/" . $id . "' class='use-ajax'>"
                . t('delete') . "</a> " . $file . "</div>")
        );

        $response = new AjaxResponse();

        $title = $this->t('Confirm');
        $content['#attached']['library'][] = 'core/drupal.dialog.ajax';

        $response->addCommand(new OpenModalDialogCommand($title, $content));


        return $response;
    }

    /**
     * Return ajax delete actions after confirmation
     * My documents
     */
    public function deleteConfirmed(Request $request, $id) {

        if (DocumentsData::validate_owner($id)) {
            $query = 'SELECT uri FROM {ek_documents} WHERE id=:id';
            $uri = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchField();

            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_documents')
                    ->condition('id', $id)
                    ->execute();
            
            \Drupal\Core\Cache\Cache::invalidateTags(['common_documents','my_documents','shared_documents']);
            
            if ($delete) {
                $query = "SELECT * FROM {file_managed} WHERE uri=:u";
                $file = db_query($query, [':u' => $uri])->fetchObject();
                    if(!$file->fid){
                        unlink($uri);
                    } else {
                        file_delete($file->fid);
                    }

                $response = new AjaxResponse();
                $response->addCommand(new CloseDialogCommand());
                $response->addCommand(new RemoveCommand('#div-' . $id));
                return $response;
            } else {
               return new Response('', 204); 
            }
        } else {
            return new Response('', 204);
        }
    }

    /**
     * Return ajax remove 
     * Remove shared document from display in list
     */
    public function remove($id) {

        $query = 'SELECT share_uid FROM {ek_documents} WHERE id=:id';
        $share_uid = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
        $uid = ',' . \Drupal::currentUser()->id() . ',';
        $share_uid = str_replace($uid, ',', $share_uid);
        if ($share_uid == ',') {
            $share_uid = 0;
            $share = 0;
        } else {
            $share = 1;
        }
        Database::getConnection('external_db', 'external_db')->update('ek_documents')->fields(array('share' => $share, 'share_uid' => $share_uid))->condition('id', $id)->execute();
        $response = new AjaxResponse();
        $response->addCommand(new RemoveCommand('#div-' . $id));
        return $response;
    }

    /**
     * Return ajax share
     * 
     */
    public function share(Request $request, $id) {

        return $this->dialog(TRUE, 'share|' . $id);
    }

    /**
     * Return ajax move
     * From share to my
     */
    public function move(Request $request, $id) {

        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('id', $id, '=');
        $doc = $query->execute()->fetchObject();
        
        $uri = "private://documents/users/" . \Drupal::currentUser()->id() . '/' . basename($doc->uri);
        $move = \Drupal::service('file_system')->copy($doc->uri, $uri, FILE_EXISTS_RENAME);
        
        $fields = array(
            'uid' => \Drupal::currentUser()->id(),
            //'fid' => '',
            'type' => $doc->type,
            'filename' => $doc->filename,
            'uri' => $move,
            'folder' => t('moved from share folder'),
            'comment' => $doc->comment,
            'date' => time(),
            'size' => $doc->size,
            'share' => 0,
            'share_uid' => 0,
            'share_gid' => 0,
            'expire' => 0
        );

        $insert = Database::getConnection('external_db', 'external_db')->insert('ek_documents')->fields($fields)->execute();
        if($insert){
            self::remove($id);
        } 
        
        
        return new Response('', 204);
    }

    /**
     * Return ajax project post
     * send a document to a project
     */
    public function project(Request $request, $id) {

        return $this->dialog(TRUE, 'project|' . $id);
    }

    /**
     * Return ajax drag & drop
     *
     */
    public function dragdrop(Request $request) {

        $from = explode("-", $request->get('from'));
        $fields = array('folder' => $request->get('to'));
        $result = Database::getConnection('external_db', 'external_db')
                ->update('ek_documents')
                ->condition('id', $from[1])
                ->fields($fields)
                ->execute();
        
        return new Response('', 204);
    }

    /**
     * AJAX callback handler for AjaxTestDialogForm.
     */
    public function modal($param) {
        return $this->dialog(TRUE, $param);
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
        $content = [];
        switch ($param[0]) {

            case 'share':
                $id = $param[1];
                $content = $this->formBuilder->getForm('Drupal\ek_documents\Form\ShareForm', $id);
                $options = array('width' => '40%',);
                break;

            case 'project':
                $id = $param[1];
                $content = $this->formBuilder->getForm('Drupal\ek_documents\Form\PostProject', $id);
                $options = array('width' => '45%',);
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
            $response->addCommand(new OpenDialogCommand($selector, $title, $html));
        }
        return $response;
    }

    /**
     * return folders name autocomplete
     * @param request
     * @return Json response
     */
    public function lookupfolders(Request $request) {
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_documents'); 
        $data = $query
              ->fields('ek_documents', array('folder'))
              ->distinct()
              ->condition('uid', \Drupal::currentUser()->id())
              ->condition('folder', $request->query->get('q') . '%', 'LIKE')
              ->execute()
              ->fetchCol();

        return new JsonResponse($data);
    }

}