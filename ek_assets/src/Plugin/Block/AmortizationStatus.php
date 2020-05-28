<?php
/**
 * @file
 * Contains \Drupal\ek_assets\Plugin\Block\AmortizationStatus.
 */
namespace Drupal\ek_assets\Plugin\Block;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides an amortization status block
 *
 * @Block(
 *   id = "amortization_status_block",
 *   admin_label = @Translation("Assets amortization status list"),
 *   category = @Translation("Ek assets Widgets")
 * )
 */
class AmortizationStatus extends BlockBase
{
  
  
  /**
   * {@inheritdoc}
   */
    public function build()
    {
        $items = array();
        $items['title'] = $this->t('Assets amortization');
        $items['id'] = 'assets_amortization_status';
        $now = date('U');

        $access = AccessCheck::GetCompanyByUser();
        $colist = AccessCheck::CompanyList();
        $company = implode(',', $access);

        $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t("<a href='@url'>List</a>", ['@url' => $url]),
        );

        $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE amort_record <> :r "
                . "AND amort_status <> :s "
                . "AND FIND_IN_SET (coid, :c)";
        $a = array(
            ':r' => '',
            ':s' => 1,
            ':c' => $company,
        );

        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);
        
        $next = "<ul>";
        $due = "<ul>";
        
        while ($r = $data->fetchObject()) {
            $schedule = unserialize($r->amort_record);
            foreach ($schedule['a'] as $key => $value) {
                if ($value['journal_reference'] == '') {
                    $date = strtotime($value['record_date']);
                    $coname = $colist[$r->coid];
                    $url = Url::fromRoute('ek_assets.set_amortization', ['id' => $r->id])->toString();
                    if ($date >= $now) {
                        $next .= '<li><a href="'. $url .'">'
                                            . $r->asset_name . '</a>' . ' [' . $value['record_date']
                                            . '], - ' . $coname . '</li>';
                    } else {
                        $due .= '<li><a href="'. $url .'">'
                                            . $r->asset_name . '</a>' . ' <b>[' . $value['record_date']
                                            . ']</b>, - ' . $coname . '</li>';
                    }
                    break;
                } else {
                    //break;
                }
            }
        }
     
        $items['content']['next'] = ['#markup' => $next . '</ul>'];
        $items['content']['due'] = ['#markup' => $due . '</ul>'];
      
        return array(
    '#items' =>$items,
    '#theme' => 'ek_assets_dashboard',
    '#attached' => array(
      'library' => array('ek_assets/ek_assets.dashboard'),
      ),
    '#cache' => [
      'max-age' => 0,
      ],
    );
    }
  
    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('amortize_assets')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }
}
