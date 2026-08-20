<?php

declare(strict_types=1);

namespace Drupal\gazda_seo;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Builds entity-driven Schema.org data for shops and products.
 */
final class StructuredDataBuilder {

  /**
   * Schema.org weekday names keyed by Office Hours day number.
   */
  private const WEEKDAYS = [
    0 => 'https://schema.org/Sunday',
    1 => 'https://schema.org/Monday',
    2 => 'https://schema.org/Tuesday',
    3 => 'https://schema.org/Wednesday',
    4 => 'https://schema.org/Thursday',
    5 => 'https://schema.org/Friday',
    6 => 'https://schema.org/Saturday',
  ];

  /**
   * Constructs a structured data builder.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Builds an HTML head script render element for a supported node.
   *
   * @return array<string, mixed>|null
   *   A render element, or NULL when the node bundle is unsupported.
   */
  public function build(NodeInterface $node): ?array {
    $cache_tags = $node->getCacheTags();

    $data = match ($node->bundle()) {
      'shop' => $this->buildLocalBusiness($node, $cache_tags, TRUE),
      'product' => $this->buildProduct($node, $cache_tags),
      default => NULL,
    };

    if ($data === NULL) {
      return NULL;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
      ),
      '#attributes' => ['type' => 'application/ld+json'],
      '#cache' => [
        'contexts' => [
          'languages:language_content',
          'url.site',
        ],
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Builds a Product graph, including its seller relationship when available.
   */
  private function buildProduct(NodeInterface $product, array &$cache_tags): array {
    $url = $product->toUrl('canonical', ['absolute' => TRUE])->toString();
    $product_id = $url . '#product';
    $images = $this->imageUrls($product, 'field_images', $cache_tags);

    $product_data = [
      '@type' => 'Product',
      '@id' => $product_id,
      'url' => $url,
      'name' => $product->label(),
      'mainEntityOfPage' => $url,
    ];
    if ($description = $this->fieldText($product, 'field_description')) {
      $product_data['description'] = $description;
    }
    if ($images !== []) {
      $product_data['image'] = $images;
    }

    $graph = [$product_data];
    $shop = $product->hasField('field_shop')
      ? $product->get('field_shop')->entity
      : NULL;

    if ($shop instanceof NodeInterface && $shop->bundle() === 'shop') {
      $shop_url = $shop->toUrl('canonical', ['absolute' => TRUE])->toString();
      $offer_id = $url . '#offer';
      $graph[0]['offers'] = ['@id' => $offer_id];
      $graph[] = [
        '@type' => 'Offer',
        '@id' => $offer_id,
        'url' => $url,
        'itemOffered' => ['@id' => $product_id],
        'seller' => ['@id' => $shop_url . '#localbusiness'],
      ];
      $graph[] = $this->buildLocalBusiness($shop, $cache_tags, FALSE);
    }

    return [
      '@context' => 'https://schema.org',
      '@graph' => $graph,
    ];
  }

  /**
   * Builds LocalBusiness data from a Shop node.
   */
  private function buildLocalBusiness(
    NodeInterface $shop,
    array &$cache_tags,
    bool $with_context,
  ): array {
    $cache_tags = Cache::mergeTags($cache_tags, $shop->getCacheTags());
    $url = $shop->toUrl('canonical', ['absolute' => TRUE])->toString();
    $images = $this->imageUrls($shop, 'field_images', $cache_tags);
    $logos = $this->imageUrls($shop, 'field_logo', $cache_tags);

    $data = [
      '@type' => 'LocalBusiness',
      '@id' => $url . '#localbusiness',
      'url' => $url,
      'name' => $shop->label(),
      'mainEntityOfPage' => $url,
    ];
    if ($with_context) {
      $data = ['@context' => 'https://schema.org'] + $data;
    }
    if ($description = $this->fieldText($shop, 'field_description')) {
      $data['description'] = $description;
    }
    if ($images !== []) {
      $data['image'] = $images;
    }
    if ($logos !== []) {
      $data['logo'] = reset($logos);
    }
    if ($phone = $this->fieldText($shop, 'field_phone')) {
      $data['telephone'] = $phone;
    }
    if ($email = $this->fieldText($shop, 'field_email')) {
      $data['email'] = $email;
    }
    if ($address = $this->address($shop)) {
      $data['address'] = $address;
    }
    if ($geo = $this->geo($shop)) {
      $data['geo'] = $geo;
    }
    if ($hours = $this->openingHours($shop)) {
      $data['openingHoursSpecification'] = $hours;
    }

    return $data;
  }

  /**
   * Returns cleaned plain text from a field's first value.
   */
  private function fieldText(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    $value = (string) $node->get($field_name)->value;
    $value = Html::decodeEntities(strip_tags($value));
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';

    return Unicode::truncate(trim($value), 500, TRUE, TRUE);
  }

  /**
   * Returns absolute image URLs and adds file cache tags.
   */
  private function imageUrls(NodeInterface $node, string $field_name, array &$cache_tags): array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return [];
    }

    $urls = [];
    foreach ($node->get($field_name) as $item) {
      $file = $item->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());
      $urls[] = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    }

