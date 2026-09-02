<?php

namespace Drupal\shop_google_reviews;

/**
 * Refreshes cached Google ratings for shop content.
 */
interface RatingRefresherInterface {

  /**
   * Refreshes published shop ratings and returns operation counts.
   */
  public function refreshAll(bool $force = FALSE): array;

}
