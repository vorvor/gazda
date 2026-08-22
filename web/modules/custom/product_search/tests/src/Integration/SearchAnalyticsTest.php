<?php

declare(strict_types=1);

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_test_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
product_search_test_assert(
  $database->schema()->tableExists('product_search_query_log'),
  'The product search query log table does not exist.',
);

$transaction = $database->startTransaction();
try {
  $database->delete('product_search_query_log')->execute();
  $analytics = \Drupal::service('product_search.search_analytics');

  $analytics->log('  TeJ   keresés  ', 4);
  $analytics->log('tej keresés', 0);
  $analytics->log('abc', 2);
  $analytics->log("\u{00A0}abc\u{00A0}", 1, '192.0.2.61');
  $analytics->log("\u{00A0}abcd\u{00A0}", 1, '192.0.2.62');
  $analytics->log(str_repeat("\u{0130}", 255), 1, '192.0.2.63');

  $rows = $database->select('product_search_query_log', 'log')
    ->fields('log')
    ->orderBy('id')
    ->execute()
    ->fetchAllAssoc('id');

  product_search_test_assert(count($rows) === 4, 'Only cleaned queries longer than three characters should be stored.');
  $first = reset($rows);
  product_search_test_assert($first->search_term === 'TeJ keresés', 'Stored search text should be trimmed and whitespace-normalized.');
  product_search_test_assert($first->normalized_term === 'tej keresés', 'Search text should have a case-insensitive aggregation key.');
  product_search_test_assert((int) $first->result_count === 4, 'The result count should be stored with the search.');
  $unicode_row = end($rows);
  product_search_test_assert(mb_strlen($unicode_row->normalized_term) <= 255, 'Case normalization must not exceed the database column length.');
  product_search_test_assert(
    array_filter($rows, static fn (object $row): bool => $row->search_term === 'abcd') !== [],
    'Leading and trailing Unicode whitespace should be removed.',
  );

  $statistics = $analytics->getStatistics();
  product_search_test_assert(count($statistics) === 3, 'Equivalent search terms should be aggregated while distinct terms remain separate.');
  product_search_test_assert((int) $statistics[0]->search_count === 2, 'The aggregate should count both searches.');
  product_search_test_assert((int) $statistics[0]->no_result_count === 1, 'The aggregate should count searches without results.');

  $summary = $analytics->getSummary();
  product_search_test_assert($summary['total_searches'] === 4, 'The summary should include total searches.');
  product_search_test_assert($summary['unique_terms'] === 3, 'The summary should include unique terms.');
  product_search_test_assert($summary['no_result_searches'] === 1, 'The summary should include no-result searches.');

  print "PASS: product searches are normalized, stored, and aggregated.\n";
}
finally {
  $transaction->rollBack();
}
