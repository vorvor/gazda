<?php

declare(strict_types=1);

use Drupal\node\NodeInterface;

/**
 * Fails the integration test when a condition is not met.
 */
function gazda_seo_test_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Loads one published node of the requested bundle.
 */
function gazda_seo_test_node(string $bundle): NodeInterface {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->condition('status', NodeInterface::PUBLISHED)
    ->range(0, 1)
    ->execute();

  gazda_seo_test_assert($ids !== [], "No published {$bundle} node found.");
  $node = $storage->load(reset($ids));
  gazda_seo_test_assert($node instanceof NodeInterface, "Could not load {$bundle} node.");

  return $node;
}

$builder = \Drupal::service('gazda_seo.structured_data_builder');

$shop = gazda_seo_test_node('shop');
$shop_element = $builder->build($shop);
$shop_data = json_decode($shop_element['#value'], TRUE, 512, JSON_THROW_ON_ERROR);
gazda_seo_test_assert(
  $shop_data['@context'] === 'https://schema.org',
  'Shop schema context is missing.',
);
gazda_seo_test_assert($shop_data['@type'] === 'LocalBusiness', 'Shop is not a LocalBusiness.');
gazda_seo_test_assert(
  $shop_data['name'] === $shop->label(),
  'Shop name does not match the entity.',
);
gazda_seo_test_assert(
  str_ends_with($shop_data['@id'], '#localbusiness'),
  'Shop schema has no stable identifier.',
);
gazda_seo_test_assert(isset($shop_element['#cache']['tags']), 'Shop schema has no cache tags.');

$product = gazda_seo_test_node('product');
$product_element = $builder->build($product);
$product_data = json_decode($product_element['#value'], TRUE, 512, JSON_THROW_ON_ERROR);
gazda_seo_test_assert(
  $product_data['@context'] === 'https://schema.org',
  'Product schema context is missing.',
);
gazda_seo_test_assert(isset($product_data['@graph']), 'Product schema does not use a graph.');
$product_types = array_column($product_data['@graph'], '@type');
gazda_seo_test_assert(
  in_array('Product', $product_types, TRUE),
  'Product graph has no Product entity.',
);
gazda_seo_test_assert(
  in_array('Offer', $product_types, TRUE),
  'Product graph has no Offer relationship.',
);
gazda_seo_test_assert(
  in_array('LocalBusiness', $product_types, TRUE),
  'Product graph has no related LocalBusiness.',
);
gazda_seo_test_assert(
  !str_contains($product_element['#value'], 'price'),
  'Product schema fabricates a price.',
);
gazda_seo_test_assert(
  !str_contains($product_element['#value'], 'availability'),
  'Product schema fabricates availability.',
);

print "PASS: LocalBusiness and Product structured data are entity-driven and related.\n";
