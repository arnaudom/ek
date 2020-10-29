<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Menu\AdminMenuLink.
 */

namespace Drupal\ek_admin\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A menu link that shows settings error.
 */
class AdminMenuLink extends MenuLinkDefault {

    /**
     * The current user.
     *
     * @var \Drupal\Core\Session\AccountInterface
     */
    protected $currentUser;

    /**
     * Constructs a new MessageMenuLink.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_override
     *   The static override storage.
     * @param \Drupal\Core\Session\AccountInterface $current_user
     *   The current user.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AccountInterface $current_user) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
        $this->currentUser = $current_user;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
                $configuration, $plugin_id, $plugin_definition,
                $container->get('menu_link.static.overrides'),
                $container->get('current_user')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle() {
        
            if ($this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('administrate')) {
                                 
                    $coids = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
                    $api = \Drupal::moduleHandler()->invokeAll('ek_settings', [$coids]);
                    $mod = ['documents','finance','logistics','projects','sales'];
                    $c = 0;
                    foreach($mod as $key) {
                        if(array_key_exists($key, $api)) {
                            $c++;
                        }
                    }
                    if ($c > 0) {
                        return [
                            '#markup' => $this->t('Administration <span title=@t class="admin_menu_badge">@c</span>', ['@t' => t('Settings missing'), '@c' => $c]),
                            
                        ];
                    }
                    return $this->t('Administration');
            }
            return $this->t('Administration');
        
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName() {
        return 'ek_admin.main';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts() {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheTags() {
        return ['ek_admin.settings'];
    }

}
