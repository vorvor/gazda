<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;

/**
 * Fails the integration test when a condition is not met.
 */
function shop_google_reviews_service_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$service = \Drupal::entityTypeManager()->getStorage('node')->load(140);
shop_google_reviews_service_assert(
  $service instanceof NodeInterface && $service->bundle() === 'service',
  'The representative service node is unavailable.',
);
$shop = $service->get('field_shop')->entity;
shop_google_reviews_service_assert(
  $shop instanceof NodeInterface && $shop->bundle() === 'shop',
  'The representative service has no referenced shop.',
);
$rating_client = \Drupal::service('shop_google_reviews.rating_client');
$rating = $rating_client->get((int) $shop->id());
shop_google_reviews_service_assert(is_array($rating), 'The referenced shop has no cached rating fixture.');

$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($service, 'full');
$html = (string) \Drupal::service('renderer')->renderRoot($build);
$cache_tags = CacheableMetadata::createFromRenderArray($build)->getCacheTags();
shop_google_reviews_service_assert(
  in_array($rating_client->cacheTag((int) $shop->id()), $cache_tags, TRUE),
  'The service render is missing the referenced shop rating cache tag.',
);

$document = new DOMDocument();
$previous_errors = libxml_use_internal_errors(TRUE);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous_errors);
$xpath = new DOMXPath($document);
shop_google_reviews_service_assert(
  $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " product-full ")]')->length === 1,
  'The service does not use the product detail layout.',
);
shop_google_reviews_service_assert(
  $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " product-details ")]')->length === 1,
  'The service is missing the product details panel.',
);
shop_google_reviews_service_assert(
  $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " shop-details ")]')->length === 1,
  'The service is missing the shop details panel.',
);
shop_google_reviews_service_assert(
  $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " shop-details ")]//a[contains(concat(" ", normalize-space(@class), " "), " shop-google-rating ")]')->length === 1,
  'The service shop panel does not contain its Google rating.',
);
shop_google_reviews_service_assert(
  $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " field--name-field-tags ")]/*[contains(concat(" ", normalize-space(@class), " "), " field__label ")]')->length === 0,
  'Service tags must use the same label-hidden presentation as products.',
);
shop_google_reviews_service_assert(
  $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " field--name-field-service-description ")]/*[contains(concat(" ", normalize-space(@class), " "), " field__label ")]')->length === 0,
  'The service description must use the same label-hidden presentation as product descriptions.',
);

print "PASS: service nodes use the product detail layout and shop rating.\n";
