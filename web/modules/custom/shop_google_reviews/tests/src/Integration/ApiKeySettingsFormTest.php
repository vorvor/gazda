<?php

declare(strict_types=1);

use Drupal\Core\Form\FormState;
use Drupal\shop_google_reviews\Form\ApiKeySettingsForm;
use Drupal\shop_google_reviews\GooglePlacesRatingClient;
use Drupal\shop_google_reviews\RatingRefresherInterface;

/**
 * Fails the integration test when a condition is not met.
 */
function shop_google_reviews_settings_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Records settings-form refresh requests without calling Google.
 */
final class ShopGoogleReviewsTestRefresher implements RatingRefresherInterface {

  public int $calls = 0;

  public bool $forced = FALSE;

  public bool $throw = FALSE;

  /**
   * {@inheritdoc}
   */
  public function refreshAll(bool $force = FALSE): array {
    if ($this->throw) {
      throw new RuntimeException('Simulated refresh startup failure.');
    }

    $this->calls++;
    $this->forced = $force;

    return ['updated' => 4, 'skipped' => 0, 'failed' => 0];
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
  $refresher = new ShopGoogleReviewsTestRefresher();
  $form_object = new ApiKeySettingsForm($state, $refresher);
  $form = $form_object->buildForm([], new FormState());
  shop_google_reviews_settings_assert(
    ($form['api_key']['#type'] ?? '') === 'textfield',
    'The API key input must be a text field.',
  );
  shop_google_reviews_settings_assert(
    ($form['api_key']['#default_value'] ?? NULL) === '',
    'The API key field must be empty when no key is stored.',
  );

  $save_state = (new FormState())
    ->setValue('api_key', 'admin-test-key')
    ->setValue('clear_api_key', FALSE);
  $form_object->submitForm($form, $save_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === 'admin-test-key',
    'The submitted API key was not stored.',
  );
  shop_google_reviews_settings_assert(
    $refresher->calls === 1 && $refresher->forced,
    'Saving the API key did not force an immediate rating refresh.',
  );
  $saved_form = $form_object->buildForm([], new FormState());
  shop_google_reviews_settings_assert(
    ($saved_form['api_key']['#default_value'] ?? NULL) === 'admin-test-key',
    'The saved API key is not shown in the text field.',
  );

  $unchanged_state = (new FormState())
    ->setValue('api_key', 'admin-test-key')
    ->setValue('clear_api_key', FALSE);
  $form_object->submitForm($saved_form, $unchanged_state);
  shop_google_reviews_settings_assert(
    $refresher->calls === 1,
    'Resaving an unchanged API key must not trigger a billed refresh.',
  );

  $blank_state = (new FormState())
    ->setValue('api_key', '')
    ->setValue('clear_api_key', FALSE);
  $form_object->submitForm($form, $blank_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === 'admin-test-key',
    'A blank submission must retain the stored API key.',
  );
  shop_google_reviews_settings_assert(
    $refresher->calls === 1,
    'A blank submission must not trigger another rating refresh.',
  );

  $clear_state = (new FormState())
    ->setValue('api_key', '')
    ->setValue('clear_api_key', TRUE);
  $form_object->submitForm($form, $clear_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === NULL,
    'The clear option did not remove the stored API key.',
  );
  shop_google_reviews_settings_assert(
    $refresher->calls === 1,
    'Removing the API key must not trigger another rating refresh.',
  );

  $failing_refresher = new ShopGoogleReviewsTestRefresher();
  $failing_refresher->throw = TRUE;
  $failing_form = new ApiKeySettingsForm($state, $failing_refresher);
  $failure_state = (new FormState())
    ->setValue('api_key', 'saved-despite-refresh-failure')
    ->setValue('clear_api_key', FALSE);
  $failing_form->submitForm($form, $failure_state);
  shop_google_reviews_settings_assert(
    $state->get($state_key) === 'saved-despite-refresh-failure',
    'A refresh startup failure prevented the API key from being saved.',
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
