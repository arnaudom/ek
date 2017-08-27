<?php

/**
 * @file
 * Contains \Drupal\yourmodule\Plugin\Block\TrackBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a display for user action in project page' .
 *
 * @Block(
 *   id = "track_project_block",
 *   admin_label = @Translation("User interaction projects"),
 *   category = @Translation("Ek projects Widgets")
 * )
 */
class TrackBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
    public function build() {

        $sound = '../../' . drupal_get_path('module', 'ek_projects') . '/art/beep.wav';

        return array(
            '#items' => ['title' => t('Users activity'), 'link' => $sound],
            '#theme' => 'activity_block',
            '#attached' => array(
                'library' => array('ek_projects/ek_projects_css', 'ek_projects/ek_projects_updater'),
            ),
        );
    }


  /**
   * {@inheritdoc}
   */
    protected function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous()) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
