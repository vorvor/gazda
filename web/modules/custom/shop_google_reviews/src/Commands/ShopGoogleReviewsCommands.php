<?php

namespace Drupal\shop_google_reviews\Commands;

use Drupal\shop_google_reviews\GooglePlacesRatingClient;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Google shop ratings.
 */
final class ShopGoogleReviewsCommands extends DrushCommands {

  public function __construct(
    private readonly GooglePlacesRatingClient $ratingClient,
  ) {
    parent::__construct();
  }

  /**
   * Refreshes Google ratings for all published shops.
   */
  #[CLI\Command(name: 'shop-google-reviews:refresh', aliases: ['sgr:refresh'])]
  #[CLI\Option(name: 'force', description: 'Refresh shops even when their rating is still current.')]
  #[CLI\Usage(name: 'drush sgr:refresh --force', description: 'Refresh every published shop rating.')]
  public function refresh(array $options = ['force' => FALSE]): void {
    $counts = $this->ratingClient->refreshAll((bool) $options['force']);
    $this->logger()->notice(
      'Updated: {updated}; skipped: {skipped}; failed: {failed}.',
      $counts,
    );
  }

}
