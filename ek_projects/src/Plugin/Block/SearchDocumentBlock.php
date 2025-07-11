<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\SearchDocumentBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'search document project box widget' .
 *
 * @Block(
 *   id = "ek_project_search_document_block",
 *   admin_label = @Translation("Search projects document"),
 *   category = @Translation("Ek projects block")
 *
 * )
 */
class SearchDocumentBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {
        $items = [];
        $items['content'] = \Drupal::formBuilder()->getForm('Drupal\ek_projects\Form\SearchDocumentProject');
        $items['title'] = $this->t('Search projects by document');
        $items['id'] = 'search_project_document';


        return [
            '#items' => $items,
            '#theme' => 'ek_projects_dashboard',
            '#attached' => [
                'library' => ['ek_projects/ek_projects.dashboard'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous() && $account->hasPermission('view_project')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
