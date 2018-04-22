<?php
/**
 * @file
 * Contains \Drupal\ek_hr\Plugin\Block\CurrentPayrollBlock.
 */
namespace Drupal\ek_hr\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
/**
 * Provides a 'Payroll statistics widget' .
 *
 * @Block(
 *   id = "hr_payroll_stat_block",
 *   admin_label = @Translation("Payroll statistics"),
 *   category = @Translation("Ek HR Widgets")
 * )
 */
class PayrollStatBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
 
  $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
  $company = implode(',',$access);
     
    $query = "SELECT current,name from {ek_hr_payroll_cycle} "
            . "INNER JOIN {ek_company} "
            . "ON ek_hr_payroll_cycle.coid=ek_company.id "
            . "WHERE FIND_IN_SET (ek_company.id, :a) order by name";
  $a = array( ':a' => $company);    
    
  $data = Database::getConnection('external_db','external_db')->query($query, $a);
  
  $list = "<table><thead><tr><th></th><th>".t('working')."</th><th>".t('resigned')."</th><th>".t('total')."</th></tr></thead><tbody>";

  foreach ($access as $coid) {
  
  if($coid != '') {
    $name = Database::getConnection('external_db','external_db')
            ->query("SELECT name from {ek_company} WHERE id=:coid", array(':coid' => $coid))
            ->fetchField();

    $query = "SELECT count(id) from {ek_hr_workforce} WHERE active=:a and company_id=:coid";
    $a = array(':a' => 'working', ':coid' => $coid);
    $working = Database::getConnection('external_db','external_db')->query($query, $a)->fetchField();
    $a = array(':a' => 'resigned', ':coid' => $coid);
    $resigned = Database::getConnection('external_db','external_db')->query($query, $a)->fetchField();
    $total = $working+$resigned;
  
    $list =  $list."<tr><td>$name</td><td>$working </td><td>$resigned</td><td>$total</td></tr>";
  }  
    
  
  }    
  
  $list .= "</tbody></table>";
  
  $items = array();
  $items['title'] = t('Payroll statistics');
  $items['content'] = $list;
  $items['id'] = 'payroll_stat';
  
  return array(
    '#items' => $items,
    '#theme' => 'ek_hr_dashboard',
    '#attached' => array(
      'library' => array('ek_hr/ek_hr.dashboard'),
      ),
   '#cache' => [
                'tags' => ['payroll_stat_block'],
            ],  
    );
  
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if (!$account->isAnonymous() && $account->hasPermission('hr_dashboard') ) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden(); 
  }
}