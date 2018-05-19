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

        $query = "SELECT p.id, pcode, c.name as country, pname, date,b.name as client FROM {ek_project} p INNER JOIN {ek_country} c ON p.cid=c.id INNER JOIN {ek_address_book} b ON p.client_id=b.id order by p.id DESC limit 20";
        $data = Database::getConnection('external_db', 'external_db')->query($query);
        $list = '<ul>';

        while ($d = $data->fetchObject()) {

            $list .= '<li title="' . $d->pname . '-' . $d->client . '" >' . $d->country . ' - '
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
                'library' => array('ek_projects/ek_projects.dashboard'),
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
