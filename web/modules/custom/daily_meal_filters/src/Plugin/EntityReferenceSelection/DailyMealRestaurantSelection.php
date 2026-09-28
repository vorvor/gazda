<?php

namespace Drupal\daily_meal_filters\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Selects restaurants referenced by published, accessible daily meals.
 */
#[EntityReferenceSelection(
  id: 'daily_meal_restaurants',
  label: new TranslatableMarkup('Restaurants with daily meals'),
  entity_types: ['node'],
  group: 'daily_meal_restaurants',
  weight: 0,
)]
class DailyMealRestaurantSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $references = $this->entityTypeManager->getStorage('node')
      ->getAggregateQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'daily_meal')
      ->condition('status', 1)
      ->exists('field_restaurant')
      ->groupBy('field_restaurant.target_id')
      ->aggregate('nid', 'COUNT')
      ->execute();
    $ids = array_column($references, 'field_restaurant_target_id');
    $query->condition('type', 'restaurant');
    $query->condition('status', 1);
    $query->condition('nid', $ids ?: [0], 'IN');
    return $query;
  }

}
