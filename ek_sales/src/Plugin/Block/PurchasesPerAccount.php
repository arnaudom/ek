<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Plugin\Block\PurchasesPerAccount.
 */

namespace Drupal\ek_sales\Plugin\Block;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a Purchases per account block
 *
 * @Block(
 *   id = "Purchases_per_account_block",
 *   admin_label = @Translation("Purchases per account block"),
 *   category = @Translation("Ek sales block")
 * )
 */
class PurchasesPerAccount extends BlockBase
{


  /**
   * {@inheritdoc}
   */
    public function build()
    {
        $items = array();

        $items['title'] = t('Purchases per account');
        $items['id'] = 'purchases-per-account';


        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');

        $months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
        $years = array(date('Y'), date('Y') - 1, date('Y') - 2, date('Y') - 3 );
        $content = '';
        $query = "SELECT sum(amountbc) as total,name FROM {ek_sales_purchase} i "
                    . "INNER JOIN {ek_address_book} b ON i.client=b.id WHERE "
                    . "date like :d GROUP BY b.name order by total DESC ";
        
        foreach ($years as $year) {
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':d' => $year . '%'));


            $content .= '<div>' . $year . '</div><table><tbody class="">';
            
            while ($d = $data->fetchObject()) {
                $content .= "<tr><td class='' title=''>" . $d->name . ":</td>"
                        . "<td class='' title='');'>" . number_format($d->total, 2) . " " . $baseCurrency . "</td></tr>";
            }
            $content .= "</tbody></table>";
        }
        $items['content'] =  '<div>' . $content . '</div>';

        return array(
            '#items' => $items,
            '#theme' => 'ek_sales_dashboard',
            '#attached' => array(
                'library' => array('ek_sales/ek_sales.dashboard'),
            ),
            '#cache' => [
                'tags' => ['purchases_per_account_block'],
            ],
        );
    }


    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('sales_data')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

    /**
     * {@inheritdoc}
     *
     * @todo Make cacheable once https://www.drupal.org/node/2351015 lands.
     */
    public function getCacheMaxAge()
    {
        return 0;
    }
}
