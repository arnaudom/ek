<?php
/**
 * @file
 * Contains \Drupal\yourmodule\Plugin\Block\CalendarBlock.
 */
namespace Drupal\ek_projects\Plugin\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'button for calendar widget' .
 *
 * @Block(
 *   id = "fullcalendar_block",
 *   admin_label = @Translation("Full Calendar projects"),
 *   category = @Translation("Ek projects Widgets")
 * )
 */
class CalendarBlock extends BlockBase {
  
  

  /**
   * {@inheritdoc}
   */
  public function build() {
 
  
  $items = array();
  $link = Url::fromRoute('ek_projects_calendar')->toString();
  $cal = "<a class='use-ajax' "
          . "href='". $link ."'>". t('expand') . "</a>";
  $items['content'] = "<div id='calendar_block'></div>"
          . "<div>". $cal  . "</div>";
  $items['title'] = t('Calendar');
  $items['id'] = 'calendar_small';
  $l =  \Drupal::currentUser()->getPreferredLangcode();

  
  return array(
    '#items' => $items,
    '#theme' => 'calendar_block',
    '#attached' => array(
      'library' => array('ek_projects/ek_projects.calendar'),
      'drupalSettings' => array('calendarLang' => $l, 'type' => 'block' )
      ),
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