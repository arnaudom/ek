<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\SearchBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'search project box widget' .
 *
 * @Block(
 *   id = "ek_project_search_block",
 *   admin_label = @Translation("Search projects"),
 *   category = @Translation("Ek projects block")
 *
 * )
 */
class SearchBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {
        $items = array();
        $items['content'] = \Drupal::formBuilder()->getForm('Drupal\ek_projects\Form\SearchProject');
        $items['title'] = $this->t('Search projects');
        $items['id'] = 'search_project';


        return array(
            '#items' => $items,
            '#theme' => 'ek_projects_dashboard',
            '#attached' => array(
                'library' => array('ek_projects/ek_projects.dashboard'),
            ),
        );
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
