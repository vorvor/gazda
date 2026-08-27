<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Creates and updates meal nodes for a restaurant.
 */
final class MealSynchronizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Synchronizes extracted meal records.
   *
   * Existing meals are matched by restaurant and exact title. Missing meals
   * are intentionally left unchanged.
   *
   * @param \Drupal\node\NodeInterface $restaurant
   *   The restaurant node.
   * @param \Drupal\restaurant_menu_import\MealRecord[] $records
   *   Extracted meals.
   * @param bool $dry_run
   *   Whether to report changes without saving them.
   *
   * @return array{created: int, updated: int, unchanged: int}
   *   Synchronization counts.
   */
  public function synchronize(NodeInterface $restaurant, array $records, bool $dry_run = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
    $existing_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'meal')
      ->condition('field_restaurant.target_id', $restaurant->id())
      ->sort('nid')
      ->execute();
    $existing_by_title = [];
    foreach ($storage->loadMultiple($existing_ids) as $existing_meal) {
      $existing_by_title[$this->normalizeTitle($existing_meal->label())] ??= $existing_meal;
    }

    foreach ($records as $record) {
      if (!$record instanceof MealRecord) {
        continue;
      }

      $meal = $existing_by_title[$this->normalizeTitle($record->name)] ?? NULL;
      $description = $record->description !== ''
        ? $record->description
        : 'Forrás: ' . $record->sourceUrl;

      if (!$meal instanceof NodeInterface) {
        $result['created']++;
        if (!$dry_run) {
          $meal = $storage->create([
            'type' => 'meal',
            'title' => $record->name,
            'langcode' => $restaurant->language()->getId(),
            'status' => 1,
            'uid' => $restaurant->getOwnerId(),
            'promote' => 0,
            'sticky' => 0,
            'field_price' => $record->price,
            'field_description' => $description,
            'field_restaurant' => ['target_id' => $restaurant->id()],
          ]);
          $meal->save();
          $existing_by_title[$this->normalizeTitle($record->name)] = $meal;
        }
        continue;
      }

      $changed = $meal->get('field_price')->value !== $record->price
        || $meal->get('field_description')->value !== $description
        || !$meal->isPublished();
      if (!$changed) {
        $result['unchanged']++;
        continue;
      }

      $result['updated']++;
      if (!$dry_run) {
        $meal->set('field_price', $record->price);
        $meal->set('field_description', $description);
        $meal->setPublished();
        $meal->setNewRevision(TRUE);
        $meal->setRevisionLogMessage('Updated from the configured restaurant menu source.');
        $meal->save();
      }
    }

    return $result;
  }

  /**
   * Builds a conservative title key insensitive to case and spacing.
   */
  private function normalizeTitle(string $title): string {
    return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($title));
  }

}
