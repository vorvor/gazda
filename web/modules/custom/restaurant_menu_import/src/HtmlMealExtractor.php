<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extracts common Drupal Views menu rows from HTML.
 */
final class HtmlMealExtractor {

  /**
   * Extracts meals from an HTML document or one element ID.
   *
   * @return \Drupal\restaurant_menu_import\MealRecord[]
   *   Extracted meals.
   */
  public function extract(string $html, string $source_url, ?string $element_id = NULL, ?string $element_class = NULL): array {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    if ($element_id !== NULL) {
      $context = $xpath->query('//*[@id=' . $this->xpathLiteral($element_id) . ']')->item(0);
    }
    elseif ($element_class !== NULL) {
      $context = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . str_replace('"', '', $element_class) . ' ")]')->item(0);
    }
    else {
      $context = $document->documentElement;
    }
    if (!$context instanceof DOMElement) {
      return [];
    }

    $records = [];
    $rows = $xpath->query('.//*[
      contains(concat(" ", normalize-space(@class), " "), " views-row ")
      or contains(concat(" ", normalize-space(@class), " "), " meal__item ")
    ]', $context);
    foreach ($rows ?: [] as $row) {
      $title_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " views-field-title ")]', $row)->item(0);
      $title_node ??= $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " food-name ")]', $row)->item(0);
      $price_node = $xpath->query('.//*[contains(@class, "price")]', $row)->item(0);
      $price_node ??= $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " to-cart ")]', $row)->item(0);
      if (!$title_node || !$price_node) {
        continue;
      }

      $name = $this->clean($title_node->textContent);
      $price_digits = preg_replace('/\D/', '', $price_node->textContent);
      if ($name === '' || $price_digits === '') {
        continue;
      }
      $body_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " views-field-body ")]', $row)->item(0);
      $body_node ??= $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " card-description ")]', $row)->item(0);
      $description = $body_node ? $this->clean($body_node->textContent) : '';
      $records[] = new MealRecord(
        hash('sha256', $source_url . '|' . mb_strtolower($name)),
        $name,
        number_format((int) $price_digits, 2, '.', ''),
        $description,
        $source_url . ($element_id !== NULL ? '#' . $element_id : ''),
      );
    }

    return $records;
  }

  /**
   * Normalizes visible HTML text.
   */
  private function clean(string $value): string {
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Quotes a value for XPath.
   */
  private function xpathLiteral(string $value): string {
    return '"' . str_replace('"', '', $value) . '"';
  }

}
