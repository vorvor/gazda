<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Fetches and normalizes WordPress Tribe Events API records.
 */
final class TribeEventsSource {

  private const MAX_PAGES = 20;

  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Fetches all current and future records from one configured endpoint.
   *
   * @param array{name: string, endpoint: string, priority: int, fallback_place: string, website: string} $configuration
   *   Source definition.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized events.
   */
  public function fetch(array $configuration, ?\DateTimeImmutable $from = NULL): array {
    $from ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Budapest'));
    $records = [];
    $totalPages = 1;
    for ($page = 1; $page <= min($totalPages, self::MAX_PAGES); $page++) {
      $response = $this->httpClient->request('GET', $configuration['endpoint'], [
        'connect_timeout' => 10,
        'timeout' => 30,
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'Setaljbe cultural program importer',
        ],
        'query' => [
          'page' => $page,
          'per_page' => 50,
          'start_date' => $from->setTimezone(new \DateTimeZone('Europe/Budapest'))->format('Y-m-d 00:00:00'),
        ],
      ]);
      $json = (string) $response->getBody();
      $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        throw new RuntimeException('The Tribe Events endpoint returned an invalid response.');
      }
      $totalPages = max(1, (int) ($data['total_pages'] ?? 1));
      $records = array_merge($records, $this->extract($json, $configuration));
    }
    return $records;
  }

  /**
   * Normalizes one Tribe Events JSON response.
   *
   * @param array{name: string, endpoint: string, priority: int, fallback_place: string, website: string} $configuration
   *   Source definition.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized events.
   */
  public function extract(string $json, array $configuration): array {
    $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!isset($data['events']) || !is_array($data['events'])) {
      throw new RuntimeException('The Tribe Events response contains no event collection.');
    }

    $records = [];
    foreach ($data['events'] as $event) {
      if (!is_array($event) || empty($event['id']) || empty($event['title']) || empty($event['url']) || empty($event['utc_start_date'])) {
        continue;
      }
      $start = $this->parseUtcDate((string) $event['utc_start_date']);
      if ($start === NULL) {
        continue;
      }
      $end = !empty($event['utc_end_date']) ? $this->parseUtcDate((string) $event['utc_end_date']) : NULL;
      $venue = is_array($event['venue'] ?? NULL) ? $event['venue'] : [];
      $categories = [];
      foreach (($event['categories'] ?? []) as $category) {
        $name = is_array($category) ? trim((string) ($category['name'] ?? '')) : '';
        if ($name !== '') {
          $categories[] = $this->plainText($name);
        }
      }
      $title = $this->plainText((string) $event['title']);
      $sourceUrl = $this->validUrl((string) $event['url']);
      if ($title === '' || $sourceUrl === '') {
        continue;
      }
      $placeName = $this->plainText((string) ($venue['venue'] ?? $configuration['fallback_place']));
      if ($placeName === '') {
        $placeName = $configuration['fallback_place'];
      }

      $records[] = new ProgramRecord(
        sourceName: $configuration['name'],
        externalId: (string) $event['id'],
        priority: (int) $configuration['priority'],
        title: $title,
        description: $this->plainText((string) ($event['description'] ?? $event['excerpt'] ?? '')),
        start: $start,
        end: $end,
        allDay: (bool) ($event['all_day'] ?? FALSE),
        placeName: $placeName,
        placeAddress: $this->venueAddress($venue),
        placeWebsite: $this->validUrl((string) ($venue['website'] ?? $configuration['website'])),
        sourceUrl: $sourceUrl,
        ticketUrl: $this->validUrl((string) ($event['website'] ?? '')),
        price: $this->plainText((string) ($event['cost'] ?? '')),
        categories: array_values(array_unique($categories)),
        family: $this->isFamilyEvent($categories, $title),
        status: $this->eventStatus($event, $title),
        sourceUpdated: !empty($event['modified_utc']) ? $this->parseUtcDate((string) $event['modified_utc']) : NULL,
      );
    }
    return $records;
  }

  /**
   * Parses a WordPress UTC date.
   */
  private function parseUtcDate(string $value): ?\DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
    return $date instanceof \DateTimeImmutable ? $date : NULL;
  }

  /**
   * Converts source HTML into compact readable plain text.
   */
  private function plainText(string $value): string {
    $value = preg_replace('#<(?:br|/p|/div|/li|/h[1-6])\b[^>]*>#i', "\n", $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_map(static fn(string $line): string => trim((string) preg_replace('/[\t ]+/u', ' ', $line)), preg_split('/\R+/u', $value) ?: []);
    return trim(implode("\n", array_values(array_filter($lines, static fn(string $line): bool => $line !== ''))));
  }

  /**
   * Builds one Hungarian-style venue address.
   */
  private function venueAddress(array $venue): string {
    $locality = trim(implode(' ', array_filter([
      $this->plainText((string) ($venue['zip'] ?? '')),
      $this->plainText((string) ($venue['city'] ?? '')),
    ])));
    $address = $this->plainText((string) ($venue['address'] ?? ''));
    return implode(', ', array_filter([$locality, $address]));
  }

  /**
   * Accepts only explicit HTTP(S) source links.
   */
  private function validUrl(string $value): string {
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? $value : '';
  }

  /**
   * Detects family-oriented source categories conservatively.
   */
  private function isFamilyEvent(array $categories, string $title): bool {
    $haystack = mb_strtolower(implode(' ', $categories) . ' ' . $title);
    return preg_match('/gyerek|gyermek|család|kölyök|baba|mese|tábor/u', $haystack) === 1;
  }

  /**
   * Maps explicit source state and title markers to local status values.
   */
  private function eventStatus(array $event, string $title): string {
    $sourceStatus = mb_strtolower((string) ($event['event_status'] ?? ''));
    $normalizedTitle = mb_strtolower($title);
    if ($sourceStatus === 'canceled' || str_contains($normalizedTitle, 'elmarad')) {
      return 'cancelled';
    }
    if ($sourceStatus === 'postponed' || str_contains($normalizedTitle, 'elhalaszt')) {
      return 'postponed';
    }
    if (str_contains($normalizedTitle, 'teltház')) {
      return 'sold_out';
    }
    return 'scheduled';
  }

}
