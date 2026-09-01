<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/LibrarySource.php';

use Drupal\cultural_program_import\LibrarySource;

function library_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$html = file_get_contents(__DIR__ . '/../../../tests/fixtures/hbpmk.html');
library_assert(is_string($html), 'The library fixture must be readable.');
$source = new LibrarySource(\Drupal::httpClient());
$records = $source->extract($html, new DateTimeImmutable('2026-08-31 00:00:00', new DateTimeZone('UTC')));

library_assert(count($records) === 1, 'Past library cards must not be imported.');
$record = $records[0];
library_assert($record->title === 'Mesedélután', 'The library event title must be retained.');
library_assert($record->description === 'Közös mese és játék.', 'The visible event summary must become plain text.');
library_assert($record->start->format('Y-m-d H:i:s P') === '2026-09-08 16:00:00 +00:00', 'The semantic datetime attribute must be used.');
library_assert($record->sourceUrl === 'https://hbpmk.hu/mesedelutan-2026', 'The canonical event URL must be absolute.');
library_assert($record->externalId === hash('sha256', $record->sourceUrl), 'The canonical URL must produce a stable external ID.');
library_assert($record->categories === ['Könyvtári program', 'Gyermekkönyvtár'], 'The library section must become a category.');
library_assert($record->family, 'Children’s-library events must set the family flag.');
library_assert($record->placeName === 'Hamvas Béla Pest Megyei Könyvtár', 'The event must reference the library place.');

print "PASS: Hamvas Béla library event extraction\n";
