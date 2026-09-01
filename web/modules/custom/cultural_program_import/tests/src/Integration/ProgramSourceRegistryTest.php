<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/ProgramSourceRegistryInterface.php';
require_once __DIR__ . '/../../../src/TribeEventsSource.php';
require_once __DIR__ . '/../../../src/PartMoziSource.php';
require_once __DIR__ . '/../../../src/SkanzenSource.php';
require_once __DIR__ . '/../../../src/LibrarySource.php';
require_once __DIR__ . '/../../../src/ProgramSourceRegistry.php';

use Drupal\cultural_program_import\LibrarySource;
use Drupal\cultural_program_import\PartMoziSource;
use Drupal\cultural_program_import\ProgramSourceRegistry;
use Drupal\cultural_program_import\SkanzenSource;
use Drupal\cultural_program_import\TribeEventsSource;

$client = \Drupal::httpClient();
$registry = new ProgramSourceRegistry(
  new TribeEventsSource($client),
  new PartMoziSource($client),
  new SkanzenSource($client),
  new LibrarySource($client),
);
$expected = ['programkereso', 'cultural_center', 'partmozi', 'femuz', 'skanzen', 'library', 'teatrum'];
if ($registry->keys() !== $expected) {
  throw new RuntimeException('The complete deterministic source registry is required.');
}
try {
  $registry->fetch('not-a-source');
  throw new RuntimeException('An unknown source key must be rejected.');
}
catch (InvalidArgumentException) {
  // Expected.
}

print "PASS: complete cultural program source registry\n";
