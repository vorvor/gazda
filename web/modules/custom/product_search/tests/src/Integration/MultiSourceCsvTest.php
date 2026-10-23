<?php

use Drupal\product_search\CsvProductSearch;

$root = sys_get_temp_dir() . '/multi-stock-' . bin2hex(random_bytes(8));
mkdir($root . '/gazdabolt_full_stock', 0700, TRUE);
mkdir($root . '/balint_full_stock', 0700, TRUE);
$gazda = $root . '/gazdabolt_full_stock/merged_products.csv';
$balint = $root . '/balint_full_stock/products.csv';
try {
  file_put_contents($gazda, "termék neve,rövid terméknév\nFehér lámpa,Lámpa\n");
  file_put_contents($balint, "product strict name,rövid terméknév,short description\nFehér lámpa,Asztali lámpa,description-only\nFehér lámpa,Asztali lámpa,description-only\n");
  $service = new CsvProductSearch($root);
  $products = $service->searchProducts('LÁMPA');
  if (count($products) !== 2 || array_column($products, 'source') !== ['gazdabolt', 'balint']
    || array_column($products, 'short_name') !== ['Lámpa', 'Asztali lámpa']) {
    throw new RuntimeException('Cross-shop duplicates must remain separate with source-specific short names.');
  }
  if (count($service->searchProducts('lámpa', ['balint'])) !== 1
    || $service->searchProducts('description-only') !== []
    || $service->searchProducts('lámpa', []) !== []) {
    throw new RuntimeException('Source selection, name-only matching and empty source selection must work.');
  }
  unlink($gazda);
  if (count($service->searchProducts('lámpa')) !== 1) {
    throw new RuntimeException('A missing stock file must not break other sources.');
  }
  print "PASS: per-shop deduplication, source-specific titles, name-only matching, missing-file isolation.\n";
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
