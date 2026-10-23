<?php

declare(strict_types=1);

use Drupal\product_search\CsvProductSearch;

/**
 * Asserts CSV parsing and matching behavior with an isolated stock file.
 */
function csv_product_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$root = sys_get_temp_dir() . '/product-search-' . bin2hex(random_bytes(8));
mkdir($root . '/gazdabolt_full_stock', 0700, TRUE);
$path = $root . '/gazdabolt_full_stock/merged_products.csv';
try {
  $search = new CsvProductSearch($root);
  csv_product_assert($search->search('test') === [], 'A missing CSV must not break node searches.');
  $handle = fopen($path, 'w');
  fputcsv($handle, ["\xEF\xBB\xBFunique_id", 'termék neve', 'leírás'], ',', '"', '');
  fputcsv($handle, ['1', 'Árvíztűrő, lapát', 'description-only'], ',', '"', '');
  fputcsv($handle, ['2', 'Kalapács', ''], ',', '"', '');
  fputcsv($handle, ['3', 'Kalapács', ''], ',', '"', '');
  fputcsv($handle, ['4', '<script>alert(1)</script>', ''], ',', '"', '');
  fclose($handle);
  csv_product_assert($search->search('ÁRVÍZTŰRŐ') === ['Árvíztűrő, lapát'], 'Hungarian case-insensitive matching and quoted commas must work.');
  csv_product_assert($search->search('lapát+kalapács') === ['Árvíztűrő, lapát', 'Kalapács'], 'Multiple keywords must use OR and duplicate names must appear once.');
  csv_product_assert($search->search('description-only') === [], 'Only the name column may match.');
  csv_product_assert($search->search(' + ') === [], 'Empty keywords must not return the whole stock.');

  $controller = \Drupal\product_search\Controller\ProductSearchController::create(\Drupal::getContainer());
  $build_results = new ReflectionMethod($controller, 'buildSearchResults');
  $build = $build_results->invoke($controller, [], $search->search('script'));
  $html = (string) \Drupal::service('renderer')->renderRoot($build);
  csv_product_assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'CSV product names must be HTML escaped.');
  $find = new ReflectionMethod($controller, 'findProductNodeIds');
  $nids = $find->invoke($controller, 'a');
  csv_product_assert($nids !== [], 'The integration check requires existing matching nodes.');
  $mixed = $build_results->invoke($controller, $nids, ['CSV test product']);
  csv_product_assert(isset($mixed['products'], $mixed['csv']), 'Mixed results must retain the existing product View.');
  csv_product_assert(array_values($mixed['products']['#arguments']) === [implode('+', $nids)], 'Existing node IDs must reach the View unchanged.');
  $html = (string) \Drupal::service('renderer')->renderRoot($mixed);
  csv_product_assert(str_contains($html, 'CSV test product') && str_contains($html, 'view-products'), 'Mixed node and CSV results must render together.');
  file_put_contents($path, "wrong,header\nfoo,bar\n");
  csv_product_assert($search->search('foo') === [], 'A missing name column must fail safely.');
  print "PASS: CSV parsing, Unicode case, OR keywords, name-only matching, deduplication, empty/missing CSV, HTML escaping, and mixed View rendering.\n";
}
finally {
  if (file_exists($path)) {
    unlink($path);
  }
  rmdir($root . '/gazdabolt_full_stock');
  rmdir($root);
}
