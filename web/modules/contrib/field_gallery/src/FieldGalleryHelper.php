<?php

namespace Drupal\field_gallery;

/**
 * Helper functions for field_gallery.
 */
class FieldGalleryHelper {

  /**
   * Build configuration string from an array for select options list.
   *
   * @param array $options
   *   Options.
   *
   * @return string
   *   Help text.
   */
  public static function buildConfOptionsString(array $options) {
    $output = '';
    // Array is an associative array.
    $flg_associative = TRUE;
    if (array_values($options) === $options) {
      $flg_associative = FALSE;
    }

    foreach ($options as $key => $value) {
      if ($output) {
        $output .= "\n";
      }

      if ($flg_associative) {
        // Kay / Value.
        $output .= "$key|$value";
      }
      else {
        // Key only.
        $output .= "$value";
      }
    }
    return $output;
  }

}
