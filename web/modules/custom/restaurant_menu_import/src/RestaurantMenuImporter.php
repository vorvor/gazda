<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Runs a basic restaurant menu import.
 */
final class RestaurantMenuImporter {

  private const MAX_DOWNLOAD_BYTES = 10485760;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
    private readonly SourceResolver $sourceResolver,
    private readonly PdfMealExtractor $pdfExtractor,
    private readonly HtmlMealExtractor $htmlExtractor,
    private readonly MealSynchronizer $mealSynchronizer,
    private readonly Parser $pdfParser,
  ) {
  }

  /**
   * Imports the configured sources of one restaurant node.
   *
   * @return array{sources: int, extracted: int, created: int, updated: int, unchanged: int, errors: int}
   *   Import counts.
   */
  public function import(int $restaurant_id, bool $dry_run = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $restaurant = $storage->load($restaurant_id);
    if (!$restaurant instanceof NodeInterface || $restaurant->bundle() !== 'restaurant') {
      throw new RuntimeException("Restaurant node $restaurant_id was not found.");
    }
    if (!$restaurant->hasField('field_data_source')) {
      throw new RuntimeException('The restaurant has no field_data_source field.');
    }

    $lock_name = 'restaurant_menu_import:' . $restaurant_id;
    if (!$this->lock->acquire($lock_name, 300.0)) {
      throw new RuntimeException('An import is already running for this restaurant.');
    }

    try {
      $result = [
        'sources' => 0,
        'extracted' => 0,
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'errors' => 0,
      ];
      $records = [];
      $sources = $this->sourceResolver->resolve((string) $restaurant->get('field_data_source')->value);
      foreach ($sources as $source) {
        try {
          $source_records = $this->extractSource($source, $result);
          if ($source_records === []) {
            $result['errors']++;
            $this->logger->warning('Restaurant @restaurant menu source contained no confidently extractable meals.', [
              '@restaurant' => $restaurant_id,
            ]);
            continue;
          }
          foreach ($source_records as $record) {
            $records[mb_strtolower($record->name)] = $record;
          }
        }
        catch (\Throwable $exception) {
          $result['errors']++;
          $this->logger->warning('Restaurant @restaurant menu source could not be imported: @message', [
            '@restaurant' => $restaurant_id,
            '@message' => $exception->getMessage(),
          ]);
        }
      }

      $result['extracted'] = count($records);
      $sync = $this->mealSynchronizer->synchronize($restaurant, array_values($records), $dry_run);
      return array_replace($result, $sync);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Extracts records from one resolved source descriptor.
   *
   * @param array{type: string, url?: string, selector?: string} $source
   *   Source descriptor.
   * @param array<string, int> $result
   *   Import counters.
   *
   * @return \Drupal\restaurant_menu_import\MealRecord[]
   *   Extracted records.
   */
  private function extractSource(array $source, array &$result): array {
    $type = $source['type'];
    if ($type === 'image') {
      throw new RuntimeException('Attached-image OCR is not supported by the basic importer.');
    }

    if ($type === 'html_pdf_section') {
      $html = $this->download((string) $source['url']);
      $pdf_urls = $this->findPdfLinks($html, (string) $source['url'], (string) $source['selector']);
      $records = [];
      foreach ($pdf_urls as $pdf_url) {
        $records = array_merge($records, $this->extractSource([
          'type' => 'pdf',
          'url' => $pdf_url,
        ], $result));
      }
      return $records;
    }

    $url = (string) ($source['url'] ?? '');
    if ($url === '') {
      throw new RuntimeException('The source URL is empty.');
    }
    $result['sources']++;
    $contents = $this->download($url);

    return match ($type) {
      'pdf' => $this->pdfExtractor->extractText($this->pdfParser->parseContent($contents)->getText(), $url),
      'html_fragment' => $this->htmlExtractor->extract($contents, $url, (string) $source['selector']),
      'html_section' => $this->htmlExtractor->extract($contents, $url, NULL, (string) $source['selector']),
      'html' => $this->htmlExtractor->extract($contents, $url),
      default => throw new RuntimeException("Unsupported source type: $type"),
    };
  }

  /**
   * Downloads a bounded HTTP(S) resource.
   */
  private function download(string $url): string {
    if (!preg_match('#^https?://#i', $url)) {
      throw new RuntimeException('Only HTTP(S) sources are supported.');
    }

    $response = $this->httpClient->request('GET', $url, [
      'allow_redirects' => ['max' => 5],
      'connect_timeout' => 10,
      'timeout' => 30,
      'headers' => ['User-Agent' => 'Drupal restaurant menu importer'],
    ]);
    $contents = (string) $response->getBody();
    if ($contents === '' || strlen($contents) > self::MAX_DOWNLOAD_BYTES) {
      throw new RuntimeException('The downloaded source is empty or too large.');
    }
    return $contents;
  }

  /**
   * Finds PDF links inside one element with the configured class.
   *
   * @return string[]
   *   Absolute PDF URLs.
   */
  private function findPdfLinks(string $html, string $page_url, string $class): array {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $section = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . str_replace('"', '', $class) . ' ")]')->item(0);
    if (!$section instanceof DOMElement) {
      return [];
    }

    $urls = [];
    foreach ($xpath->query('.//a[@href]', $section) ?: [] as $link) {
      $href = trim($link->getAttribute('href'));
      if (!preg_match('/\.pdf(?:$|\?)/i', $href)) {
        continue;
      }
      if (str_starts_with($href, '/')) {
        $parts = parse_url($page_url);
        $href = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $href;
      }
      elseif (!preg_match('#^https?://#i', $href)) {
        $href = rtrim(dirname($page_url), '/') . '/' . ltrim($href, '/');
      }
      $urls[] = $href;
    }

    return array_values(array_unique($urls));
  }

}
