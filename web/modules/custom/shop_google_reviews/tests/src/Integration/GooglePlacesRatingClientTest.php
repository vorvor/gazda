<?php

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\shop_google_reviews\GooglePlacesRatingClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Fails the integration test when a condition is not met.
 */
function shop_google_reviews_test_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'shop');
shop_google_reviews_test_assert(
  isset($field_definitions['field_google_place_id']),
  'The shop Google Place ID field is missing.',
);
shop_google_reviews_test_assert(
  $field_definitions['field_google_place_id']->getType() === 'string',
  'The shop Google Place ID field must be a string.',
);
$form_display = EntityFormDisplay::load('node.shop.default');
shop_google_reviews_test_assert(
  $form_display !== NULL && $form_display->getComponent('field_google_place_id') !== NULL,
  'The Google Place ID field is missing from the shop edit form.',
);
$view_display = EntityViewDisplay::load('node.shop.default');
shop_google_reviews_test_assert(
  $view_display !== NULL && $view_display->getComponent('field_google_place_id') === NULL,
  'The raw Google Place ID must be hidden on shop pages.',
);

$node_id = 987654;
$place_id = 'ChIJ4wxj49XVQUcRN_NrxWY78wg';
$state = \Drupal::state();
$history = [];
$mock = new MockHandler([
  new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'displayName' => ['text' => 'Gazdabolt', 'languageCode' => 'hu'],
    'rating' => 4.8,
    'userRatingCount' => 123,
    'googleMapsUri' => 'https://maps.google.com/?cid=123',
  ], JSON_THROW_ON_ERROR)),
]);
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$http_client = new Client(['handler' => $stack]);
$previous_key = getenv('GOOGLE_PLACES_API_KEY');
putenv('GOOGLE_PLACES_API_KEY=test-api-key');

try {
  $client = new GooglePlacesRatingClient(
    $http_client,
    $state,
    \Drupal::time(),
    \Drupal::service('cache_tags.invalidator'),
    \Drupal::entityTypeManager(),
    \Drupal::logger('shop_google_reviews_test'),
  );

  $result = $client->refresh($node_id, $place_id);
  shop_google_reviews_test_assert(count($history) === 1, 'Exactly one Places request was expected.');
  $request = $history[0]['request'];
  shop_google_reviews_test_assert($request->getMethod() === 'GET', 'Place details must use GET.');
  shop_google_reviews_test_assert(
    $request->getUri()->getScheme() === 'https'
      && $request->getUri()->getHost() === 'places.googleapis.com'
      && $request->getUri()->getPath() === '/v1/places/' . $place_id,
    'The request did not use the editor-provided Place ID.',
  );
  shop_google_reviews_test_assert(
    $request->getUri()->getQuery() === 'languageCode=hu',
    'The request did not select Hungarian provider text.',
  );
  shop_google_reviews_test_assert(
    $request->getHeaderLine('X-Goog-FieldMask') === 'displayName,rating,userRatingCount,googleMapsUri',
    'The request field mask is incorrect.',
  );
  shop_google_reviews_test_assert(
    $request->getHeaderLine('X-Goog-Api-Key') === 'test-api-key',
    'The server-side API key was not used.',
  );
  shop_google_reviews_test_assert($result['place_id'] === $place_id, 'The Place ID was not stored.');
  shop_google_reviews_test_assert($result['display_name'] === 'Gazdabolt', 'The display name was not normalized.');
  shop_google_reviews_test_assert($result['rating'] === 4.8, 'The rating was not normalized.');
  shop_google_reviews_test_assert($result['review_count'] === 123, 'The review count was not normalized.');
  shop_google_reviews_test_assert($client->get($node_id) === $result, 'The normalized payload was not cached.');
}
finally {
  $state->delete('shop_google_reviews.rating.' . $node_id);
  $state->delete('shop_google_reviews.last_attempt.' . $node_id);
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['shop_google_reviews:' . $node_id]);
  if ($previous_key === FALSE) {
    putenv('GOOGLE_PLACES_API_KEY');
  }
  else {
    putenv('GOOGLE_PLACES_API_KEY=' . $previous_key);
  }
}

print "PASS: Google Place ID details are fetched and cached.\n";
