<?php

namespace Drupal\product_search;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\PrivateKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and aggregates product-search terms without visitor identifiers.
 */
final class SearchAnalytics {

  private const TABLE = 'product_search_query_log';

  private const MAX_TERM_LENGTH = 255;

  private const FLOOD_EVENT = 'product_search.analytics';

  private const FLOOD_LIMIT = 60;

  private const FLOOD_WINDOW = 3600;

  private const RETENTION_SECONDS = 90 * 86400;

  private const MAX_ROWS = 10000;

  private const WRITE_LOCK = 'product_search.analytics_write';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly RequestStack $requestStack,
    private readonly PrivateKey $privateKey,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Stores a search term when it contains more than three characters.
   */
  public function log(string $search_term, int $result_count, ?string $client_address = NULL): void {
    $lock_acquired = FALSE;

    try {
      if (!$this->database->schema()->tableExists(self::TABLE)) {
        return;
      }

      $search_term = $this->clean($search_term);
      if (mb_strlen($search_term) <= 3) {
        return;
      }

      $client_address ??= $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
      $identifier = hash_hmac('sha256', $client_address, $this->privateKey->get());
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $identifier)) {
        return;
      }

      $search_term = mb_substr($search_term, 0, self::MAX_TERM_LENGTH);
      $normalized_term = mb_substr(
        mb_strtolower($search_term),
        0,
        self::MAX_TERM_LENGTH,
      );

      if (!$this->lock->acquire(self::WRITE_LOCK, 30.0)) {
        return;
      }
      $lock_acquired = TRUE;

      // Recheck under the write lock so concurrent requests cannot race past
      // the per-client flood threshold before either event is registered.
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $identifier)) {
        return;
      }

      $this->prepareForInsert();
      $this->database->insert(self::TABLE)
        ->fields([
          'search_term' => $search_term,
          'normalized_term' => $normalized_term,
          'result_count' => max(0, $result_count),
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW, $identifier);
    }
    catch (\Throwable) {
      // Search results must not depend on optional analytics persistence.
      $this->logger->warning('Product search analytics write or retention maintenance failed.');
    }
    finally {
      if ($lock_acquired) {
        try {
          $this->lock->release(self::WRITE_LOCK);
        }
        catch (\Throwable) {
          $this->logger->warning('Product search analytics lock release failed.');
        }
      }
    }
  }

  /**
   * Makes room under the retention policy and hard cap before inserting.
   */
  private function prepareForInsert(): void {
    $this->database->delete(self::TABLE)
      ->condition('created', $this->time->getRequestTime() - self::RETENTION_SECONDS, '<')
      ->execute();

    $row_count = (int) $this->database->select(self::TABLE, 'log')
      ->countQuery()
      ->execute()
      ->fetchField();
    $rows_to_delete = $row_count - self::MAX_ROWS + 1;
    if ($rows_to_delete <= 0) {
      return;
    }

    $oldest_ids = $this->database->select(self::TABLE, 'log')
      ->fields('log', ['id'])
      ->orderBy('created', 'ASC')
      ->orderBy('id', 'ASC')
      ->range(0, $rows_to_delete)
      ->execute()
      ->fetchCol();
    $this->database->delete(self::TABLE)
      ->condition('id', $oldest_ids, 'IN')
      ->execute();
  }

  /**
   * Returns search terms ordered by popularity and recency.
   *
   * @return array<int, object>
   *   Aggregated search-statistic records.
   */
  public function getStatistics(int $limit = 200): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return [];
    }

    $query = $this->database->select(self::TABLE, 'log');
    $query->addField('log', 'normalized_term');
    $query->addExpression('COUNT(*)', 'search_count');
    $query->addExpression('SUM(CASE WHEN log.result_count = 0 THEN 1 ELSE 0 END)', 'no_result_count');
    $query->addExpression('AVG(log.result_count)', 'average_result_count');
    $query->addExpression('MAX(log.created)', 'last_searched');
    $query->groupBy('log.normalized_term');
    $query->orderBy('search_count', 'DESC');
    $query->orderBy('last_searched', 'DESC');
    $query->range(0, max(1, $limit));

    return $query->execute()->fetchAll();
  }

  /**
   * Returns summary totals for the statistics page.
   *
   * @return array{total_searches: int, unique_terms: int, no_result_searches: int}
   *   Search totals.
   */
  public function getSummary(): array {
    $empty = [
      'total_searches' => 0,
      'unique_terms' => 0,
      'no_result_searches' => 0,
    ];

    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return $empty;
    }

    $query = $this->database->select(self::TABLE, 'log');
    $query->addExpression('COUNT(*)', 'total_searches');
    $query->addExpression('COUNT(DISTINCT log.normalized_term)', 'unique_terms');
    $query->addExpression('SUM(CASE WHEN log.result_count = 0 THEN 1 ELSE 0 END)', 'no_result_searches');
    $result = $query->execute()->fetchAssoc() ?: [];

    return [
      'total_searches' => (int) ($result['total_searches'] ?? 0),
      'unique_terms' => (int) ($result['unique_terms'] ?? 0),
      'no_result_searches' => (int) ($result['no_result_searches'] ?? 0),
    ];
  }

  /**
   * Trims a term and collapses repeated whitespace.
   */
  private function clean(string $search_term): string {
    $search_term = preg_replace('/\s+/u', ' ', $search_term) ?? $search_term;
    return trim($search_term);
  }

}
