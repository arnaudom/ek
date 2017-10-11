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

        $path = drupal_get_path('module', 'ek_documents');
        if ($request->get('get') == 'myDocs') {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $my = DocumentsData::my_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $my = DocumentsData::my_documents('date', $from, $to, null);
                }
            } else {
                $my = DocumentsData::my_documents('any');
            }
        } elseif ($request->get('get') == 'sharedDocs') {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $my = DocumentsData::shared_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $my = DocumentsData::shared_documents('date', $from, $to, null);
                }
            } else {
                $my = DocumentsData::shared_documents('any');
            }
        } else {
            if (isset($_SESSION['documentfilter']['filter'])) {
                if ($_SESSION['documentfilter']['keyword'] != '') {
                    //search by keyword
                    $key = $_SESSION['documentfilter']['keyword'];
                    $my = DocumentsData::common_documents('key', null, null, $key);
                } else {
                    $from = $_SESSION['documentfilter']['from'];
                    $to = $_SESSION['documentfilter']['to'];
                    $my = DocumentsData::common_documents('date', $from, $to, null);
                }
            } else {
                $my = DocumentsData::common_documents('any');
            }
        }

        $list_my = '';
        $i = 0;

        if (!isset($_COOKIE["list-type"])) {
            $_COOKIE["list-type"] = '1';
        }

        if ($my) {
            foreach ($my as $key => $data) {

                foreach ($data as $folder => $doc) {
                    $i++;
                    if ($folder == '')
                        $folder = '&nbsp';

                    $list_my .= "<div id='" . $folder . "' class='drop-folder panel panel-default'>
                            <div class='panel-heading'  onclick=\"jQuery('#mdoc_$i').toggle('fast');\">
                                
                                <h4 class='panel-title text-primary'><span class='folder-ico float-left'></span>" . $folder . " <small class='badge float-right' >" . count($doc) . " " . t('document(s)') . "</small></h4>
                                
                            </div>
                          ";
                    $list_my .="<div id='" . $folder . "' class='drop-folder panel-body' >
                            <div class='inner' id='mdoc_$i' >";


                    if ($_COOKIE["list-type"] == '0') {
                        $list_my .="<table class='table'>
                                                          <thhead>                                                    
                                                            <tr>
                                                            <th/>
                                                            <th style='width:50%;'>" . t('Document') . "</th>
                                                            <th  style='width:15%;'>" . t('Date') . "</th>
                                                            <th  style='width:10%;'>" . t('Size') . "</th>
                                                            <th></th>
                                                            <th></th>";
                        if ($this->moduleHandler->moduleExists('ek_projects') && $request->get('get') == 'myDocs') {
                            $list_my .= "<th></th>";
                        }
                        $list_my .="</tr>
                                                            </thead><tbody>";
                    }

                    if ($doc) {
                        foreach ($doc as $d) {


                            $extension = explode(".", $d["filename"]);
                            $ftype = array_pop($extension);
                            $size = round($d["size"] / 1000, 1);
                            $from = "div-" . $d["id"];
                            $id = 'doc_' . $d['id'];
                            if (strlen($extension[0]) > 12) {
                                $doc_name = substr($extension[0], 0, 12) . " ... ";
                            } else {
                                $doc_name = $extension[0];
                            }


                            if ($_COOKIE["list-type"] == '1') {


                                $ico = $path . '/art/' . $ftype . ".png";
                                if (file_exists($ico)) {
                                    $img = "<IMG class='largeico' src='" . $ico . "' >";
                                } else {
                                    $img = "<IMG class='largeico' src='" . $path . "/art/no.png' >";
                                }

                                if ($request->get('get') == 'myDocs') {
                                    $list_my .= "<div id='" . $from . "' class='move'>
                                  <div class='doc'> 
                                  <div class='float-left handle-ico' >
                                        <div class='' title='" . t('drag') . "'></div>
                                  </div>                                   
                                  <a href='documents/delete/" . $d['id'] . "' class='use-ajax'>
                                      <div class='float-right' >
                                        <div id='" . $id . "' class='trash-ico' title='" . t('delete') . "'></div>
                                      </div>
                                  </a>";
                                    if ($this->moduleHandler->moduleExists('ek_projects')) {
                                        $list_my .= "<a href='documents/project/" . $d['id'] . "' class='use-ajax'>
                                      <div class='float-right' >
                                        <div id='" . $id . "' class='post-ico' title='" . t('post to project') . "'></div>
                                      </div>
                                  </a>";
                                    }

                                    if ($d['share_uid'] != '0') {
                                        $ico = 'shared-ico';
                                    } else {
                                        $ico = 'share-ico';
                                    }
                                    $list_my .= "<a href='documents/share/" . $d['id'] . "' class='use-ajax'>
                                      <div class='float-right' '>
                                        <div id='' class='" . $ico . "' title='" . t('share') . "'></div>
                                      </div>
                                   </a>                                  
                                  ";
                                } elseif ($request->get('get') == 'sharedDocs') {
                                    //shared docs
                                    $list_my .= "<div id='" . $from . "' class='move'>
                                  <div class='doc'>
                                    <a href='documents/remove/" . $d['id'] . "' class='use-ajax'>
                                      <div class='float-right' '>
                                        <div id='' class='remove-ico' title='" . t('remove from list') . "'></div>
                                      </div>
                                    </a>
                                    <a href='documents/move/" . $d['id'] . "' class='use-ajax'>
                                      <div class='float-right' >
                                        <div id='" . $id . "' class='move-ico' title='" . t('move to my documents') . "'></div>
                                      </div>
                                    </a>                                  
                                  ";
                                } else {
                                    //common doc
                                    $list_my .= "<div id='" . $from . "' class='move'>
                                  <div class='doc'> 
                                  <div class='float-left handle-ico' >
                                        <div class='' title='" . t('drag') . "'></div>
                                  </div>";
                                  if(\Drupal::currentUser()->hasPermission('manage_common_documents')){
                                    $list_my .= "<a href='documents/delete/" . $d['id'] . "' class='use-ajax'>
                                        <div class='float-right' >
                                          <div id='" . $id . "' class='trash-ico' title='" . t('delete') . "'></div>
                                        </div>
                                    </a>";
                                  }
                                }

                                $list_my .= " <a target='_blank' href='" . $d['url'] . "' >" . $img . "               
                                    <div title='" . date('D, j M. Y', $d["timestamp"]) . ", " . $d['filename'] . "' >" . $doc_name . " (" . $size . " Kb)</div>
                                  </a>
                                </div>
                                </div>";
                            }//end list type 1 = blocksif ($_COOKIE["list-type"]==0)
                            else {

                                if (strlen($d['filename']) > 50) {
                                    $doc_name = substr($d['filename'], 0, 12) . " ... ";
                                } else {
                                    $doc_name = $d['filename'];
                                }
                                $ico = $path . '/art/ico/' . $ftype . ".png";
                                if (file_exists($ico)) {
                                    $img = "<IMG class='smallico' src='" . $ico . "' >";
                                } else {
                                    $img = "<IMG class='smallico' src='" . $path . "/art/ico/file.png' >";
                                }

                                $list_my .= "<tr class='move' id='" . $from . "'>";
                                $list_my .= "<td style=''  class='handle-ico'>" . $img . "</td>";

                                $list_my .="<td><a target='_blank' href='" . $d['url'] . "'>" . $doc_name . "</a></td>
                              
                              <td><h5>" . date('j M. Y', $d["timestamp"]) . "</h5></td>
                              <td class=''><h5>" . $size . " Kb</h5></td>";

                                if ($request->get('get') == 'myDocs') {
                                    if ($d['share_uid'] != '0') {
                                        $ico = t('shared');
                                    } else {
                                        $ico = t('share');
                                    }

                                    $list_my .=" <td class=''><a href='documents/share/" . $d['id'] . "' class='use-ajax'>[" . $ico . "]</a></td>
                                <td class='' ><a href='documents/delete/" . $d['id'] . "' class='use-ajax'>[" . t('delete') . "]</a></td>";

                                    if ($this->moduleHandler->moduleExists('ek_projects')) {
                                        $list_my .=" <td class='' ><a href='documents/project/" . $d['id'] . "' class='use-ajax'>[" . t('post') . "]</a></td>";
                                    }
                                    $list_my .= "</tr>";
                                } elseif ($request->get('get') == 'sharedDocs') {

                                    $list_my .=" <td class='' title='" . t('remove from shared list') . "'><a href='documents/remove/" . $d['id'] . "' class='use-ajax'>[ x " . t('remove') . "]</a></td>
                                <td  title='" . t('move to my documents folder.') . "'><a class='blue' href='documents/move/" . $d['id'] . "' class='use-ajax'>[ + " . t('move') . "]</a></td>";

                                    $list_my .= "</tr>";
                                } else {
                                    if(\Drupal::currentUser()->hasPermission('manage_common_documents')){
                                        $list_my .="<td class='' ><a href='documents/delete/" . $d['id'] . "' class='use-ajax'>[" . t('delete') . "]</a></td>";
                                    } else {
                                        $list_my .= '<td/>';  
                                    }
                                    $list_my .= "<td/></tr>";
                                }
                            }//end list type 0 = list
                        }
                    }//foreach doc
                    if ($_COOKIE["list-type"] == '0') {
                        $list_my .='</tbody></table>';
                    }
                } //foreach folder


                $list_my .= "</div></div></div>";
            }//foreach data   
        }//end if my 
        
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        $response->setData(array('list' => $list_my));
        return $response;
    }

