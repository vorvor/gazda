<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;

/**
 * Fails the integration test when a condition is not met.
 */
function shop_google_reviews_product_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$alias = '/termek/gazdabolt/jegkrem-keszito';
$internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
shop_google_reviews_product_assert(
  preg_match('#^/node/(\d+)$#', $internal_path, $matches) === 1,
  'The representative product alias did not resolve to a node.',
);
$product = \Drupal::entityTypeManager()->getStorage('node')->load((int) $matches[1]);
shop_google_reviews_product_assert(
  $product instanceof NodeInterface && $product->bundle() === 'product',
  'The representative entity is not a product node.',
);
$shop = $product->get('field_shop')->entity;
shop_google_reviews_product_assert(
  $shop instanceof NodeInterface && $shop->bundle() === 'shop',
  'The representative product has no referenced shop.',
);
$rating_client = \Drupal::service('shop_google_reviews.rating_client');
$rating = $rating_client->get((int) $shop->id());
shop_google_reviews_product_assert(is_array($rating), 'The referenced shop has no cached rating fixture.');

$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($product, 'full');
$html = (string) \Drupal::service('renderer')->renderRoot($build);
$cache_tags = CacheableMetadata::createFromRenderArray($build)->getCacheTags();
shop_google_reviews_product_assert(
  in_array($rating_client->cacheTag((int) $shop->id()), $cache_tags, TRUE),
  'The product render is missing the referenced shop rating cache tag.',
);
$document = new DOMDocument();
$previous_errors = libxml_use_internal_errors(TRUE);
$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous_errors);
$xpath = new DOMXPath($document);
$contact_panels = $xpath->query(
  '//div[contains(concat(" ", normalize-space(@class), " "), " shop-details ")]'
  . '[contains(concat(" ", normalize-space(@class), " "), " shop-detail-card--contact ")]',
);
shop_google_reviews_product_assert(
  $contact_panels !== FALSE && $contact_panels->length === 1,
  'The product shop-details section must use the shop contact-card styling.',
);
$phone_links = $xpath->query(
  '//div[contains(concat(" ", normalize-space(@class), " "), " shop-details ")]'
  . '//div[contains(concat(" ", normalize-space(@class), " "), " shop-phone ")]/a[starts-with(@href, "tel:")]',
);
shop_google_reviews_product_assert(
  $phone_links !== FALSE && $phone_links->length === count($shop->get('field_phone')),
  'The product shop panel must render every referenced shop phone as a styled call action.',
);
$badges = $xpath->query(
  '//div[contains(concat(" ", normalize-space(@class), " "), " shop-details ")]'
  . '//a[contains(concat(" ", normalize-space(@class), " "), " shop-google-rating ")]',
);
shop_google_reviews_product_assert(
  $badges !== FALSE && $badges->length === 1,
  'The product shop-details section does not contain exactly one Google rating badge.',
);
$badge = $badges->item(0);
shop_google_reviews_product_assert(
  $badge instanceof DOMElement && $badge->getAttribute('href') === $rating['google_maps_uri'],
  'The product rating badge does not link to the referenced shop on Google Maps.',
);
shop_google_reviews_product_assert(
  str_contains($badge->textContent, number_format((float) $rating['rating'], 1, ','))
    && str_contains($badge->textContent, (string) $rating['review_count']),
  'The product rating badge does not show the referenced shop score and review count.',
);

print "PASS: product shop-details renders its referenced shop Google rating.\n";