    return array_values(array_unique($urls));
  }

  /**
   * Builds a PostalAddress while preserving the source address verbatim.
   */
  private function address(NodeInterface $shop): array {
    $value = $this->fieldText($shop, 'field_address');
    if ($value === '') {
      return [];
    }

    $address = [
      '@type' => 'PostalAddress',
      'streetAddress' => $value,
      'addressCountry' => 'HU',
    ];
    if (preg_match('/\b(\d{4})\b/u', $value, $matches)) {
      $address['postalCode'] = $matches[1];
    }
    if (preg_match('/\bSzentendre\b/ui', $value)) {
      $address['addressLocality'] = 'Szentendre';
    }

    return $address;
  }

  /**
   * Builds GeoCoordinates from a Geofield point.
   */
  private function geo(NodeInterface $shop): array {
    if (!$shop->hasField('field_location') || $shop->get('field_location')->isEmpty()) {
      return [];
    }

    $point = $shop->get('field_location')->first()?->getValue() ?? [];
    if (!isset($point['lat'], $point['lon'])) {
      return [];
    }

    return [
      '@type' => 'GeoCoordinates',
      'latitude' => (float) $point['lat'],
      'longitude' => (float) $point['lon'],
    ];
  }

  /**
   * Builds Schema.org opening-hours specifications.
   */
  private function openingHours(NodeInterface $shop): array {
    if (!$shop->hasField('field_open_hours') || $shop->get('field_open_hours')->isEmpty()) {
      return [];
    }

    $specifications = [];
    foreach ($shop->get('field_open_hours')->getValue() as $slot) {
      $day = (int) ($slot['day'] ?? -1);
      if (!isset(self::WEEKDAYS[$day])) {
        continue;
      }

      $all_day = (bool) ($slot['all_day'] ?? FALSE);
      $opens = $all_day ? '00:00' : $this->formatTime((int) ($slot['starthours'] ?? 0));
      $closes = $all_day ? '23:59' : $this->formatTime((int) ($slot['endhours'] ?? 0));
      if ($opens === NULL || $closes === NULL || $opens === $closes) {
        continue;
      }

      $specifications[] = [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => self::WEEKDAYS[$day],
        'opens' => $opens,
        'closes' => $closes,
      ];
    }

    return $specifications;
  }

  /**
   * Converts an Office Hours integer into an ISO-compatible time.
   */
  private function formatTime(int $time): ?string {
    if ($time < 0 || $time > 2400 || $time % 100 > 59) {
      return NULL;
    }
    if ($time === 2400) {
      return '23:59';
    }

    return sprintf('%02d:%02d', intdiv($time, 100), $time % 100);
  }

}
