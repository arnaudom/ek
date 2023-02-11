<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\ProjectPostitBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides a personal post it field per project .
 *
 * @Block(
 *   id = "project_postit_block",
 *   admin_label = @Translation("My Post-it note"),
 *   category = @Translation("Ek projects block")
 * )
 */
class ProjectPostitBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
    public function build() {
        $items = array();
        $items['content'] = '';
        $items['title'] = '';
        $items['id'] = 'project_postit';

        
            $path = \Drupal::service('path.current')->getPath();
            $parts = explode('/', $path);
            $id = array_pop($parts);
            $post = [];
           
            $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_project_actionplan', 'a');
            $query->fields('a',['ap_doc']);
            $query->leftJoin('ek_project', 'p', 'p.pcode=a.pcode');
            $query->condition('p.id', $id, '=');
            $data = $query->execute()->fetchField();
            if($data) {
                $post = unserialize($data);
            }
            
            
            $text = isset($post[\Drupal::currentUser()->id()]) ? $post[\Drupal::currentUser()->id()] : '?';
                      
            $list = '<div class="projectpostit" contenteditable="true">';
            $list .= $text;
            $list .= '</div>';
            $items['title'] = $this->t('Post-it');
            $items['content'] = $list;

        return array(
            '#markup' => $list,
            '#title' => $items['title'],
            '#attached' => [],
            '#cache' => [
                'tags' => ['project_postit_block'],
                'max-age' => 0,
            ],
        );
    }


    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('view_project')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }
}
