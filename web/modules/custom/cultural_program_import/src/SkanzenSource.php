<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Extracts dated major events from the official Skanzen Next.js site.
 */
final class SkanzenSource {

  private const BASE_URL = 'https://skanzen.hu/hu/';
  private const LIST_URL = 'https://skanzen.hu/hu/programok/rendezvenyek';

  private const MONTHS = [
    'január' => 1,
    'február' => 2,
    'március' => 3,
    'április' => 4,
    'május' => 5,
    'június' => 6,
    'július' => 7,
    'augusztus' => 8,
    'szeptember' => 9,
    'október' => 10,
    'november' => 11,
    'december' => 12,
  ];

  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Fetches current and future major Skanzen events.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized events with parseable dates.
   */
  public function fetch(?\DateTimeImmutable $from = NULL): array {
    $from ??= new \DateTimeImmutable('today', new \DateTimeZone('Europe/Budapest'));
    $listHtml = $this->download(self::LIST_URL);
    $data = $this->nextData($listHtml);
    $items = [];
    foreach (($data['props']['pageProps']['dehydratedState']['queries'] ?? []) as $query) {
      foreach (($query['state']['data']['pages'] ?? []) as $page) {
        $items = array_merge($items, $page['response']['getPostList']['body']['list'] ?? []);
      }
    }

    $records = [];
    foreach (array_slice($items, 0, 20) as $item) {
      $slug = is_array($item) ? trim((string) ($item['slug'] ?? '')) : '';
      if ($slug === '') {
        continue;
      }
      $url = self::BASE_URL . ltrim($slug, '/');
      $record = $this->extractDetail($this->download($url), $url);
      if ($record !== NULL && ($record->end ?? $record->start) >= $from) {
        $records[] = $record;
      }
    }
    return $records;
  }

  /**
   * Extracts one Skanzen detail page.
   */
  public function extractDetail(string $html, string $sourceUrl): ?ProgramRecord {
    $data = $this->nextData($html);
    $body = NULL;
    foreach (($data['props']['pageProps']['dehydratedState']['queries'] ?? []) as $query) {
      $candidate = $query['state']['data']['response']['getPost']['body'] ?? NULL;
      if (is_array($candidate)) {
        $body = $candidate;
        break;
      }
    }
    $post = is_array($body['post'] ?? NULL) ? $body['post'] : [];
    if (empty($post['id']) || empty($post['title']) || empty($post['lead'])) {
      return NULL;
    }

    $lead = $this->plainText((string) $post['lead']);
    $dateRange = $this->parseHungarianDateRange($lead, (string) ($post['publishedAt'] ?? ''));
    if ($dateRange === NULL) {
      return NULL;
    }
    [$startDate, $endDate] = $dateRange;
    $featuredText = '';
    foreach (($post['content']['sections'] ?? []) as $section) {
      foreach (($section['blocks'] ?? []) as $block) {
        if (($block['blockType'] ?? '') === 'FeaturedText') {
          $featuredText .= ' ' . $this->plainText((string) ($block['blockBody'] ?? ''));
        }
      }
    }
    $times = $this->parseTimeRange($featuredText);
    $allDay = $times === NULL;
    [$startHour, $startMinute, $endHour, $endMinute] = $times ?? [0, 0, 23, 59];
    $timezone = new \DateTimeZone('Europe/Budapest');
    $start = new \DateTimeImmutable(sprintf('%s %02d:%02d:00', $startDate, $startHour, $startMinute), $timezone);
    $end = new \DateTimeImmutable(sprintf('%s %02d:%02d:00', $endDate, $endHour, $endMinute), $timezone);
    $title = $this->plainText((string) $post['title']);
    $subcategory = $this->plainText((string) ($body['subcategory']['title'] ?? 'Rendezvények'));
    $familyText = mb_strtolower($title . ' ' . $lead . ' ' . (string) ($post['metaDescription'] ?? ''));

    return new ProgramRecord(
      sourceName: 'Skanzen',
      externalId: (string) $post['id'],
      priority: 100,
      title: $title,
      description: $lead,
      start: $start,
      end: $end,
      allDay: $allDay,
      placeName: 'Skanzen',
      placeAddress: '2000 Szentendre, Sztaravodai út 75.',
      placeWebsite: 'https://skanzen.hu/',
      sourceUrl: $sourceUrl,
      ticketUrl: '',
      price: '',
      categories: array_values(array_unique(array_filter(['Skanzen', $subcategory]))),
      family: preg_match('/család|gyerek|gyermek|kézműves|minden korosztály|tábor/u', $familyText) === 1,
      status: 'scheduled',
      sourceUpdated: $this->parseIsoDate((string) ($post['publishedAt'] ?? '')),
    );
  }

