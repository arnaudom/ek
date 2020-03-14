<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Plugin\Block\ImportExcelChartBlock
 */

namespace Drupal\ek_finance\Plugin\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'upload form to import excel budget data' .
 *
 * @Block(
 *   id = "import_excel_chart_block",
 *   admin_label = @Translation("Import Excel chart of accounts"),
 *   category = @Translation("Ek Finance block")
 * )
 */
class ImportExcelChartBlock extends BlockBase {
  
  

  /**
   * {@inheritdoc}
   */
  public function build() {
 
  $items = array();
  $items['#title'] = t("Import chart");
  $items['content'] = \Drupal::formBuilder()->getForm('Drupal\ek_finance\Form\UploadChart');

  return $items;
  /*
  return array(
    '#items' => $items,
  );
  */
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (!$account->isAnonymous() && $account->hasPermission('administrate_finance') ) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
    
  }
  
  /**
   * {@inheritdoc}
   *
   * @todo Make cacheable once https://www.drupal.org/node/2351015 lands.
   */
  public function getCacheMaxAge() {
    return 0;
  }
 
}