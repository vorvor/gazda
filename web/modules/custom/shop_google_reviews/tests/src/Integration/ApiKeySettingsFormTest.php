<?php

declare(strict_types=1);

use Drupal\Core\Form\FormState;
use Drupal\shop_google_reviews\Form\ApiKeySettingsForm;
use Drupal\shop_google_reviews\GooglePlacesRatingClient;

/**
 * Fails the integration test when a condition is not met.
 */
function shop_google_reviews_settings_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$route = \Drupal::service('router.route_provider')
  ->getRouteByName('shop_google_reviews.settings');
shop_google_reviews_settings_assert(
  $route->getPath() === '/admin/config/services/shop-google-reviews',
  'The settings route path is incorrect.',
);
shop_google_reviews_settings_assert(
  $route->getRequirement('_permission') === 'administer site configuration',
  'The settings route permission is incorrect.',
);
$menu_links = \Drupal::service('plugin.manager.menu.link')->getDefinitions();
shop_google_reviews_settings_assert(
  isset($menu_links['shop_google_reviews.settings']),
  'The settings page is missing from the administration menu.',
);

$state = \Drupal::state();
$state_key = GooglePlacesRatingClient::API_KEY_STATE_KEY;
$previous_key = $state->get($state_key);

try {
  $state->delete($state_key);
  $form_object = ApiKeySettingsForm::create(\Drupal::getContainer());
  $form = $form_object->buildForm([], new FormState());
  shop_google_reviews_settings_assert(
    ($form['api_key']['#type'] ?? '') === 'password',
    'The API key input must be a password field.',
  );
  shop_google_reviews_settings_assert(
    ($form['api_key']['#default_value'] ?? NULL) === '',
    'The API key must never be exposed as a form default value.',
  );

  $save_state = (new FormState())
    ->setValue('api_key', 'admin-test-key')
    ->setValue('clear_api_key', FALSE);
  $form_object->submitForm($form, $save_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === 'admin-test-key',
    'The submitted API key was not stored.',
  );

  $blank_state = (new FormState())
    ->setValue('api_key', '')
    ->setValue('clear_api_key', FALSE);
  $form_object->submitForm($form, $blank_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === 'admin-test-key',
    'A blank submission must retain the stored API key.',
  );

  $clear_state = (new FormState())
    ->setValue('api_key', '')
    ->setValue('clear_api_key', TRUE);
  $form_object->submitForm($form, $clear_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === NULL,
    'The clear option did not remove the stored API key.',
  );
}
finally {
  if ($previous_key === NULL) {
    $state->delete($state_key);
  }
  else {
    $state->set($state_key, $previous_key);
  }
}

print "PASS: admin API key settings route, form, save, retention, and clear behavior work.\n";
