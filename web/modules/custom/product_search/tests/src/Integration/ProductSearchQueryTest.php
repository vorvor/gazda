<?php

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\product_search\Controller\ProductSearchController;

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

print "PASS: product lookup applies node access and executes once.\n";
