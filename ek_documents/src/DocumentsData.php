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
    public static function my_documents($filter = null, $from = null, $to = null, $name = null, $order = 'filename') {
        $uid = \Drupal::currentUser()->id();
        $my = array();

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_documents', 'd');
        $query->fields('d', ['folder']);
        $query->distinct();
        $query->condition('uid', $uid);
        $query->orderBy('folder');
        $folders = $query->execute();
        while ($f = $folders->fetchObject()) {
            $folderarrays = array();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('uid', $uid, '=');
            $query->condition('folder', $f->folder);
            $query->orderBy($order);

            switch ($filter) {

                case 'any':
                    break;

                case 'key':
                    $query->condition('filename', '%' . $name . '%', 'LIKE');
                    break;

                case 'date':
                    $d1 = strtotime($from);
                    $d2 = strtotime($to);
                    $query->condition('date', $d1, '>=');
                    $query->condition('date', $d2, '<=');
                    break;
            }

            $list = $query->execute();

            while ($l = $list->fetchObject()) {
                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = strtolower(array_pop($extension));

                $icon_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/' . $extension . ".png";
                $icon_path_small = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/ico/' . $extension . ".png";

                if (!file_exists($icon_path)) {
                    $extension = 'no';
                }
                if (!file_exists($icon_path_small)) {
                    $extension = 'no';
                }
                $size = 0;
                if (is_numeric($l->size)) {
                    $size = round($l->size / 1000, 2);
                }
                $thisarray = array('id' => $l->id,
                    'fid' => $l->fid, //reserved for option usage of file_managed table
                    'uid' => $l->uid,
                    'filename' => $l->filename,
                    'doc_name' => $doc_name,
                    'doc_name_short' => $doc_short_name,
                    'extension' => $extension,
                    'uri' => $l->uri,
                    'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($l->uri),
                    'type' => $l->type,
                    'comment' => $l->comment,
                    'timestamp' => $l->date,
                    'date' => date("Y-m-d", $l->date),
                    'date_full' => date('D, j M. Y', $l->date),
                    'size' => $size,
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
    public static function shared_documents($filter = null, $from = null, $to = null, $name = null, $order = 'filename') {
        $uid = \Drupal::currentUser()->id();
        $userData = \Drupal::service('user.data');
        $share = array();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_documents', 'd');
        $query->fields('d', ['uid']);
        $query->distinct();
        $query->condition('uid', 0, '<>');
        $query->condition('share', 0, '>');
        $query->orderBy('uid');
        $folders = $query->execute();

        while ($f = $folders->fetchObject()) {
            $account = \Drupal\user\Entity\User::load($f->uid);
            $folder_name = "";
            if ($account) {
                $folder_name = $account->getAccountName();
            }
            $folderarrays = array();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('share', 0, '>');
            $query->orderBy($order);
            switch ($filter) {

                case 'any':
                    $query->condition('uid', $f->uid);
                    $query->condition('share_uid', '%,' . $uid . ',%', 'LIKE');
                    break;

                case 'key':
                    $query->condition('uid', $uid);
                    $query->condition('filename', '%' . $name . '%', 'LIKE');
                    $query->condition('share_uid', '%,' . $uid . ',%', 'LIKE');
                    break;

                case 'date':
                    $d1 = strtotime($from);
                    $d2 = strtotime($to);
                    $query->condition('uid', $uid);
                    $query->condition('date', $d1, '>=');
                    $query->condition('date', $d2, '<=');
                    $query->condition('share_uid', '%,' . $uid . ',%', 'LIKE');
                    break;
            }

            $list = $query->execute();

            while ($l = $list->fetchObject()) {

                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = strtolower(array_pop($extension));
                $icon_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/' . $extension . ".png";
                $icon_path_small = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/ico/' . $extension . ".png";
                $new = 0;
                if (!file_exists($icon_path)) {
                    $icon_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/no.png';
                }
                if (!file_exists($icon_path_small)) {
                    $icon_path_small = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/ico/no.png';
                }
                $size = 0;
                if (is_numeric($l->size)) {
                    $size = round($l->size / 1000, 2);
                }
                if ($userData->get('ek_documents', $uid, $l->id)) {
                    $new = 1;
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
                    'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($l->uri),
                    'type' => $l->type,
                    'comment' => $l->comment,
                    'timestamp' => $l->date,
                    'date' => date("Y-m-d", $l->date),
                    'date_full' => date('D, j M. Y', $l->date),
                    'size' => $size,
                    'share' => $l->share,
                    'share_uid' => $l->share_uid,
                    'share_gid' => $l->share_gid,
                    'expire' => $l->expire,
                    'content' => 'sharedDocs',
                    'new' => $new,
                );
                array_push($folderarrays, $thisarray);
            }

            // clear new document cache alerts
            \Drupal\Core\Cache\Cache::invalidateTags(['new_documents_shared']);

            if (!empty($folderarrays)) {
                array_push($share, array($folder_name => $folderarrays));
            }
        } //loop folders

        return $share;
    }

//shared

    /**
     * return an array of common documents
     *
     * @param filter = 'any' 'key' or 'date'
     * @param from, to, name: to be use for search by date or name implementation (TO DO)
     */
    public static function common_documents($filter = null, $from = null, $to = null, $name = null, $order = 'filename') {
        $uid = 0;
        $common = array();
        $manage = 0;
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_documents', 'd');
        $query->fields('d', ['folder']);
        $query->distinct();
        $query->condition('uid', $uid, '=');
        $query->orderBy('folder');
        $folders = $query->execute();

        while ($f = $folders->fetchObject()) {
            $folderarrays = array();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('uid', $uid);
            $query->condition('folder', $f->folder);
            $query->orderBy($order);

            switch ($filter) {

                case 'any':
                    break;

                case 'key':
                    $query->condition('filename', '%' . $name . '%', 'LIKE');
                    break;

                case 'date':
                    $d1 = strtotime($from);
                    $d2 = strtotime($to);

                    $query->condition('date', $d1, '>=');
                    $query->condition('date', $d2, '<=');
                    break;
            }

            $list = $query->execute();
            if (\Drupal::currentUser()->hasPermission('manage_common_documents')) {
                $manage = 1;
            }

            while ($l = $list->fetchObject()) {
                $extension = explode(".", $l->filename);
                $doc_name = $extension[0];
                $doc_short_name = substr($extension[0], 0, 12) . "...";
                $extension = strtolower(array_pop($extension));
                $icon_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/' . $extension . ".png";
                $icon_path_small = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/ico/' . $extension . ".png";

                if (!file_exists($icon_path)) {
                    $icon_path = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/no.png';
                }
                if (!file_exists($icon_path_small)) {
                    $icon_path_small = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_admin') . '/art/ico/no.png';
                }

                $size = 0;
                if (is_numeric($l->size)) {
                    $size = round($l->size / 1000, 2);
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
                    'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($l->uri),
                    'type' => $l->type,
                    'comment' => $l->comment,
                    'timestamp' => $l->date,
                    'date' => date("Y-m-d", $l->date),
                    'date_full' => date('D, j M. Y', $l->date),
                    'size' => $size,
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
    }

//shared

    /**
     * return true if document is owned by current user
     *
     *
     */
    public static function validate_owner($id) {
        $query = 'SELECT uid from {ek_documents} WHERE id=:id';
        $uid = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
        $del = false;
        if ($uid == \Drupal::currentUser()->id()) {
            $del = true;
        } elseif ($uid == '0' && \Drupal::currentUser()->hasPermission('manage_common_documents')) {
            $del = true;
        }

        return $del;
    }

}
