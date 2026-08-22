<?php

declare(strict_types=1);

use Drupal\product_search\Controller\SearchStatisticsController;

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_statistics_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$route = \Drupal::service('router.route_provider')
  ->getRouteByName('product_search.statistics');
product_search_statistics_assert(
  $route->getRequirement('_permission') === 'view product search statistics',
  'The statistics route should require its dedicated permission.',
);
product_search_statistics_assert(
  $route->getDefault('_title') === 'Keresési statisztika',
  'The statistics page should have a Hungarian title.',
);

$database = \Drupal::database();
$transaction = $database->startTransaction();
try {
  $database->delete('product_search_query_log')->execute();
  $analytics = \Drupal::service('product_search.search_analytics');
  $analytics->log('tej keresés', 4);
  $analytics->log('Tej keresés', 0);

  $controller = \Drupal::service('class_resolver')
    ->getInstanceFromDefinition(SearchStatisticsController::class);
  $build = $controller->page();
  $output = (string) \Drupal::service('renderer')->renderRoot($build);

  product_search_statistics_assert(str_contains($output, 'Összes keresés'), 'The page should show summary statistics.');
  product_search_statistics_assert(str_contains($output, 'tej keresés'), 'The page should list aggregated search terms.');
  product_search_statistics_assert(str_contains($output, 'Találat nélküli keresések'), 'The page should report searches without results.');

  print "PASS: the protected statistics page renders aggregated search data.\n";
}
finally {
  $transaction->rollBack();
}
