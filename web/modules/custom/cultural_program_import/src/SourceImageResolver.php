<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RequestOptions;

/**
 * Resolves a usable event image from an original program source page.
 */
final class SourceImageResolver {

  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Fetches only the source page and returns its first original-site image URL.
   *
   * The image itself is deliberately not requested or downloaded.
   */
  public function resolveUrl(string $sourceUrl): ?string {
    $response = $this->httpClient->request('GET', $sourceUrl, $this->requestOptions(30));
    $html = (string) $response->getBody();
    if ($html === '') {
      return NULL;
    }

    foreach ($this->extractCandidates($html, $sourceUrl) as $imageUrl) {
      if ($this->hasAllowedHost($sourceUrl, $imageUrl)) {
        return $imageUrl;
      }
    }

    return NULL;
  }

  /**
   * Extracts ordered image candidates without downloading them.
   *
   * @return string[]
   *   Absolute candidate URLs, strongest metadata first.
   */
  public function extractCandidates(string $html, string $sourceUrl): array {
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      return [];
    }

    $xpath = new \DOMXPath($document);
    $raw = [];
    foreach ([
      '//meta[@property="og:image"]/@content',
      '//meta[@property="og:image:url"]/@content',
      '//meta[@name="twitter:image"]/@content',
      '//meta[@name="twitter:image:src"]/@content',
      '//link[@rel="image_src"]/@href',
    ] as $query) {
      foreach ($xpath->query($query) ?: [] as $attribute) {
        $raw[] = trim($attribute->nodeValue);
      }
    }

    foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
      $decoded = json_decode(trim($script->textContent), TRUE);
      if (is_array($decoded)) {
        $this->collectStructuredImages($decoded, $raw);
      }
    }

    foreach ($xpath->query('//main//img | //article//img | //img') ?: [] as $image) {
      if (!$image instanceof \DOMElement) {
        continue;
      }
      foreach (['src', 'data-src', 'data-lazy-src'] as $attribute) {
        if ($image->hasAttribute($attribute)) {
          $raw[] = trim($image->getAttribute($attribute));
        }
      }
      foreach (['srcset', 'data-srcset'] as $attribute) {
        if (!$image->hasAttribute($attribute)) {
          continue;
        }
        $parts = array_filter(array_map('trim', explode(',', $image->getAttribute($attribute))));
        if ($parts !== []) {
          $last = end($parts);
          $raw[] = preg_split('/\s+/', (string) $last)[0] ?? '';
        }
      }
    }

    $candidates = [];
    foreach ($raw as $value) {
      foreach ($this->normalizeCandidate($value, $sourceUrl) as $candidate) {
        if (!$this->isDecorativeAsset($candidate)) {
          $candidates[$candidate] = $candidate;
        }
      }
    }
    return array_values($candidates);
  }

  /**
   * Collects image and thumbnail values from JSON-LD structures.
   */
  private function collectStructuredImages(array $value, array &$images): void {
    foreach ($value as $key => $item) {
      if (in_array((string) $key, ['image', 'thumbnail', 'thumbnailUrl', 'contentUrl'], TRUE)) {
        if (is_string($item)) {
          $images[] = $item;
        }
        elseif (is_array($item)) {
          foreach ($item as $nested) {
            if (is_string($nested)) {
              $images[] = $nested;
            }
            elseif (is_array($nested)) {
              $this->collectStructuredImages($nested, $images);
            }
          }
        }
      }
      elseif (is_array($item)) {
        $this->collectStructuredImages($item, $images);
      }
    }
  }

  /**
   * Normalizes source-specific image URLs and returns preferred variants.
   *
   * @return string[]
   *   One or more absolute URLs in preference order.
   */
  private function normalizeCandidate(string $value, string $sourceUrl): array {
    $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5);
    if ($value === '' || str_starts_with($value, 'data:')) {
      return [];
    }

    try {
      $absolute = (string) UriResolver::resolve(new Uri($sourceUrl), new Uri($value));
    }
    catch (\Throwable) {
      return [];
    }

    $query = [];
    parse_str((string) parse_url($absolute, PHP_URL_QUERY), $query);
    if (isset($query['url']) && is_string($query['url']) && filter_var($query['url'], FILTER_VALIDATE_URL)) {
      $absolute = $query['url'];
    }

    $preferred = [];
    $original = preg_replace(
      '~(/sites/default/files)/styles/[^/]+/public/~',
      '$1/',
      $absolute,
    );
    if (is_string($original) && $original !== $absolute) {
      $original = preg_replace('/\?.*$/', '', $original) ?: $original;
      $preferred[] = $original;
    }

    $wordpressOriginal = preg_replace('/-\d+x\d+(\.(?:jpe?g|png|webp))(?=\?|$)/i', '$1', $absolute);
    if (is_string($wordpressOriginal) && $wordpressOriginal !== $absolute) {
      $preferred[] = $wordpressOriginal;
    }

    $preferred[] = $absolute;
    return array_values(array_unique($preferred));
  }

  /**
   * Rejects known loading, tracking, navigation, and accessibility assets.
   */
  private function isDecorativeAsset(string $url): bool {
    if (!preg_match('~^https?://~i', $url)) {
      return TRUE;
    }
    return (bool) preg_match(
      '~(?:tribe-loading|varoskep|favicon|userway|spinner|loader|tracking|pixel|avatar|site-logo|/icons?/|\.svg(?:\?|$))~i',
      $url,
    );
  }

  /**
   * Allows images only from the source site's registrable-domain approximation.
   */
  private function hasAllowedHost(string $sourceUrl, string $imageUrl): bool {
    $sourceHost = mb_strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
    $imageHost = mb_strtolower((string) parse_url($imageUrl, PHP_URL_HOST));
    if ($sourceHost === '' || $imageHost === '') {
      return FALSE;
    }
    $parts = explode('.', $sourceHost);
    $root = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $sourceHost;
    return $imageHost === $root || str_ends_with($imageHost, '.' . $root);
  }

  /**
   * Returns conservative HTTP options for source-page requests.
   */
  private function requestOptions(int $timeout): array {
    return [
      RequestOptions::CONNECT_TIMEOUT => 10,
      RequestOptions::TIMEOUT => $timeout,
      RequestOptions::HEADERS => [
        'User-Agent' => 'SetaljBe cultural program image importer/1.0',
        'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8',
      ],
      RequestOptions::ALLOW_REDIRECTS => ['max' => 5],
      RequestOptions::HTTP_ERRORS => TRUE,
    ];
  }

}
