<?php

declare(strict_types=1);

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_abuse_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
$transaction = $database->startTransaction();
try {
  $database->delete('product_search_query_log')->execute();
  $flood = \Drupal::service('flood');
  $analytics = \Drupal::service('product_search.search_analytics');
  $client_address = '192.0.2.44';
  $identifier = hash_hmac('sha256', $client_address, \Drupal::service('private_key')->get());
  $flood->clear('product_search.analytics', $identifier);

  for ($i = 0; $i < 61; $i++) {
    $analytics->log('rate limited search ' . $i, 0, $client_address);
  }

  $count = (int) $database->select('product_search_query_log', 'log')
    ->countQuery()
    ->execute()
    ->fetchField();
  product_search_abuse_assert($count === 60, 'A pseudonymous client must be limited to 60 analytics records per hour.');

  $stored_row = $database->select('product_search_query_log', 'log')
    ->fields('log')
    ->range(0, 1)
    ->execute()
    ->fetchObject();
  $columns = array_keys((array) $stored_row);
  product_search_abuse_assert(!array_intersect(['ip', 'ip_address', 'uid', 'user_id', 'session_id', 'identifier'], $columns), 'The analytics table must not contain visitor identifier columns.');

  $old_id = $database->insert('product_search_query_log')
    ->fields([
      'search_term' => 'expired search',
      'normalized_term' => 'expired search',
      'result_count' => 0,
      'created' => \Drupal::time()->getRequestTime() - (91 * 86400),
    ])
    ->execute();
  $analytics->log('retention trigger', 0, '192.0.2.45');
  $expired_exists = (bool) $database->select('product_search_query_log', 'log')
    ->fields('log', ['id'])
    ->condition('id', $old_id)
    ->execute()
    ->fetchField();
  product_search_abuse_assert(!$expired_exists, 'Analytics records older than 90 days must be pruned.');

  $competing_lock = new \Drupal\Core\Lock\DatabaseLockBackend($database);
  product_search_abuse_assert($competing_lock->acquire('product_search.analytics_write', 30.0), 'The test must acquire the analytics write lock.');
  try {
    $count_before_lock = (int) $database->select('product_search_query_log', 'log')
      ->countQuery()
      ->execute()
      ->fetchField();
    $analytics->log('lock contention search', 0, '192.0.2.47');
    $count_after_lock = (int) $database->select('product_search_query_log', 'log')
      ->countQuery()
      ->execute()
      ->fetchField();
    product_search_abuse_assert($count_after_lock === $count_before_lock, 'Analytics must skip a write when the cap lock is unavailable.');
  }
  finally {
    $competing_lock->release('product_search.analytics_write');
  }

  $database->delete('product_search_query_log')->execute();
  $insert = $database->insert('product_search_query_log')
    ->fields(['search_term', 'normalized_term', 'result_count', 'created']);
  for ($i = 0; $i < 10000; $i++) {
    $insert->values(['bounded row', 'bounded row', 0, \Drupal::time()->getRequestTime()]);
  }
  $insert->execute();
  $analytics->log('storage cap trigger', 0, '192.0.2.46');
  $capped_count = (int) $database->select('product_search_query_log', 'log')
    ->countQuery()
    ->execute()
    ->fetchField();
  product_search_abuse_assert($capped_count === 10000, 'The analytics table must be capped at 10,000 rows.');

  $flood_rows = $database->select('flood', 'f')
    ->fields('f', ['identifier'])
    ->condition('event', 'product_search.analytics')
    ->execute()
    ->fetchCol();
  product_search_abuse_assert(!in_array($client_address, $flood_rows, TRUE), 'Flood records must never contain the raw client address.');

  print "PASS: analytics writes are pseudonymously throttled, retained for 90 days, and capped at 10,000 rows.\n";
}
finally {
  $transaction->rollBack();
}
