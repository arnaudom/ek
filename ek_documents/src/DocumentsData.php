<?php

namespace Drupal\ek_documents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;

/*
*/
/**
 * Interface for documents lists and data
 *
 * 
 */
 class DocumentsData {
 
  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Database
   */
  protected $database;
  
   /**
   * Constructs.
   *
   * 
   */
  public function __construct(Database $database) {
    $this->appdata = $database->getConnection('external_db', 'external_db');
    
    
  }   
 
   /**
   * return an array own documents
   *
   * @param filter = 'any' 'key' or 'date' 
   * @param from, to, name: to be use for search by date or name implementation (TO DO0
   *
   */ 
  public static function my_documents($filter = NULL, $from = NULL, $to = NULL, $name = NULL, $order = 'filename') {
  
  $uid = \Drupal::currentUser()->id();
  $my = array();
    
  $query = "SELECT DISTINCT folder FROM {ek_documents} WHERE uid=:uid order by folder";
  $a=array(':uid' => $uid);
  $folders = Database::getConnection('external_db', 'external_db')->query($query, $a);
  
    while($f = $folders->fetchObject()) {
    
    $folderarrays= array(); 
      switch($filter) {
      
        case 'any':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND folder=:f order by :o";
        $a=array(':uid' => $uid, ':f' => $f->folder, ':o' => $order);

        
        break;
      
        case 'key':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid and filename like :name and folder=:f order by :o";
        $a=array(':uid' => $uid, ':name' => "%$name%", ':f' => $f->folder, ':o' => $order);
               
        break;  
        
        case 'date':

        $d1 = strtotime($from);
        $d2 = strtotime($to);
        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND date>=:d1 AND date<=:d2 AND folder=:f order by :o";
        $a = array(':uid' => $uid, ':d1' => $d1, ':d2' => $d2, ':f' => $f->folder, ':o' => $order);
        break;    
      
      }

    
    $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
    
             while ($l = $list->fetchObject()) {
                 
                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = array_pop($extension);

                $icon_path = drupal_get_path('module', 'ek_documents') . '/art/' . $extension . ".png";
                $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/' . $extension . ".png";
            
                if (!file_exists($icon_path)) {
                    $icon_path = drupal_get_path('module', 'ek_documents') . '/art/no.png';
                } 
                if(!file_exists($icon_path_small)){
                    $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/no.png';
                }
                               
                $thisarray = array('id' => $l->id,
                                'fid' => $l->fid, //reserved for option usage of file_managed table
                                'uid' => $l->uid,
                                'filename' => $l->filename,
                                'doc_name' => $doc_name,
                                'doc_name_short' => $doc_short_name, 
                                'extension' => $extension,
                                'icon_path' => $icon_path,
                                'icon_path_small' => $icon_path_small,
                                'uri' => $l->uri,
                                'url' => file_create_url($l->uri),
                                'type' => $l->type,
                                'comment' => $l->comment,
                                'timestamp' => $l->date,
                                'date' => date("Y-m-d", $l->date),
                                'date_full' => date('D, j M. Y', $l->date),
                                'size' => round($l->size/1000,2),
                                'share' => $l->share,
                                'share_uid' => $l->share_uid,
                                'share_gid' => $l->share_gid,
                                'expire' => $l->expire,
                                'content' => 'myDocs'
                                );
                array_push($folderarrays, $thisarray);
            }
            
            array_push($my, array($f->folder => $folderarrays));
              
    
    
    } //loop folders
  

  return $my;
  
  
  
  }
 
 
 
   /**
   * return an array of shared documents
   *
   * @param filter = 'any' 'key' or 'date' 
   * @param from, to, name: to be use for search by date or name implementation (TO DO0
   */ 
  public static function shared_documents( $filter = NULL, $from = NULL, $to = NULL, $name = NULL, $order = 'filename') {
  
  $uid = \Drupal::currentUser()->id();
  $share = array();
    
  $query = "SELECT DISTINCT uid FROM {ek_documents} WHERE uid<>:uid AND share > :sh order by uid";
  $a=array(':uid' => $uid, ':sh' => 0);
  $folders = Database::getConnection('external_db', 'external_db')->query($query, $a);
  
    while($f = $folders->fetchObject()) {
    
    $folder_name = db_query('SELECT name FROM {users_field_data} WHERE uid=:u', array(':u' => $f->uid))->fetchField();
    $folderarrays= array(); 
      switch($filter) {
      
        case 'any':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND share > :sh AND share_uid like :s order by :o";
        $a=array(':uid' => $f->uid, ':sh' => 0, ':s' => '%,' . $uid . ',%' , ':o' => $order);

        
        break;
      
        case 'key':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND share > :sh ANd filename like :name AND share_uid like :s order by :o";
        $a=array(':uid' => $uid, ':sh' => 0, ':name' => "%$name%", ':s' => '%,' . $uid . ',%' , ':o' => $order);
               
        break;  
        
        case 'date':

        $d1 = strtotime($from);
        $d2 = strtotime($to);
        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND share > :sh AND date>=:d1 AND date<=:d2 AND share_uid like :s order by :o";
        $a = array(':uid' => $uid, ':sh' => 0, ':d1' => $d1, ':d2' => $d2, ':s' => '%,' . $uid . ',%' , ':o' => $order);
        break;    
      
      }

    
    $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
    
            while ($l=$list->fetchObject()) {
                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = array_pop($extension);
                    $icon_path = drupal_get_path('module', 'ek_documents') . '/art/' . $extension . ".png";
                    $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/' . $extension . ".png";

                    if (!file_exists($icon_path)) {
                        $icon_path = drupal_get_path('module', 'ek_documents') . '/art/no.png';
                    } 
                    if(!file_exists($icon_path_small)){
                        $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/no.png';
                    }

                $thisarray = array('id' => $l->id,
                                    'fid' => $l->fid, //reserved for option usage of file_managed table
                                    'uid' => $l->uid,
                                    'filename' => $l->filename,
                                    'doc_name' => $doc_name,
                                    'doc_name_short' => $doc_short_name, 
                                    'extension' => $extension,
                                    'icon_path' => $icon_path,
                                    'icon_path_small' => $icon_path_small,
                                    'uri' => $l->uri,
                                    'url' => file_create_url($l->uri),
                                    'type' => $l->type,
                                    'comment' => $l->comment,
                                    'timestamp' => $l->date,
                                    'date' => date("Y-m-d", $l->date),
                                    'date_full' => date('D, j M. Y', $l->date),
                                    'size' => round($l->size/1000,2),
                                    'share' => $l->share,
                                    'share_uid' => $l->share_uid,
                                    'share_gid' => $l->share_gid,
                                    'expire' => $l->expire,
                                    'content' => 'sharedDocs'
                                    );
                array_push($folderarrays, $thisarray);
            }
            
            if(!empty($folderarrays)) {
            array_push($share, array($folder_name => $folderarrays));
            }
              
    

     } //loop folders 

  return $share;
  
  
  
  }//shared 
 
   /**
   * return an array of common documents
   *
   * @param filter = 'any' 'key' or 'date' 
   * @param from, to, name: to be use for search by date or name implementation (TO DO)
   */ 
  public static function common_documents( $filter = NULL, $from = NULL, $to = NULL, $name = NULL, $order = 'filename') {
  
  $uid = 0;
  $common = array();
  $manage = 0;
    
  $query = "SELECT DISTINCT folder FROM {ek_documents} WHERE uid=:uid order by folder";
  $a=array(':uid' => $uid);
  $folders = Database::getConnection('external_db', 'external_db')->query($query, $a);
  
    while($f = $folders->fetchObject()) {
    
    $folderarrays= array(); 
      switch($filter) {
      
        case 'any':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND folder=:f order by :o";
        $a=array(':uid' => $uid, ':f' => $f->folder, ':o' => $order);

        
        break;
      
        case 'key':

        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid and filename like :name and folder=:f order by :o";
        $a=array(':uid' => $uid, ':name' => "%$name%", ':f' => $f->folder, ':o' => $order);
               
        break;  
        
        case 'date':

        $d1 = strtotime($from);
        $d2 = strtotime($to);
        $query = "SELECT * FROM {ek_documents} WHERE uid=:uid AND date>=:d1 AND date<=:d2 AND folder=:f order by :o";
        $a = array(':uid' => $uid, ':d1' => $d1, ':d2' => $d2, ':f' => $f->folder, ':o' => $order);
        break;    
      
      }

    
    $list = Database::getConnection('external_db', 'external_db')->query($query, $a);
    if(\Drupal::currentUser()->hasPermission('manage_common_documents')){ 
        $manage = 1;
    }
    
            while ($l=$list->fetchObject()) {
            
                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = array_pop($extension);
                $icon_path = drupal_get_path('module', 'ek_documents') . '/art/' . $extension . ".png";
                $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/' . $extension . ".png";

                if (!file_exists($icon_path)) {
                    $icon_path = drupal_get_path('module', 'ek_documents') . '/art/no.png';
                } 
                if(!file_exists($icon_path_small)){
                    $icon_path_small = drupal_get_path('module', 'ek_documents') . '/art/ico/no.png';
                }


                $thisarray = array('id' => $l->id,
                                    'fid' => $l->fid, //reserved for option usage of file_managed table
                                    'uid' => $l->uid,
                                    'filename' => $l->filename,
                                    'doc_name' => $doc_name,
                                    'doc_name_short' => $doc_short_name, 
                                    'extension' => $extension,
                                    'icon_path' => $icon_path,
                                    'icon_path_small' => $icon_path_small,
                                    'uri' => $l->uri,
                                    'url' => file_create_url($l->uri),
                                    'type' => $l->type,
                                    'comment' => $l->comment,
                                    'timestamp' => $l->date,
                                    'date' => date("Y-m-d", $l->date),
                                    'date_full' => date('D, j M. Y', $l->date),
                                    'size' => round($l->size/1000,2),
                                    'share' => $l->share,
                                    'share_uid' => $l->share_uid,
                                    'share_gid' => $l->share_gid,
                                    'expire' => $l->expire,
                                    'content' => 'commonDocs',
                                    'manage' => $manage
                                    );
                array_push($folderarrays, $thisarray);
            }
            
            array_push($common, array($f->folder => $folderarrays));
              
    
    
    } //loop folders
  


  return $common;
  
  
  
  }//shared  
 
   /**
   * return true if document is owned by current user
   * 
   * 
   */ 
   
   public static function validate_owner( $id ) {
   
    $query = 'SELECT uid from {ek_documents} WHERE id=:id';
    $uid = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
    $del = FALSE;
    if ($uid == \Drupal::currentUser()->id()) {
        $del = TRUE;
    } elseif($uid == '0' && \Drupal::currentUser()->hasPermission('manage_common_documents')) {
        $del = TRUE;
    }
    
    return $del;
   
   }
 


 
 
 } // class