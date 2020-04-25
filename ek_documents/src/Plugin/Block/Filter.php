<?php
/**
 * @file
 * Contains \Drupal\ek_documents\Plugin\Block\Filter.
 */
namespace Drupal\ek_documents\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a 'Filter form for personal documents' .
 *
 * @Block(
 *   id = "filter_document_block",
 *   admin_label = @Translation("Filter documents"),
 *   category = @Translation("Ek Documents block")
 * )
 */
class Filter extends BlockBase
{
  

  /**
   * {@inheritdoc}
   */
    public function build()
    {
        $items = array();
        $items['content'] = \Drupal::formBuilder()->getForm('Drupal\ek_documents\Form\FilterDoc');

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        if (!$account->isAnonymous() && $account->hasPermission('manage_documents')) {
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
