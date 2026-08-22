<?php

declare(strict_types=1);

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\mysql\Driver\Database\mysql\Connection as MysqlConnection;
use Drupal\product_search\Controller\ProductSearchController;
use Drupal\product_search\SearchAnalytics;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;

/**
 * Database seam that preserves schema discovery but forces analytics inserts to fail.
 */
final class ProductSearchFailingInsertConnection extends MysqlConnection {

  public function __construct(private readonly object $realSchema) {}

  public function schema() {
    return $this->realSchema;
  }

  public function insert($table, array $options = []) {
    throw new RuntimeException('forced analytics failure');
  }

}

/**
 * Database seam that inserts normally but forces retention pruning to fail.
 */
final class ProductSearchFailingPruneConnection extends MysqlConnection {

  public function __construct(private readonly MysqlConnection $realConnection) {}

  public function schema() {
    return $this->realConnection->schema();
  }

  public function insert($table, array $options = []) {
    return $this->realConnection->insert($table, $options);
  }

  public function delete($table, array $options = []) {
    throw new RuntimeException('forced retention failure');
  }

}

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_failure_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
$secret_term = 'secret-user-search-term';
$logger = new class extends AbstractLogger {
  public array $records = [];

  public function log($level, string|Stringable $message, array $context = []): void {
    $this->records[] = [$level, $message, $context];
  }
};
$analytics = new SearchAnalytics(
  new ProductSearchFailingInsertConnection($database->schema()),
  \Drupal::time(),
  \Drupal::service('flood'),
  \Drupal::lock(),
  \Drupal::service('request_stack'),
  \Drupal::service('private_key'),
  $logger,
);
$controller = new ProductSearchController(
  $database,
  \Drupal::service('renderer'),
  \Drupal::entityTypeManager(),
  \Drupal::service('file_url_generator'),
  $analytics,
  \Drupal::service('csrf_token'),
);

$flood = \Drupal::service('flood');
$insert_identifier = hash_hmac('sha256', '192.0.2.55', \Drupal::service('private_key')->get());
$insert_transaction = $database->startTransaction();
try {
  $flood->register('product_search.analytics', 3600, $insert_identifier);
  $flood_count_before = (int) $database->select('flood', 'f')
    ->condition('event', 'product_search.analytics')
    ->condition('identifier', $insert_identifier)
    ->countQuery()
    ->execute()
    ->fetchField();
  $csrf_token = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $response = $controller->ajaxSearch(Request::create(
    '/search-product/ajax',
    'POST',
    ['q' => $secret_term],
    [],
    [],
    [
      'REMOTE_ADDR' => '192.0.2.55',
      'HTTP_X_CSRF_TOKEN' => $csrf_token,
    ],
  ));

  product_search_failure_assert($response->getStatusCode() === 200, 'An analytics insert failure must not fail a successful search response.');
  product_search_failure_assert(count($logger->records) === 1, 'An analytics failure should emit one warning.');
  $serialized_records = serialize($logger->records);
  product_search_failure_assert(!str_contains($serialized_records, $secret_term), 'The safe warning must not contain the user search term.');
  product_search_failure_assert($logger->records[0][0] === 'warning', 'The analytics failure should be logged at warning level.');
  $flood_count_after = (int) $database->select('flood', 'f')
    ->condition('event', 'product_search.analytics')
    ->condition('identifier', $insert_identifier)
    ->countQuery()
    ->execute()
    ->fetchField();
  product_search_failure_assert($flood_count_after === $flood_count_before, 'An analytics insert failure must preserve existing flood state.');
}
finally {
  $insert_transaction->rollBack();
}

$prune_logger = clone $logger;
$prune_logger->records = [];
$prune_analytics = new SearchAnalytics(
  new ProductSearchFailingPruneConnection($database),
  \Drupal::time(),
  \Drupal::service('flood'),
  \Drupal::lock(),
  \Drupal::service('request_stack'),
  \Drupal::service('private_key'),
  $prune_logger,
);
$transaction = $database->startTransaction();
try {
  $before_prune_failure = (int) $database->select('product_search_query_log', 'log')
    ->condition('normalized_term', $secret_term)
    ->countQuery()
    ->execute()
    ->fetchField();
  $prune_analytics->log($secret_term, 0, '192.0.2.56');
  $after_prune_failure = (int) $database->select('product_search_query_log', 'log')
    ->condition('normalized_term', $secret_term)
    ->countQuery()
    ->execute()
    ->fetchField();
  product_search_failure_assert(count($prune_logger->records) === 1, 'A retention failure should emit one warning without escaping.');
  product_search_failure_assert(!str_contains(serialize($prune_logger->records), $secret_term), 'The retention warning must not contain the user search term.');
  product_search_failure_assert($after_prune_failure === $before_prune_failure, 'A retention failure must not insert a row beyond the hard cap.');
}
finally {
  $transaction->rollBack();
}

print "PASS: analytics insert and pruning failures fail open and emit term-free warnings.\n";
