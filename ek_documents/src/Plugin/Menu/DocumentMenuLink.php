<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Menu\DocumentMenuLink.
 */

namespace Drupal\ek_documents\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A menu link that shows shared documents.
 */
class DocumentMenuLink extends MenuLinkDefault {

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
        
            if ($this->currentUser->isAuthenticated()) {
                                   
                    $userdata = \Drupal::service('user.data')->get('ek_documents', $this->currentUser->id());
                    if (count($userdata) > 0) {
                        return [
                            '#markup' => $this->t('Documents <span title=@t class="shared_document_badge">@c</span>', ['@t' => t('New document sahred'),'@c' => count($userdata)]),
                            '#attached' => [
                                'library' => ['ek_documents/ek_documents_css'],
                            ],
                        ];
                    }
                    return $this->t('Documents');
            }
            return $this->t('Documents');
        
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName() {
        return 'ek_documents_documents';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts() {
        return ['user'];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheTags() {
        return ['new_documents_shared'];
    }

}
