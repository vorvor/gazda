<?php

namespace Drupal\product_search;

/**
 * Searches the stock CSV without creating content entities.
 */
final class CsvProductSearch {

  public const SOURCES = [
    'gazdabolt' => [
      'path' => '/gazdabolt_full_stock/merged_products.csv',
      'column' => 'termék neve',
      'shop' => 'GAZDABOLT',
      'alias' => '/bolt/gazdabolt',
    ],
    'balint' => [
      'path' => '/balint_full_stock/products.csv',
      'column' => 'product strict name',
      'shop' => 'BÁLINT ÉS TÁRSA',
      'alias' => '/bolt/balint-es-tarsa-kft-villamossagi-szakuzlet',
    ],
  ];

  /**
   * Returns source-aware cards, keeping identical names from different shops.
   */
  public function searchProducts(string $keyword, ?array $sources = NULL): array {
    $products = [];
    foreach ($sources ?? array_keys(self::SOURCES) as $source) {
      foreach ($this->search($keyword, $source) as $name) {
        $products[] = [
          'name' => $name,
          'short_name' => $this->shortName($name),
          'shop' => self::SOURCES[$source]['shop'],
          'source' => $source,
        ];
      }
    }
    return $products;
  }

  /**
   * Short titles for the most recent set of matching full names.
   *
   * @var array<string, string>
   */
  private array $shortNames = [];

  public function __construct(private readonly string $appRoot) {}

  /**
   * Counts distinct nonempty product names within each configured stock source.
   */
  public function countProducts(): int {
    $count = 0;
    foreach (self::SOURCES as $source) {
      $handle = @fopen($this->appRoot . $source['path'], 'r');
      if ($handle === FALSE) {
        continue;
      }
      try {
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === FALSE) {
          continue;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        $column = array_search($source['column'], array_map('trim', $header), TRUE);
        if ($column === FALSE) {
          continue;
        }
        $names = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
          $name = trim($row[$column] ?? '');
          if ($name !== '') {
            $names[mb_strtolower($name, 'UTF-8')] = TRUE;
          }
        }
        $count += count($names);
      }
      finally {
        fclose($handle);
      }
    }
    return $count;
  }

  /**
   * Returns names matching any whitespace- or plus-separated keyword.
   */
  public function search(string $keyword, string $source = 'gazdabolt'): array {
    $this->shortNames = [];
    if (!isset(self::SOURCES[$source])) {
      return [];
    }
    $keywords = preg_split('/[+\s]+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $path = $this->appRoot . self::SOURCES[$source]['path'];
    if (!$keywords || !is_readable($path)) {
      return [];
    }

    $handle = @fopen($path, 'r');
    if ($handle === FALSE) {
      return [];
    }

    try {
      $header = fgetcsv($handle, 0, ',', '"', '');
      if ($header === FALSE) {
        return [];
      }
      $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
      $column = array_search(self::SOURCES[$source]['column'], array_map('trim', $header), TRUE);
      $short_column = array_search('rövid terméknév', array_map('trim', $header), TRUE);
      if ($column === FALSE) {
        return [];
      }

      $names = [];
      while (($row = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
        $name = trim($row[$column] ?? '');
        foreach ($keywords as $word) {
          if ($name !== '' && mb_stripos($name, $word, 0, 'UTF-8') !== FALSE) {
            $names[mb_strtolower($name, 'UTF-8')] = $name;
            $short_name = $short_column === FALSE ? '' : trim($row[$short_column] ?? '');
            $this->shortNames[$name] = $short_name !== '' ? $short_name : $name;
            break;
          }
        }
      }
      return array_values($names);
    }
    finally {
      fclose($handle);
    }
  }

  /**
   * Returns the editable CSV title, falling back to the original product name.
   */
  public function shortName(string $name): string {
    return $this->shortNames[$name] ?? $name;
  }

}
