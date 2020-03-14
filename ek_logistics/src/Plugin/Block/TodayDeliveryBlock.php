<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Plugin\Block\TodayDeliverylBlock.
 */

namespace Drupal\ek_logistics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;

/**
 * Provides a 'Today deliveries list' .
 *
 * @Block(
 *   id = "logistics_today_delivery_block",
 *   admin_label = @Translation("Today deliveries"),
 *   category = @Translation("Ek Logistics block")
 * )
 */
class TodayDeliveryBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {

        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_delivery', 'd');
        $or = $query->orConditionGroup();
        $or->condition('head', $access, 'IN');
        $or->condition('allocation', $access, 'IN');
        $f = array('id','head','serial','client','status','title','date','ddate','pcode');
        $data = $query
                ->fields('d', $f)
                ->condition('d.ddate', date('Y-m-d'), '=')
                ->condition($or)
                ->orderBy('id', 'ASC')
                ->execute();

        
        $content = '';

        while ($r = $data->fetchObject()) {

            $client = \Drupal\ek_address_book\AddressBookData::geturl($r->client);
            $link = "<a title='" . t('view') . "' href='"
                    . Url::fromRoute('ek_logistics.delivery.print_html', ['id' => $r->id], [])->toString() . "'>"
                    . $r->serial . "</a>";
            $content .= "<li>" . $link . ":  " . $client . "</li>";
        }

        if ($content == '')
            $content = "<li>" . t('No delivery for today') . "</li>";
        $list = "<ul>" . $content . "</ul>";


        $items = array();
        $items['title'] = t('Today deliveries');
        $items['content'] = $list;
        $items['id'] = 'todaydeliveries';

        return array(
            '#items' => $items,
            '#theme' => 'ek_logistics_dashboard',
            '#attached' => array(
                'library' => array('ek_logistics/ek_logistics.dashboard'),
            ),
            '#cache' => [
                'tags' => ['logistics_delivery_block'],
                'max-age' => 43200,
            ],
        );
    }

    /**
     * {@inheritdoc}
     */
    public function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous() && $account->hasPermission('logistics_dashboard')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
