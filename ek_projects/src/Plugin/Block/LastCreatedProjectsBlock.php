<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\LastCreatedProjectsBlock.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a 'list of latest created projects widget' .
 *
 * @Block(
 *   id = "ek_project_last_projects_block",
 *   admin_label = @Translation("Last created projects"),
 *   category = @Translation("Ek projects Widgets")
 * )
 */
class LastCreatedProjectsBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
    public function build() {

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p');
                    $query->leftJoin('ek_country', 'c', 'p.cid=c.id');
                    $query->leftJoin('ek_address_book', 'b', 'p.client_id=b.id');
                    $query->fields('p', array('id', 'pcode', 'pname', 'date', 'notify'));
                    $query->fields('c', array('name'));
                    $query->fields('b', array('name'));
                    $query->range(0,30);
                    $query->orderBy('p.id', 'DESC');
        $data = $query->execute();
       
        $list = '<ul>';

        while ($d = $data->fetchObject()) {
            
            $notify = explode(',', $d->notify);
            if (in_array(\Drupal::currentUser()->id(), $notify)) {
                $cls = "check-square";
                $title = t("Unfollow");
            } else {
                $cls = 'square';
                $title = t("Follow");
            }

            $list .= '<li title="' . $d->pname . '-' . $d->b_name . '" >' 
                    . '<span title='.$title.' id="'.$d->id.'" class="follow ico '. $cls .'"></span> '
                    . $d->c_name . ' - '
                    . ProjectData::geturl($d->id) . ' - [' . $d->date . ']</li>';
        }

        $list .= '</ul>';


        $items = array();
        $items['content'] = $list;
        $items['title'] = t('Latest projects');
        $items['id'] = 'last_project';


        return array(
            '#items' => $items,
            '#theme' => 'ek_projects_dashboard',
            '#attached' => array(
                'library' => ['ek_projects/ek_projects.dashboard','ek_admin/ek_admin_css'],
            ),
            '#cache' => [
                'tags' => ['project_last_block'],
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