  /**
   * Downloads one bounded public page.
   */
  private function download(string $url): string {
    $response = $this->httpClient->request('GET', $url, [
      'connect_timeout' => 10,
      'timeout' => 30,
      'headers' => ['User-Agent' => 'Setaljbe cultural program importer'],
    ]);
    $html = (string) $response->getBody();
    if ($html === '' || strlen($html) > 5_000_000) {
      throw new RuntimeException('The Skanzen page is empty or too large.');
    }
    return $html;
  }

  /**
   * Reads the server-rendered Next.js payload.
   */
  private function nextData(string $html): array {
    if (!preg_match('#<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>#si', $html, $match)) {
      throw new RuntimeException('The Skanzen page contains no Next.js data payload.');
    }
    $data = json_decode($match[1], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      throw new RuntimeException('The Skanzen Next.js payload is invalid.');
    }
    return $data;
  }

  /**
   * Parses Hungarian source date wording into ISO local dates.
   *
   * @return array{0: string, 1: string}|null
   *   Start and end dates as Y-m-d.
   */
  private function parseHungarianDateRange(string $text, string $publishedAt): ?array {
    $months = implode('|', array_map('preg_quote', array_keys(self::MONTHS)));
    if (preg_match('/(?<year>20\d{2})\.\s*(?<month>' . $months . ')\s*(?<day>\d{1,2})\.?\s*[–-]\s*(?<end_month>' . $months . ')\s*(?<end_day>\d{1,2})/iu', $text, $match)) {
      return [
        $this->dateString((int) $match['year'], self::MONTHS[mb_strtolower($match['month'])], (int) $match['day']),
        $this->dateString((int) $match['year'], self::MONTHS[mb_strtolower($match['end_month'])], (int) $match['end_day']),
      ];
    }
    if (preg_match('/(?<year>20\d{2})\.\s*(?<month>' . $months . ')\s*(?<day>\d{1,2})\.?\s*[–-]\s*(?<end_day>\d{1,2})/iu', $text, $match)) {
      $month = self::MONTHS[mb_strtolower($match['month'])];
      return [
        $this->dateString((int) $match['year'], $month, (int) $match['day']),
        $this->dateString((int) $match['year'], $month, (int) $match['end_day']),
      ];
    }
    if (preg_match('/(?<year>20\d{2})\.\s*(?<month>' . $months . ')\s*(?<day>\d{1,2})/iu', $text, $match)) {
      $date = $this->dateString((int) $match['year'], self::MONTHS[mb_strtolower($match['month'])], (int) $match['day']);
      return [$date, $date];
    }
    $published = $this->parseIsoDate($publishedAt);
    if ($published !== NULL && preg_match('/(?<month>' . $months . ')\s*(?<day>\d{1,2})/iu', $text, $match)) {
      $date = $this->dateString((int) $published->format('Y'), self::MONTHS[mb_strtolower($match['month'])], (int) $match['day']);
      return [$date, $date];
    }
    return NULL;
  }

  /**
   * Parses a displayed time range.
   *
   * @return array{0: int, 1: int, 2: int, 3: int}|null
   *   Start hour/minute and end hour/minute.
   */
  private function parseTimeRange(string $text): ?array {
    if (!preg_match('/(?<start_hour>\d{1,2}):(?<start_minute>\d{2})\s*[–-]\s*(?<end_hour>\d{1,2}):(?<end_minute>\d{2})/u', $text, $match)) {
      return NULL;
    }
    return [(int) $match['start_hour'], (int) $match['start_minute'], (int) $match['end_hour'], (int) $match['end_minute']];
  }

  /**
   * Formats a validated local calendar date.
   */
  private function dateString(int $year, int $month, int $day): string {
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  /**
   * Parses an ISO metadata timestamp.
   */
  private function parseIsoDate(string $value): ?\DateTimeImmutable {
    if ($value === '') {
      return NULL;
    }
    try {
      return new \DateTimeImmutable($value);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Converts source HTML into compact visible text.
   */
  private function plainText(string $value): string {
    $value = preg_replace('#<(?:br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

}