//load

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

        $query = 'SELECT * FROM {ek_documents} WHERE id=:id';
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
        $uri = "private://documents/users/" . \Drupal::currentUser()->id() . '/' . $data->filename;
        $from = drupal_realpath($data->uri);
        $to = drupal_realpath($uri);
        $move = file_unmanaged_copy($from, $to, FILE_EXISTS_RENAME);
        $name = array_pop(explode('/', $move));
        $uri = "private://documents/users/" . \Drupal::currentUser()->id() . '/' . $name;
        $fields = array(
            'uid' => \Drupal::currentUser()->id(),
            //'fid' => '',
            'type' => $data->type,
            'filename' => $name,
            'uri' => $uri,
            'folder' => t('moved from share folder'),
            'comment' => $data->comment,
            'date' => time(),
            'size' => $data->size,
            'share' => 0,
            'share_uid' => 0,
            'share_gid' => 0,
            'expire' => 0
        );

        $insert = Database::getConnection('external_db', 'external_db')->insert('ek_documents')->fields($fields)->execute();
        if($insert){
            self::remove($id);
        } 
        
        //LogicException: The controller must return a response (null given)
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
        $result = Database::getConnection('external_db', 'external_db')->update('ek_documents')
                ->condition('id', $from[1])
                ->fields($fields)
                ->execute();
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
        $content = '';
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