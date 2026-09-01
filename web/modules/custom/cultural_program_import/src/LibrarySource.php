<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Extracts dated events from the Hamvas Béla library's Drupal Views.
 */
final class LibrarySource {

  private const BASE_URL = 'https://hbpmk.hu';
  private const SOURCE_URL = 'https://hbpmk.hu/elmeny';

  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Fetches current and future library events.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized library events.
   */
  public function fetch(?\DateTimeImmutable $from = NULL): array {
    $response = $this->httpClient->request('GET', self::SOURCE_URL, [
      'connect_timeout' => 10,
      'timeout' => 30,
      'headers' => ['User-Agent' => 'Setaljbe cultural program importer'],
    ]);
    return $this->extract((string) $response->getBody(), $from);
  }

  /**
   * Extracts dated event cards from server-rendered HTML.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized library events.
   */
  public function extract(string $html, ?\DateTimeImmutable $from = NULL): array {
    $from ??= new \DateTimeImmutable('today', new \DateTimeZone('Europe/Budapest'));
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      throw new RuntimeException('The library events page is not valid HTML.');
    }
    $xpath = new DOMXPath($document);
    $records = [];
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " rendezvenyek-elemek-sor ")]') ?: [] as $card) {
      if (!$card instanceof DOMElement) {
        continue;
      }
      $time = $xpath->query('.//time[@datetime]', $card)->item(0);
      $titleLink = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " views-field-title ")]//a[@href]', $card)->item(0);
      if (!$time instanceof DOMElement || !$titleLink instanceof DOMElement) {
        continue;
      }
      try {
        $start = new \DateTimeImmutable($time->getAttribute('datetime'));
      }
      catch (\Exception) {
        continue;
      }
      if ($start < $from) {
        continue;
      }
      $title = $this->text($titleLink->textContent);
      $sourceUrl = $this->absoluteUrl($titleLink->getAttribute('href'));
      if ($title === '' || $sourceUrl === '') {
        continue;
      }
      $body = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " views-field-body ")]//*[contains(concat(" ", normalize-space(@class), " "), " field-content ")]', $card)->item(0);
      $sectionTitle = $xpath->query('ancestor::section[1]//*[contains(concat(" ", normalize-space(@class), " "), " block-title ")][1]', $card)->item(0);
      $section = $sectionTitle !== NULL ? $this->text($sectionTitle->textContent) : '';
      $categories = ['Könyvtári program'];
      if ($section !== '') {
        $categories[] = $section;
      }
      $familyHaystack = mb_strtolower($section . ' ' . $title);

      $records[] = new ProgramRecord(
        sourceName: 'Hamvas Béla Pest Megyei Könyvtár',
        externalId: hash('sha256', $sourceUrl),
        priority: 100,
        title: $title,
        description: $body !== NULL ? $this->text($body->textContent) : '',
        start: $start,
        end: NULL,
        allDay: FALSE,
        placeName: 'Hamvas Béla Pest Megyei Könyvtár',
        placeAddress: '2000 Szentendre, Pátriárka utca 7.',
        placeWebsite: self::BASE_URL . '/',
        sourceUrl: $sourceUrl,
        ticketUrl: '',
        price: '',
        categories: $categories,
        family: preg_match('/gyermek|gyerek|ifjúsági|mese|család|tábor/u', $familyHaystack) === 1,
        status: 'scheduled',
      );
    }
    return $records;
  }

  /**
   * Normalizes visible card text.
   */
  private function text(string $value): string {
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Resolves a library path against the canonical host.
   */
  private function absoluteUrl(string $url): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (str_starts_with($url, '/')) {
      $url = self::BASE_URL . $url;
    }
    elseif (!preg_match('#^https?://#i', $url)) {
      $url = self::BASE_URL . '/' . ltrim($url, '/');
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
  }

}
