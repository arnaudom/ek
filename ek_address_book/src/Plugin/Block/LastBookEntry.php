<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Plugin\Block\LastBookEntry.
 */

namespace Drupal\ek_address_book\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'list of latest created Address book entries widget' .
 *
 * @Block(
 *   id = "ek_address_book_last_entry_block",
 *   admin_label = @Translation("Last created Address Book entries"),
 *   category = @Translation("Ek Address Book block")
 * )
 */
class LastBookEntry extends BlockBase implements ContainerFactoryPluginInterface {
    
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition
        );
    }

    /**
     * Constructs an  object.
     *
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book', 'ab');
        $query->fields('ab', ['id', 'name', 'type', 'created', 'stamp', 'shortname']);
        $query->range(0, 10);
        $query->orderBy('ab.id', 'DESC');
        $data = $query->execute();
        $type = [1 => $this->t('client'), 2 => $this->t('supplier'), 3 => $this->t('other')];
        $list = '<ul>';

        while ($d = $data->fetchObject()) {
            $t = $type[$d->type];
            $lm = date("Y-m-d", $d->stamp);
            $url = Url::fromRoute('ek_address_book.view', ['abid' => $d->id], [])->toString();
            $a = "<a href='" .$url. "'>" . $d->shortname . "</a>";
            $list .= '<li title="Last edit ' . $lm . '" class="">'
                    . $d->name . ' - '
                    . $a . ' - <span title="'. $t .'">[' . $d->created . ']</span></li>';
        }

        $list .= '</ul>';


        $items = [];
        $items['content'] = $list;
        $items['title'] = t('Latest Address book entries');
        $items['id'] = 'last_book_entries';


        return [
            '#items' => $items,
            '#theme' => 'ek_address_book_dashboard',
            '#attached' => [
                'library' => ['ek_address_book/ek_address_book.dashboard', 'ek_admin/ek_admin_css'],
            ],
            '#cache' => [
                'tags' => ['ab_last_block'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous() && $account->hasPermission('view_project')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
