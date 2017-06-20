<?php
 
/**
 * @file
 * Contains \Drupal\ek_finance\Plugin\Field\AidField.
 */
 
namespace Drupal\ek_finance\Plugin\Field\AidField;
 
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
 
/**
 * Provides a 'AidField' field.
 *
 * @FieldWidget(
 *   id = "ek_finance_aid_field",
 *   label = @Translation("Account id"),
  *   field_types = {
 *     "select"
 *   },
  *   settings = {
 *     "size" = "1",
 *     "placeholder" = ""
 *   }
 *   admin_label = @Translation("ek_finance Account id"),
 *   module = "ek_finance"
 * )
 */
class AidField extends WidgetBase {
 
  /**
   * {@inheritdoc}
   */
}