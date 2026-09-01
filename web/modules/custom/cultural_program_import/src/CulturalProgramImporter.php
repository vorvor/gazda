<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use Drupal\Core\Lock\LockBackendInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Coordinates isolated source fetches and one authoritative synchronization.
 */
final class CulturalProgramImporter {

  private const LOCK_NAME = 'cultural_program_import:all';

  public function __construct(
    private readonly ProgramSynchronizer $synchronizer,
    private readonly ProgramSourceRegistryInterface $sourceRegistry,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Imports one source or every configured source.
   *
   * A source failure is reported but does not discard records fetched from
   * independent sibling sources. Omitted events remain unchanged.
   *
   * @param string $source
   *   One registry key, or `all`.
   * @param bool $dryRun
   *   Whether entity writes should be skipped.
   * @param \DateTimeImmutable|null $from
   *   Earliest relevant event date.
   *
   * @return array
   *   Per-source extraction, error, and synchronization counts.
   */
  public function import(string $source = 'all', bool $dryRun = FALSE, ?\DateTimeImmutable $from = NULL): array {
    $available = $this->sourceRegistry->keys();
    $selected = $source === 'all' ? $available : [$source];
    foreach ($selected as $key) {
      if (!in_array($key, $available, TRUE)) {
        throw new InvalidArgumentException(sprintf('Unknown cultural program source: %s', $key));
      }
    }
    if (!$this->lock->acquire(self::LOCK_NAME, 900.0)) {
      throw new RuntimeException('Another cultural program import is already running.');
    }

    try {
      $records = [];
      $sourceCounts = [];
      $errors = [];
      foreach ($selected as $key) {
        try {
          $fetched = $this->sourceRegistry->fetch($key, $from);
          $sourceCounts[$key] = count($fetched);
          $records = array_merge($records, $fetched);
        }
        catch (\Throwable $exception) {
          $errors[$key] = $exception->getMessage();
          $this->logger->error('Cultural program source {source} failed: {message}', [
            'source' => $key,
            'message' => $exception->getMessage(),
          ]);
        }
      }

      $result = $this->synchronizer->synchronize($records, $dryRun);
      $result['fetched'] = count($records);
      $result['sources'] = $sourceCounts;
      $result['errors'] = $errors;
      return $result;
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
  }

}
