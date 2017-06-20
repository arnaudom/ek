<?php
/**
 * @file
 * Contains \Drupal\yourmodule\Plugin\Block\CalendarButtonBlock.
 */
namespace Drupal\ek_projects\Plugin\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'button for calendar widget' .
 *
 * @Block(
 *   id = "calendar_projects_block",
 *   admin_label = @Translation("Calendar button projects"),
 *   category = @Translation("Ek projects Widgets")
 * )
 */
class CalendarButtonBlock extends BlockBase {
  
  

  /**
   * {@inheritdoc}
   */
  public function build() {
 
  
  $link = \Drupal::url('ek_projects_calendar');
  $button = "<div id='tour-calendar'><a data-drupal-link-system-path='/projects/calendar' class='button button-action use-ajax' "
          . "href='". $link ."'>". t('calendar') . "</a></div>";


  
  return array(
    '#markup' => $button ,
      );
  
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (!$account->isAnonymous() && $account->hasPermission('projects_dashboard') ) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();      
    
  }
 
}