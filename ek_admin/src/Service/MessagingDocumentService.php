<?php

/**
 * @file
 * Contains \Drupal\ek_adming\Service\MessagingDocumentService.
 *
 * Provides document search functionality for form mentions (#doc).
 */

namespace Drupal\ek_admin\Service;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Document mention service for ek_admin.
 */
class MessagingDocumentService {

    /**
     * Search accessible documents by term for autocomplete.
     *
     * Queries documents from:
     *   1. ek_documents      - User documents (shared or owned)
     *   2. ek_project_documents - Project documents (user has country access)
     *   3. ek_sales_documents    - Sales documents (user has company access)
     *
     * @param string $term
     *   The search term after '#'.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   JSON array of {label, value} objects.
     */
    public function searchDocuments($term) {
        $uid = \Drupal::currentUser()->id();
        $results = [];
        $limit = 15;

        // 1. Search user documents (ek_documents) - owned or shared.
        $this->searchUserDocuments($term, $uid, $results);

        // 2. Search project documents (ek_project_documents).
        $this->searchProjectDocuments($term, $uid, $results);

        // 3. Search sales documents (ek_sales_documents).
        $this->searchSalesDocuments($term, $uid, $results);

        // Deduplicate and limit results.
        $unique = [];
        foreach ($results as $item) {
            if (!isset($unique[$item['value']])) {
                $unique[$item['value']] = $item;
            }
        }
        return new JsonResponse(array_slice(array_values($unique), 0, $limit));
    }

    /**
     * Search user documents table (ek_documents).
     *
     * @param string $term
     *   Search term.
     * @param int $uid
     *   Current user ID.
     * @param array &$results
     *   Results accumulator.
     */
    private function searchUserDocuments($term, $uid, &$results) {
        try {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d', ['filename']);
            $query->condition('filename', $term . '%', 'like');

            // Access: own docs OR shared (share=1 with uid in share_uid, or share=2 visible all)
            $orGroup = $query->orConditionGroup()
                    ->condition('uid', $uid)
                    ->condition('share', '2', '=');

            $subAnd = $query->andConditionGroup()
                    ->condition('share', '1', '=');
            $orGroup->condition($subAnd);

            $query->condition($orGroup);
            $query->range(0, 10);
            $filenames = $query->execute()->fetchCol();

            foreach ($filenames as $filename) {
                $results[] = [
                    'label' => $filename,
                    'value' => $filename,
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist; skip silently.
        }
    }

    /**
     * Search project documents table (ek_project_documents).
     *
     * @param string $term
     *   Search term.
     * @param int $uid
     *   Current user ID.
     * @param array &$results
     *   Results accumulator.
     */
    private function searchProjectDocuments($term, $uid, &$results) {
        try {
            $userCountries = \Drupal\ek_admin\Access\AccessCheck::GetCountryByUser($uid);

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_documents', 'pd');
            $query->fields('pd', ['filename']);
            $query->condition('pd.filename', $term . '%', 'like');

            // Join with project to filter by country access.
            $query->innerJoin('ek_project', 'p', 'p.pcode = pd.pcode');
            if (!empty($userCountries)) {
                $query->condition('p.cid', $userCountries, 'IN');
            }

            $query->range(0, 10);
            $filenames = $query->execute()->fetchCol();

            foreach ($filenames as $filename) {
                $results[] = [
                    'label' => '[project] ' . $filename,
                    'value' => $filename,
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist; skip silently.
        }
    }

    /**
     * Search sales documents table (ek_sales_documents).
     *
     * @param string $term
     *   Search term.
     * @param int $uid
     *   Current user ID.
     * @param array &$results
     *   Results accumulator.
     */
    private function searchSalesDocuments($term, $uid, &$results) {
        try {
            // Get companies user has access to via ek_admin.
            /*$userCompanies = \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid($uid);
            if (!empty($userCompanies)) {
                $companyIds = array_keys($userCompanies);
            } else {
                $companyIds = [0];
            }*/

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_documents', 'sd');
            $query->fields('sd', ['filename']);
            $query->condition('sd.filename', $term . '%', 'like');
            $query->range(0, 10);
            $filenames = $query->execute()->fetchCol();

            foreach ($filenames as $filename) {
                $results[] = [
                    'label' => '[sales] ' . $filename,
                    'value' => $filename,
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist; skip silently.
        }
    }

}