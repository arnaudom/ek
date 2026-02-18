<?php

namespace Drupal\ek_finance;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;

/**
 * generate accounts id arrays
 *
 *
 *  An associative array containing:
 *  - aid aname by coid, class
 *  - class + header list by company /  entity id
 *
 */
class AidList {

    /**
     * list accounts id from chart of accounts by class -> detail
     *
     * used in selection lists / forms
     * @param $coid = company / entity id
     * @param $type = header, class, detail
     * @param $status 1 or 0
     */
    public static function listaid($coid = null, $type = array(), $status = null) {
       
        $db = Database::getConnection('external_db', 'external_db');

        // 1) Get classes
        $query = $db->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->distinct();
        $query->condition('atype', 'class', '=');
        $query->condition('coid', $coid, '=');

        if ($status !== null) {
            $query->condition('astatus', 1, '=');
        }

        if (empty($type)) {
            $query->condition('aid', '%', 'like');
        } else {
            $or = $query->orConditionGroup();
            foreach ($type as $t) {
                $or->condition('aid', $t . '%', 'like');
            }
            $query->condition($or);
        }

        $query->orderBy('aid');
        $classes = $query->execute()->fetchAllAssoc('aid');

        if (empty($classes)) {
            return [];
        }

        // 2) Get all details for those classes in one query
        // Build a single OR condition for all class prefixes
        $detailQuery = $db->select('ek_accounts', 'd');
        $detailQuery->fields('d', ['aid', 'aname']);
        $detailQuery->condition('atype', 'detail', '=');
        $detailQuery->condition('coid', $coid, '=');

        if ($status !== null) {
            $detailQuery->condition('astatus', 1, '=');
        }

        $or = $detailQuery->orConditionGroup();
        foreach ($classes as $aid => $classRow) {
            $classPrefix = substr($aid, 0, 2);
            $or->condition('aid', $classPrefix . '%', 'like');
        }
        $detailQuery->condition($or);
        $detailQuery->orderBy('aid');

        $details = $detailQuery->execute();

        // 3) Group details by class
        $options = [];
        foreach ($classes as $aid => $classRow) {
            $classPrefix = substr($aid, 0, 2);
            $options[$classPrefix . ' ' . $classRow->aname] = [];
        }

        while ($d = $details->fetchObject()) {
            $classPrefix = substr($d->aid, 0, 2);
            // find matching class key
            foreach ($classes as $aid => $classRow) {
                if (substr($aid, 0, 2) === $classPrefix) {
                    $key = $classPrefix . ' ' . $classRow->aname;
                    $options[$key][$d->aid] = $d->aid . ' - ' . $d->aname;
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * list accounts id from chart of acounts by header -> class
     * @param $coid = company / entity id
     * @param $type = header, class, detail
     * @param $status 1 or 0
     */
    public static function listclass($coid = null, $type = array(), $status = null)
    {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->distinct();
        $query->condition('atype', 'header', '=');
        $query->condition('coid', $coid, '=');

        if ($status != null) {
            $query->condition('astatus', 1, '=');
        }

        if (empty($type)) {
            $query->condition('aid', '%', 'like');
        } else {
            $or = $query->orConditionGroup();
            foreach ($type as $t) {
                $or->condition('aid', $t . '%', 'like');
            }
            $query->condition($or);
        }

        $query->orderBy('aid');

        $data = $query->execute();
        $options = array();

        while ($r = $data->fetchObject()) {
            $head = substr($r->aid, 0, 1);

            $query2 = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
            $query2->fields('a', ['aid', 'aname']);
            $query2->condition('atype', 'class', '=');
            $query2->condition('coid', $coid, '=');
            $query2->condition('aid', $head . '%', 'like');

            if ($status != null) {
                $query2->condition('astatus', 1, '=');
            }
            $query2->orderBy('aid');
            $data2 = $query2->execute();

            $list = array();
            while ($r2 = $data2->fetchObject()) {
                $list[$r2->aid] = $r2->aid . ' - ' . $r2->aname;
            }

            $options[$head . ' ' . $r->aname] = $list;
        }

        return $options;
    }

    /**
     * Get aid name
     * @param $coid = company / entity id
     * @param $aid = account
     * @Return string
     */
    public static function aname($coid = null, $aid = null)
    {
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a', ['aname'])
                    ->condition('aid', $aid)
                    ->condition('coid', $coid)
                    ->execute();
        return $query->fetchField();
    }

    /**
     * List of all accounts
     * @param coid
     * @return array [coid][aid][aname]
     */
    public static function chartList($coid = null)
    {
        $list = [];
        if ($coid == null) {
            $query = "SELECT * FROM {ek_accounts} ORDER by coid,aid";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query);
        } else {
            $query = "SELECT * FROM {ek_accounts} WHERE coid=:c ORDER by coid,aid";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid]);
        }
        while ($d = $data->fetchObject()) {
            $list[$d->coid][$d->aid] = $d->aname;
        }

        return $list;
    }
}
