<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Plugin\Block\TodayReceivingBlock.
 */

namespace Drupal\ek_logistics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;

/**
 * Provides a 'Today receiving list' .
 *
 * @Block(
 *   id = "logistics_today_receiving_block",
 *   admin_label = @Translation("Today receiving"),
 *   category = @Translation("Ek Logistics block")
 * )
 */
class TodayReceivingBlock extends BlockBase
{

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_receiving', 'd');
        $or = $query->orConditionGroup();
        $or->condition('head', $access, 'IN');
        $or->condition('allocation', $access, 'IN');
        $f = array('id', 'head', 'serial', 'supplier', 'status', 'title', 'date', 'ddate', 'pcode');
        $data = $query
                ->fields('d', $f)
                ->condition('d.ddate', date('Y-m-d'), '=')
                ->condition('d.type', 'RR', '=')
                ->condition($or)
                ->orderBy('id', 'ASC')
                ->execute();


        $content = '';

        while ($r = $data->fetchObject()) {
            $client = \Drupal\ek_address_book\AddressBookData::geturl($r->supplier);
            $link = "<a title='" . $this->t('view') . "' href='"
                    . Url::fromRoute('ek_logistics.receiving.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            $content .= "<li>" . $link . ":  " . $client . "</li>";
        }

        if ($content == '') {
            $content = "<li>" . $this->t('No receiving for today') . "</li>";
        }
        $list = "<ul>" . $content . "</ul>";


        $items = array();
        $items['title'] = $this->t('Today receiving');
        $items['content'] = $list;
        $items['id'] = 'todayreceiving';

        return array(
            '#items' => $items,
            '#theme' => 'ek_logistics_dashboard',
            '#attached' => array(
                'library' => array('ek_logistics/ek_logistics.dashboard'),
            ),
            '#cache' => [
                'tags' => ['logistics_receiving_block'],
                'max-age' => 43200,
            ],
        );
    }

    /**
     * {@inheritdoc}
     */
    public function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('logistics_dashboard')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }
}
