<?php

namespace Drupal\ek_admin\Plugin\tour\tip;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\tour\TipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays some text as a tip.
 *
 * @Tip(
 *   id = "htext",
 *   title = @Translation("Text")
 * )
 */
class TipPluginHText extends TipPluginBase implements ContainerFactoryPluginInterface {

    /**
     * The body text which is used for render of this Text Tip.
     *
     * @var string
     */
    protected $body;

    /**
     * The image attached to tip.
     *
     * @var string
     */
    protected $image;

    /**
     * The url of image attached to tip.
     *
     * @var string
     */
    protected $imageUrl;

    /**
     * Token service.
     *
     * @var \Drupal\Core\Utility\Token
     */
    protected $token;

    /**
     * The forced position of where the tip will be located.
     *
     * @var string
     */
    protected $location;

    /**
     * Unique aria-id.
     *
     * @var string
     */
    protected $ariaId;

    /**
     * Constructs a \Drupal\tour\Plugin\tour\tip\TipPluginText object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Utility\Token $token
     *   The token service.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->token = $token;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'));
    }

    /**
     * Returns a ID that is guaranteed uniqueness.
     *
     * @return string
     *   A unique id to be used to generate aria attributes.
     */
    public function getAriaId() {
        if (!$this->ariaId) {
            $this->ariaId = Html::getUniqueId($this->get('id'));
        }
        return $this->ariaId;
    }

    /**
     * Returns body of the text tip.
     *
     * @return string
     *   The tip body.
     */
    public function getBody() {
        return $this->get('body');
    }

    /**
     * Returns local image of the htext tip.
     * format: [module,folder]
     * @return string
     *   The tip image.
     */
    public function getImage() {
        return $this->get('image');
    }

    /**
     * Returns url image of the htext tip.
     *
     * @return string
     *   The tip image.
     */
    public function getImageUrl() {
        return $this->get('imageUrl');
    }

    /**
     * Returns location of the text tip.
     *
     * @return string
     *   The tip location.
     */
    public function getLocation() : ?string{
        return $this->get('location');
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes() {
        $attributes = parent::getAttributes();
        $attributes['data-aria-describedby'] = 'tour-tip-' . $this->getAriaId() . '-contents';
        $attributes['data-aria-labelledby'] = 'tour-tip-' . $this->getAriaId() . '-label';
        if ($location = $this->getLocation()) {
            $attributes['data-options'] = 'tipLocation:' . $location;
        }
        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput() {
        $output = '<h2 class="tour-tip-label" id="tour-tip-' . $this->getAriaId() . '-label">' . Html::escape($this->getLabel()) . '</h2>';
        $output .= '<p class="tour-tip-body" id="tour-tip-' . $this->getAriaId() . '-contents">' . $this->token->replace($this->getBody()) . '</p>';
        if ($url = $this->getImageUrl()) {
            $output .= '<p class="tour-tip-image" id="tour-tip-' . $this->getAriaId() . '-image1"><IMG src="' . $url . '"/></p>';
        }
        if ($img = $this->getImage()) {
            $parts = explode(",", $img);
            if (file_exists(drupal_get_path('module', $parts[0]) . $parts[1])) {
                $img = \Drupal::service('file_url_generator')->generateAbsoluteString(drupal_get_path('module', $parts[0]) . $parts[1]);
                $output .= '<p class="tour-tip-image" id="tour-tip-' . $this->getAriaId() . '-image2"><IMG src="' . $img . '"/></p>';
            }
        }

        return ['#markup' => $output, '#allowed_tags' => ['h2', 'p', 'img']];
    }

}
