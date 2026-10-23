<?php

use Drupal\product_search\Controller\ProductSearchController;
use Drupal\product_search\CsvProductSearch;
use Symfony\Component\HttpFoundation\Request;

$root = sys_get_temp_dir() . '/discovery-count-' . bin2hex(random_bytes(8));
mkdir($root . '/gazdabolt_full_stock', 0700, TRUE);
mkdir($root . '/balint_full_stock', 0700, TRUE);
$gazda = $root . '/gazdabolt_full_stock/merged_products.csv';
$balint = $root . '/balint_full_stock/products.csv';
try {
  file_put_contents($gazda, "\xEF\xBB\xBFtermék neve\nLámpa\nlámpa\n\"\"\nÁsó\n");
  file_put_contents($balint, "product strict name\nLámpa\n");
  $csv = new CsvProductSearch($root);
  if ($csv->countProducts() !== 3) {
    throw new RuntimeException('Count unique nonempty names per source, retaining cross-shop duplicates.');
  }
  unlink($gazda);
  if ($csv->countProducts() !== 1) {
    throw new RuntimeException('Missing sources must not prevent counting other sources.');
  }
  file_put_contents($balint, "wrong column\nLámpa\n");
  if ($csv->countProducts() !== 0) {
    throw new RuntimeException('Invalid headers must contribute zero products.');
  }

  $content_count = (int) \Drupal::entityQuery('node')
    ->accessCheck(TRUE)
    ->condition('type', ['product', 'service'], 'IN')
    ->condition('status', 1)
    ->count()->execute();
  $csv_count = \Drupal::service('product_search.csv_search')->countProducts();
  $page = ProductSearchController::create(\Drupal::getContainer())->page(Request::create('/search-product'));
  if ($csv_count === 0 || $page['#local_offering_count'] !== $content_count + $csv_count) {
    throw new RuntimeException('Discovery count must add CSV products to published product/service content.');
  }
  printf("PASS: discovery count = %d content + %d CSV = %d; deduplication, blank names, missing files and invalid headers.\n", $content_count, $csv_count, $page['#local_offering_count']);
}
finally {
  foreach ([$gazda, $balint] as $path) {
    if (file_exists($path)) {
      unlink($path);
    }
  }
  rmdir($root . '/gazdabolt_full_stock');
  rmdir($root . '/balint_full_stock');
  rmdir($root);
}
