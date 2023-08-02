<?php
/**
 * @file
 * Contains \Drupal\ek_hr\Plugin\Block\CurrentPayrollBlock.
 */
namespace Drupal\ek_hr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'Current payroll widget' .
 *
 * @Block(
 *   id = "hr_current_payroll_block",
 *   admin_label = @Translation("Current payroll month"),
 *   category = @Translation("Ek HR block")
 * )
 */
class CurrentPayrollBlock extends BlockBase {
   
  /**
   * {@inheritdoc}
   */
    public function build() {
        $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
     
        $query = "SELECT current,name from {ek_hr_payroll_cycle} INNER JOIN {ek_company} ON ek_hr_payroll_cycle.coid=ek_company.id WHERE FIND_IN_SET (ek_company.id, :a) order by name";
        $a = array( ':a' => $company);
    
        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
  
        $list = "<ul>";
  
        while ($r = $data->fetchObject()) {
            $list .= "<li>" . $r->name . ": " . $r->current . "</li>";
        }
  
        $list .= "</ul>";
  
        $items = array();
        $items['title'] = t('Current payroll cycle');
        $items['content'] = $list;
  
        return array(
            '#items' => $items,
            '#theme' => 'ek_hr_dashboard',
            '#attached' => ['library' => ['ek_hr/ek_hr.dashboard'],],
            '#cache' => ['max-age' => 86400,],
    );
    }

    /**
     * {@inheritdoc}
     */
    public function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('hr_dashboard')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }
}
