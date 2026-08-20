<?php

namespace Drupal\public_holiday;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Imports Hungarian public holidays from szunetnapok.hu.
 */
class PublicHolidayImporter {

  /**
   * The holiday API endpoint.
   */
  private const API_URL = 'https://szunetnapok.hu/api/';

  /**
   * The lock name used to prevent concurrent imports.
   */
  private const LOCK_NAME = 'public_holiday.import';

  /**
   * Constructs a PublicHolidayImporter instance.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected Connection $database,
    protected StateInterface $state,
    protected TimeInterface $time,
    protected LockBackendInterface $lock,
    protected LoggerInterface $logger,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
  }

  /**
   * Checks whether an API key is available.
   *
   * @return bool
   *   TRUE when the API key is configured.
   */
  public function hasApiKey(): bool {
    return $this->getApiKey() !== '';
  }

  /**
   * Checks whether the three-month API request interval has elapsed.
   *
   * @return bool
   *   TRUE when another API request may be made.
   */
  public function isRefreshDue(): bool {
    $last_request = (int) $this->state->get('public_holiday.last_api_request', 0);
    $next_request = $last_request > 0
      ? (new \DateTimeImmutable('@' . $last_request))->modify('+3 months')->getTimestamp()
      : 0;

    return $next_request <= $this->time->getCurrentTime();
  }

  /**
   * Downloads and replaces the stored holiday data for a year.
   *
   * @param int $year
   *   The four-digit year to refresh.
   *
   * @return int
   *   The number of imported day records.
   */
  public function refreshYear(int $year): int {
    if ($year < 2000 || $year > 2100) {
      throw new RuntimeException('The requested holiday year is invalid.');
    }

    $api_key = $this->getApiKey();
    if ($api_key === '') {
      throw new RuntimeException('The public holiday API key is not configured.');
    }

    if (!$this->lock->acquire(self::LOCK_NAME, 120.0)) {
      throw new RuntimeException('A public holiday import is already running.');
    }

    try {
      // The cron pre-check may have cached State values before this process
      // acquired the lock. Reload them so a concurrent completed import cannot
      // be followed by a second request from stale per-request state.
      $this->state->resetCache();
      if (!$this->isRefreshDue()) {
        throw new RuntimeException('The public holiday data may only be refreshed once every three months.');
      }

      $request_time = $this->time->getCurrentTime();
      // Record the attempt before the network call so a failed endpoint cannot
      // be retried on every cron run and exceed the requested quarterly rate.
      $this->state->set('public_holiday.last_api_request', $request_time);

      $response = $this->httpClient->request('POST', self::API_URL, [
        'form_params' => [
          'year' => $year,
          'api_key' => $api_key,
          'type' => 'json',
        ],
        'headers' => [
          'Accept' => 'application/json',
        ],
        'timeout' => 20,
      ]);

      $data = Json::decode((string) $response->getBody());
      $records = $this->validateResponse($data, $year, $request_time);
      $this->replaceYear($year, $records);
      $this->cacheTagsInvalidator->invalidateTags(['public_holiday:data']);

      $this->state->set('public_holiday.last_refresh', $request_time);
      $this->state->set('public_holiday.last_year', $year);
      $this->logger->info('Imported @count public holiday records for @year.', [
        '@count' => count($records),
        '@year' => $year,
      ]);

      return count($records);
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
  }

  /**
   * Returns the API key stored outside exported configuration.
   */
  protected function getApiKey(): string {
    return trim((string) $this->state->get('public_holiday.api_key', ''));
  }

  /**
   * Validates and normalizes an API response.
   *
   * @return array<int, array<string, int|string>>
   *   Validated database records.
   */
  protected function validateResponse(mixed $data, int $year, int $fetched): array {
    if (!is_array($data) || ($data['response'] ?? NULL) !== 'OK') {
      $comment = is_array($data) ? (string) ($data['comment'] ?? '') : '';
      throw new RuntimeException('The holiday API returned an error.' . ($comment !== '' ? ' ' . $comment : ''));
    }

    if ((int) ($data['year'] ?? 0) !== $year || empty($data['days']) || !is_array($data['days'])) {
      throw new RuntimeException('The holiday API response did not contain the requested year and day list.');
    }

    $records = [];
    foreach ($data['days'] as $day) {
      if (!is_array($day)) {
        throw new RuntimeException('The holiday API returned an invalid day record.');
      }

      $date = (string) ($day['date'] ?? '');
      $date_object = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
      $type = (int) ($day['type'] ?? 0);
      $weekday = (int) ($day['weekday'] ?? 0);
      $name = trim((string) ($day['name'] ?? ''));

      if (!$date_object || $date_object->format('Y-m-d') !== $date || (int) $date_object->format('Y') !== $year) {
        throw new RuntimeException('The holiday API returned an invalid date.');
      }
      if ($name === '' || !in_array($type, [1, 2], TRUE) || $weekday < 1 || $weekday > 7) {
        throw new RuntimeException('The holiday API returned invalid holiday details.');
      }

      $identity = $date . ':' . $type;
      $records[$identity] = [
        'year' => $year,
        'date' => $date,
        'name' => mb_substr($name, 0, 255),
        'type' => $type,
        'weekday' => $weekday,
        'fetched' => $fetched,
      ];
    }

    return array_values($records);
  }

  /**
   * Atomically replaces all records for a year.
   *
   * @param array<int, array<string, int|string>> $records
   *   The validated records to insert.
   */
  protected function replaceYear(int $year, array $records): void {
    $transaction = $this->database->startTransaction();

    try {
      $this->database->delete('public_holiday')
        ->condition('year', $year)
        ->execute();

      $insert = $this->database->insert('public_holiday')
        ->fields(['year', 'date', 'name', 'type', 'weekday', 'fetched']);

      foreach ($records as $record) {
        $insert->values($record);
      }
      $insert->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
