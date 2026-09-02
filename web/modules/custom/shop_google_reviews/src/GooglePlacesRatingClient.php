<?php

namespace Drupal\shop_google_reviews;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Fetches and stores shop ratings from Google Places.
 */
final class GooglePlacesRatingClient {

  private const REFRESH_INTERVAL = 86400;

  private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns stored Google rating data for a shop.
   */
  public function get(int $node_id): ?array {
    $rating = $this->state->get($this->ratingStateKey($node_id));

    return is_array($rating) && isset($rating['rating'], $rating['review_count'], $rating['google_maps_uri'])
      ? $rating
      : NULL;
  }

  /**
   * Returns whether the shop rating is due for a refresh.
   */
  public function isRefreshDue(int $node_id): bool {
    $last_attempt = (int) $this->state->get($this->attemptStateKey($node_id), 0);

    return $last_attempt + self::REFRESH_INTERVAL <= $this->time->getRequestTime();
  }

  /**
   * Refreshes one shop's rating.
   */
  public function refresh(int $node_id, string $place_id): array {
    $api_key = $this->getApiKey();
    if ($api_key === '') {
      throw new RuntimeException('A Google Places API key has not been configured.');
    }

    $place_id = trim($place_id);
    if ($place_id === '') {
      throw new RuntimeException('A Google Place ID has not been configured.');
    }

    $this->state->set($this->attemptStateKey($node_id), $this->time->getRequestTime());

    $response = $this->httpClient->request('GET', self::ENDPOINT . rawurlencode($place_id), [
      'connect_timeout' => 5,
      'timeout' => 10,
      'headers' => [
        'X-Goog-Api-Key' => $api_key,
        'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri',
      ],
      'query' => ['languageCode' => 'hu'],
    ]);

    try {
      $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (JsonException $exception) {
      throw new RuntimeException('Google Places returned invalid JSON.', 0, $exception);
    }

    if (!is_array($payload) || !isset($payload['rating'], $payload['userRatingCount'], $payload['googleMapsUri'])) {
      throw new RuntimeException('No Google rating was found for this shop.');
    }

    $rating = (float) $payload['rating'];
    $review_count = (int) $payload['userRatingCount'];
    $google_maps_uri = (string) $payload['googleMapsUri'];
    if ($rating < 1 || $rating > 5 || $review_count < 0 || !$this->isGoogleMapsUri($google_maps_uri)) {
      throw new RuntimeException('Google Places returned invalid rating data.');
    }

    $data = [
      'place_id' => $place_id,
      'display_name' => (string) ($payload['displayName']['text'] ?? ''),
      'rating' => $rating,
      'review_count' => $review_count,
      'google_maps_uri' => $google_maps_uri,
      'updated' => $this->time->getRequestTime(),
    ];

    $this->state->set($this->ratingStateKey($node_id), $data);
    $this->cacheTagsInvalidator->invalidateTags([$this->cacheTag($node_id)]);

    return $data;
  }

  /**
   * Refreshes all published shops and returns operation counts.
   */
  public function refreshAll(bool $force = FALSE): array {
    $counts = ['updated' => 0, 'skipped' => 0, 'failed' => 0];
    $node_ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'shop')
      ->condition('status', NodeInterface::PUBLISHED)
      ->execute();

    /** @var \Drupal\node\NodeInterface $shop */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($node_ids) as $shop) {
      $place_id = $shop->hasField('field_google_place_id')
        ? trim((string) $shop->get('field_google_place_id')->value)
        : '';
      if ($place_id === '') {
        $counts['skipped']++;
        continue;
      }

      if (!$force && !$this->isRefreshDue((int) $shop->id())) {
        $counts['skipped']++;
        continue;
      }

      try {
        $this->refresh(
          (int) $shop->id(),
          $place_id,
        );
        $counts['updated']++;
      }
      catch (\Throwable) {
        $counts['failed']++;
        $this->logger->warning('Google rating refresh failed for shop @id.', [
          '@id' => $shop->id(),
        ]);
      }
    }

    return $counts;
  }

  /**
   * Returns the cache tag used by a shop rating render.
   */
  public function cacheTag(int $node_id): string {
    return 'shop_google_reviews:' . $node_id;
  }

  private function getApiKey(): string {
    $environment_key = getenv('GOOGLE_PLACES_API_KEY');

    return trim((string) Settings::get(
      'shop_google_reviews_api_key',
      $environment_key === FALSE ? '' : $environment_key,
    ));
  }

  private function isGoogleMapsUri(string $uri): bool {
    $parts = parse_url($uri);
    if (($parts['scheme'] ?? '') !== 'https') {
      return FALSE;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));

    return $host === 'maps.google.com' || $host === 'www.google.com';
  }

  private function ratingStateKey(int $node_id): string {
    return 'shop_google_reviews.rating.' . $node_id;
  }

  private function attemptStateKey(int $node_id): string {
    return 'shop_google_reviews.last_attempt.' . $node_id;
  }

}
