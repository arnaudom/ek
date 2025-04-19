<?php

namespace Drupal\ek_admin\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Custom Twig extension.
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('file_exists', [$this, 'fileExists']),
      new TwigFunction('file_url', [$this, 'fileUrl']),
      new TwigFunction('number_to_word', [$this, 'numberToWord']),
    ];
  }

  /**
   * Check if a file exists.
   */
  public function fileExists($path) {
    return file_exists($path);
  }
  
  /**
   * Get URL for a file.
   */
  public function fileUrl($path) {
    return \Drupal::service('file_url_generator')->generateAbsoluteString($path);
  }

  /**
   * Convert number to words.
   */
  public function numberToWord($number) {
    $resultInWords = new \Drupal\ek_admin\NumberToWord();
    return $resultInWords->en(round($number, 2));
  }
}