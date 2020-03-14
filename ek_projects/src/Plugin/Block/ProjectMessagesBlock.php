<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\ProjectMessagesBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides a 'list of latest messages linked to projects widget' .
 *
 * @Block(
 *   id = "project_messages_block",
 *   admin_label = @Translation("Project messages"),
 *   category = @Translation("Ek projects block")
 * )
 */
class ProjectMessagesBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
    public function build() {
        
        $items = array();
        $items['content'] = '';
        $items['title'] = '';
        $items['id'] = 'project_messages';

        if(\Drupal::moduleHandler()->moduleExists('ek_messaging')) {
            
            $path = \Drupal::service('path.current')->getPath();
            $parts = explode('/',$path);
            $id = array_pop($parts);
            $query = "SELECT pcode FROM {ek_project} WHERE id=:id";
            $pcode = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))->fetchField();
           
            $query = "SELECT m.id,`subject`,`stamp`,`from_uid`,`to`,`text` "
                    . "FROM {ek_messaging} m "
                    . "INNER JOIN {ek_messaging_text} t ON m.id=t.id "
                    . "WHERE text like :text order by m.id";
        
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':text' => '%' . $pcode . '%'));
                      
            $list = '<ul class="projectMessagesList">';

            while ($d = $data->fetchObject()) {

                $to = explode(',', $d->to);
                if(in_array(\Drupal::currentUser()->id(), $to) 
                        || $d->from_uid == \Drupal::currentUser()->id()) {
                    
                    $from = User::load($d->from_uid);
                    $link = Url::fromRoute('ek_messaging_read', array('id' => $d->id))->toString();
                    $read = "<a href='". $link . "'>" . t('open') . "</a>";
                    $list .= '<li title="'.$from->getAccountName.'" >' 
                            .  substr($d->subject, 0, 20) . ' - ' . date('Y-m-d', $d->stamp) . ' [' . $read . ']</li>';
                    
                }
                
            }

            $list .= '</ul>';
            $items['title'] = t('Messages');
            $items['content'] = $list;        
        
        }



        return array(
            '#markup' => $list,
            '#title' => $items['title'],
            '#attached' => array(),
            '#cache' => [
                'tags' => ['project_messages_block'],
                'max-age' => 0,
            ],
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
