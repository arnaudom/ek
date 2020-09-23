<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Plugin\Block\CalendarProjectButton.
 */

namespace Drupal\ek_projects\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'button for calendar' .
 *
 * @Block(
 *   id = "calendar_projects_button_block",
 *   admin_label = @Translation("Calendar button projects"),
 *   category = @Translation("Ek projects block")
 * )
 */
class CalendarProjectButton extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $destination = ['destination' => 'projects/project'];
        $att = [
            'class' => ['use-ajax', 'button', 'button-calendar'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
            'data-dialog-options' => Json::encode([
                'width' => '33%',
                'resizable' => 1,
                'dialogClass' => 'ui-dialog-off-canvas calendar-off-canvas',
            ]),
        ];
        $b = Link::createFromRoute($this->t('Calendar'), 'ek_projects_calendar', [], ['query' => $destination, 'attributes' => $att])->toString();
        $l = (null == \Drupal::currentUser()->getPreferredLangcode()) ? 'en' : \Drupal::currentUser()->getPreferredLangcode();
        return array(
            '#markup' => "<div id='tour-calendar'>" . $b ." </div>",
            '#attached' => array(
                'library' => array('ek_projects/ek_projects.calendar'),
                'drupalSettings' => array('calendarLang' => $l, 'type' => 'button')
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous() && $account->hasPermission('projects_dashboard')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
