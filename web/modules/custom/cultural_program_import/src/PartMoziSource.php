<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Extracts the dated P’Art Mozi weekly screening schedule.
 */
final class PartMoziSource {

  private const SOURCE_URL = 'https://partmozi.hu/';

  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Fetches the current cinema schedule.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   One record per bookable screening.
   */
  public function fetch(): array {
    $response = $this->httpClient->request('GET', self::SOURCE_URL, [
      'connect_timeout' => 10,
      'timeout' => 30,
      'headers' => ['User-Agent' => 'Setaljbe cultural program importer'],
    ]);
    return $this->extract((string) $response->getBody());
  }

  /**
   * Extracts one server-rendered weekly schedule.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   One record per bookable screening.
   */
  public function extract(string $html): array {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      throw new RuntimeException('The P’Art Mozi schedule is not valid HTML.');
    }
    $xpath = new DOMXPath($document);
    $dates = [];
    foreach ($xpath->query('//*[@id="day-tabs-wrapper"]//*[@data-tab and @data-date]') ?: [] as $tab) {
      if (!$tab instanceof DOMElement || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tab->getAttribute('data-date'))) {
        continue;
      }
      $dates[$tab->getAttribute('data-tab')] = $tab->getAttribute('data-date');
    }

    $records = [];
    foreach ($dates as $tab => $date) {
      $day = $xpath->query('//*[@id="day-tab-wrapper"]/*[contains(concat(" ", normalize-space(@class), " "), " tab-' . (int) $tab . ' ")]')->item(0);
      if (!$day instanceof DOMElement) {
        continue;
      }
      foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " movie-box ")]', $day) ?: [] as $movie) {
        if (!$movie instanceof DOMElement) {
          continue;
        }
        $titleLink = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " title ")]/a[@href]', $movie)->item(0);
        if (!$titleLink instanceof DOMElement) {
          continue;
        }
        $relativeUrl = trim($titleLink->getAttribute('href'));
        if (!preg_match('/-(\d+)\/?$/', $relativeUrl, $matches)) {
          continue;
        }
        $movieId = $matches[1];
        $title = $this->text($titleLink->textContent);
        $sourceUrl = $this->absoluteUrl($relativeUrl);
        $descriptionNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " description ")]', $movie)->item(0);
        $description = $descriptionNode instanceof DOMNode ? $this->textWithoutUi($descriptionNode) : '';
        $genres = [];
        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " genre ")]', $movie) ?: [] as $genre) {
          $name = $this->text($genre->textContent);
          if ($name !== '') {
            $genres[] = $name;
          }
        }
        $genres = array_values(array_unique($genres));

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " movie-time ")]/a[@href]', $movie) ?: [] as $screening) {
          if (!$screening instanceof DOMElement) {
            continue;
          }
          $timeNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " time ")]//em', $screening)->item(0);
          $time = $timeNode instanceof DOMNode ? $this->text($timeNode->textContent) : '';
          $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time", new \DateTimeZone('Europe/Budapest'));
          $ticketUrl = $this->absoluteUrl(trim($screening->getAttribute('href')));
          if (!$start instanceof \DateTimeImmutable || $ticketUrl === '') {
            continue;
          }
          $records[] = new ProgramRecord(
            sourceName: 'P’Art Mozi',
            externalId: "$movieId:$date:$time",
            priority: 100,
            title: $title,
            description: $description,
            start: $start,
            end: NULL,
            allDay: FALSE,
            placeName: 'P’Art Mozi',
            placeAddress: '2000 Szentendre, Dunakorzó 25.',
            placeWebsite: self::SOURCE_URL,
            sourceUrl: $sourceUrl,
            ticketUrl: $ticketUrl,
            price: '',
            categories: array_merge(['Mozi'], $genres),
            family: preg_match('/családi|gyerek|animáció/u', mb_strtolower(implode(' ', $genres))) === 1,
            status: 'scheduled',
          );
        }
      }
    }
    return $records;
  }

  /**
   * Collects text while dropping expand-control labels and ellipses.
   */
  private function textWithoutUi(DOMNode $node): string {
    if ($node instanceof DOMElement) {
      $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
      if (array_intersect($classes, ['readmore', 'readmore-dots']) !== []) {
        return '';
      }
    }
    if ($node->nodeType === XML_TEXT_NODE) {
      return $node->nodeValue ?? '';
    }
    $text = '';
    foreach ($node->childNodes as $child) {
      $text .= ' ' . $this->textWithoutUi($child);
    }
    return $this->text($text);
  }

  /**
   * Normalizes extracted visible text.
   */
  private function text(string $value): string {
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Converts local links to explicit HTTP(S) URLs.
   */
  private function absoluteUrl(string $url): string {
    if (str_starts_with($url, '/')) {
      $url = rtrim(self::SOURCE_URL, '/') . $url;
    }
    elseif (!preg_match('#^https?://#i', $url)) {
      $url = self::SOURCE_URL . ltrim($url, '/');
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
  }

}
