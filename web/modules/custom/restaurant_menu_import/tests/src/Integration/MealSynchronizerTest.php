<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/MealRecord.php';
require_once __DIR__ . '/../../../src/MealSynchronizer.php';

use Drupal\restaurant_menu_import\MealRecord;
use Drupal\restaurant_menu_import\MealSynchronizer;

function synchronizer_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
$transaction = $database->startTransaction();
try {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $restaurant = $storage->load(540);
  synchronizer_assert($restaurant !== NULL, 'The test restaurant must exist.');

  $existing = $storage->create([
    'type' => 'meal',
    'title' => 'Importer integration existing meal',
    'langcode' => 'hu',
    'status' => 1,
    'field_price' => '100.00',
    'field_description' => 'Old description.',
    'field_restaurant' => ['target_id' => 540],
  ]);
  $existing->save();

  $records = [
    new MealRecord('existing', 'IMPORTER INTEGRATION EXISTINGMEAL', '200.00', 'Updated description.', 'https://example.com/menu.pdf'),
    new MealRecord('new', 'Importer integration new meal', '300.00', 'New description.', 'https://example.com/menu.pdf'),
  ];
  $result = (new MealSynchronizer(\Drupal::entityTypeManager()))->synchronize($restaurant, $records);

  synchronizer_assert($result['created'] === 1, 'One missing meal must be created.');
  synchronizer_assert($result['updated'] === 1, 'One existing meal must be updated.');
  synchronizer_assert($result['unchanged'] === 0, 'No test meal should be unchanged.');

  $storage->resetCache([$existing->id()]);
  $updated = $storage->load($existing->id());
  synchronizer_assert($updated->get('field_price')->value === '200.00', 'Existing meal price must be updated.');
  synchronizer_assert($updated->get('field_description')->value === 'Updated description.', 'Existing meal description must be updated.');

  print "PASS: meal synchronization creates and updates content\n";
}
finally {
  $transaction->rollBack();
}
