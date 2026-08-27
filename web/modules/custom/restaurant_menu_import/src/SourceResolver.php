<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

/**
 * Converts the restaurant source field into simple source descriptors.
 */
final class SourceResolver {

  /**
   * Resolves a source field value.
   *
   * @return array<int, array{type: string, url?: string, selector?: string}>
   *   Source descriptors.
   */
  public function resolve(string $value): array {
    $value = trim($value);
    if ($value === '') {
      return [];
    }

    if ($value === '[image]') {
      return [['type' => 'image']];
    }

    if (preg_match('/section with class\s+\.([a-z0-9_-]+)\s+on\s+(https?:\/\/\S+)/i', $value, $matches)) {
      return [[
        'type' => 'html_pdf_section',
        'selector' => $matches[1],
        'url' => rtrim($matches[2]),
      ]];
    }

    if (preg_match('/section\s+wit(?:h)?\s+class\s+\.([a-z0-9_-]+)\s+on(?:\s+site)?\s+(https?:\/\/\S+)/i', $value, $matches)) {
      return [[
        'type' => 'html_section',
        'selector' => $matches[1],
        'url' => rtrim($matches[2]),
      ]];
    }

    $sources = [];
    foreach (preg_split('/\R+/', $value) ?: [] as $line) {
      $url = trim($line);
      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        continue;
      }

      $fragment = parse_url($url, PHP_URL_FRAGMENT);
      if (is_string($fragment) && $fragment !== '') {
        $sources[] = [
          'type' => 'html_fragment',
          'url' => preg_replace('/#.*$/', '', $url),
          'selector' => $fragment,
        ];
      }
      elseif (preg_match('/\.pdf(?:$|\?)/i', $url)) {
        $sources[] = ['type' => 'pdf', 'url' => $url];
      }
      else {
        $sources[] = ['type' => 'html', 'url' => $url];
      }
    }

    return $sources;
  }

}
