<?php

declare(strict_types=1);

use Drupal\content_transfer\Form\ExportForm;
use Drupal\content_transfer\Form\ImportForm;

function form_serialization_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$container = \Drupal::getContainer();
$cases = [
  [ImportForm::create($container), ['importer', 'entityTypeManager', 'fileSystem']],
  [ExportForm::create($container), ['exporter', 'fileSystem', 'entityTypeManager']],
];

foreach ($cases as [$form, $properties]) {
  $restored = unserialize(serialize($form));
  $reflection = new ReflectionObject($restored);
  foreach ($properties as $propertyName) {
    $property = $reflection->getProperty($propertyName);
    form_serialization_assert(
      $property->isInitialized($restored),
      sprintf('%s::$%s must be restored after form-cache serialization.', $restored::class, $propertyName),
    );
  }
}

print "PASS: form dependencies survive Drupal form-cache serialization\n";
