<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Menu\MessageMenuLink.
 */

namespace Drupal\ek_messaging\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

/**
 * A menu link that shows unread messages.
 */
class MessageMenuLink extends MenuLinkDefault {

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
                $configuration, $plugin_id, $plugin_definition, $container->get('menu_link.static.overrides'), $container->get('current_user')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle() {

        $db = FALSE;
        try {
            //verify that the database have been installed first to prevent error upon module install
            $external = Database::getConnectionInfo('external_db');
            if (!empty($external)) {
                $db = TRUE;
            }
        } catch (Exception $e) {
            return null;
        }

        if ($db == TRUE) {
            if ($this->currentUser->isAuthenticated()) {
                $query = "SHOW TABLES LIKE 'ek_messaging'";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query)
                        ->fetchField();
                if ($data == 'ek_messaging') {

                    $me = $this->currentUser->id();
                    $user = "%," . $me . ",%";
                    $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_messaging', 'm');
                    $query->condition('inbox', $user, 'like');
                    $query->condition('status', $user, 'not like');
                    $query->condition('archive', $user, 'not like');
                    $query->addExpression('Count(id)', 'count');
                    
                    $Obj = $query->execute();
                    $count = $Obj->fetchObject()->count;

                    if ($count > 0) {
                        return [
                            '#markup' => $this->t('Messages <span class="inbox_message_badge">@c</span>', ['@c' => $count]),
                            '#attached' => [
                                'library' => ['ek_messaging/ek_messaging_css'],
                            ],
                            '#cache' => ['tags' => ['ek_message_inbox']],
                        ];
                    }

                    return $this->t('Messages');
                }
            }
            return $this->t('Messages');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName() {
        return 'ek_messaging_inbox';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts() {
        return [];
    }

}
