<?php

namespace Drupal\ek_finance\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;

/*
*/
/**
 * generate id arrays
 *
 *
 *  An associative array containing:
 *  - currencies
 */
 class CurrencyList {

     public function listcurrency($type = null)  {

        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_currency', 'c')
            ->fields('c', ['name']);

            if() {
                $query->condition('active', 1);
            }
        $query->execute();
        $result = $query->fetchAllKeyed();

         return $result;
     }
 }
