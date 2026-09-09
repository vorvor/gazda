<?php

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\product_search\Controller\ProductSearchController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_query_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$controller = \Drupal::service('class_resolver')
  ->getInstanceFromDefinition(ProductSearchController::class);
$build_method = new ReflectionMethod($controller, 'buildProductSearchQuery');
$query = $build_method->invoke($controller, 'hermes-query-check');
product_search_query_assert($query->hasTag('node_access'), 'The product lookup query must apply Drupal node-access grants.');

Database::startLog('product_search_query_test');
$find_method = new ReflectionMethod($controller, 'findProductNodeIds');
$find_method->invoke($controller, 'hermes-query-check');
$search_queries = array_filter(
  Database::getLog('product_search_query_test'),
  static fn (array $entry): bool => str_contains((string) $entry['query'], 'MAX(nfd.changed)'),
);
product_search_query_assert(count($search_queries) === 1, 'The completed product lookup query must execute exactly once.');

$transaction = Database::getConnection()->startTransaction();
try {
  $database = Database::getConnection();
  $max_nid_query = $database->select('node_field_data', 'nfd');
  $max_nid_query->addExpression('MAX(nfd.nid)');
  $first_nid = ((int) $max_nid_query->execute()->fetchField()) + 1000;
  $timestamp = time();
  $base_record = [
    'type' => 'product',
    'langcode' => 'hu',
    'status' => 1,
    'uid' => 0,
    'created' => $timestamp,
    'changed' => $timestamp,
    'promote' => 0,
    'sticky' => 0,
    'default_langcode' => 1,
  ];
  $database->insert('node_field_data')
    ->fields($base_record + [
      'nid' => $first_nid,
      'vid' => $first_nid,
      'title' => 'Fűmag Hermes többkulcsszavas teszt',
    ])
    ->execute();
  $database->insert('node_field_data')
    ->fields($base_record + [
      'nid' => $first_nid + 1,
      'vid' => $first_nid + 1,
      'title' => 'Kalapács Hermes többkulcsszavas teszt',
    ])
    ->execute();

  $request = Request::create('/search-product/?keyword=fűmag+kalapács');
  $keyword = (string) $request->query->get('keyword');
  product_search_query_assert($keyword === 'fűmag kalapács', 'Symfony must decode the URL + separator to a space.');

  $multi_keyword_results = $find_method->invoke($controller, $keyword);
  product_search_query_assert(in_array($first_nid, $multi_keyword_results, TRUE), 'A multi-keyword search must include the fűmag product.');
  product_search_query_assert(in_array($first_nid + 1, $multi_keyword_results, TRUE), 'A multi-keyword search must include the kalapács product.');

  print "PASS: product lookup applies node access, executes once, and combines + separated keywords with OR.\n";
}
finally {
  $transaction->rollBack();
}
