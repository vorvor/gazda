<?php

namespace Drupal\shop_google_reviews\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\shop_google_reviews\GooglePlacesRatingClient;
use Drupal\shop_google_reviews\RatingRefresherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Google Places API key settings form.
 */
final class ApiKeySettingsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Constructs the settings form.
   */
  public function __construct(
    protected StateInterface $state,
    protected RatingRefresherInterface $ratingRefresher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
      $container->get('shop_google_reviews.rating_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'shop_google_reviews_api_key_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $stored_key = trim((string) $this->state->get(GooglePlacesRatingClient::API_KEY_STATE_KEY, ''));
    $has_stored_key = $stored_key !== '';

    if ($has_stored_key) {
      $status = $this->t('A Google Places API-kulcs be van állítva.');
    }
    else {
      $status = $this->t('Nincs beállítva Google Places API-kulcs.');
    }

    $form['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Állapot'),
      '#markup' => $status,
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Places API-kulcs'),
      '#default_value' => $stored_key,
      '#maxlength' => 512,
      '#description' => $this->t('Kiszolgálóra korlátozott, Places API (New) szolgáltatáshoz engedélyezett kulcsot használj.'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];
    if ($has_stored_key) {
      $form['clear_api_key'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('A Drupalban mentett API-kulcs eltávolítása'),
      ];
    }
    else {
      $form['clear_api_key'] = [
        '#type' => 'value',
        '#value' => FALSE,
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Beállítások mentése'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ((bool) $form_state->getValue('clear_api_key')) {
      $this->state->delete(GooglePlacesRatingClient::API_KEY_STATE_KEY);
      $this->messenger()->addStatus($this->t('A Drupalban mentett API-kulcs eltávolítva.'));
      return;
    }

    $api_key = trim((string) $form_state->getValue('api_key'));
    if ($api_key === '') {
      $this->messenger()->addStatus($this->t('A mentett API-kulcs nem változott.'));
      return;
    }
    $stored_key = trim((string) $this->state->get(GooglePlacesRatingClient::API_KEY_STATE_KEY, ''));
    if ($api_key === $stored_key) {
      $this->messenger()->addStatus($this->t('A mentett API-kulcs nem változott.'));
      return;
    }

    $this->state->set(GooglePlacesRatingClient::API_KEY_STATE_KEY, $api_key);
    try {
      $counts = $this->ratingRefresher->refreshAll(TRUE);
    }
    catch (\Throwable) {
      $this->messenger()->addWarning($this->t('Az API-kulcs mentve, de az értékelések frissítése nem indult el. A következő cron futás újra megpróbálja.'));
      return;
    }

    if ($counts['failed'] > 0) {
      $this->messenger()->addWarning($this->t('Az API-kulcs mentve. @updated üzlet értékelése frissült, @failed frissítés sikertelen volt.', [
        '@updated' => $counts['updated'],
        '@failed' => $counts['failed'],
      ]));
      return;
    }

    $this->messenger()->addStatus($this->t('A Google Places API-kulcs mentve, @updated üzlet értékelése frissítve.', [
      '@updated' => $counts['updated'],
    ]));
  }

}
