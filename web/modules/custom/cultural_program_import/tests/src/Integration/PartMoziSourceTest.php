<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/PartMoziSource.php';

use Drupal\cultural_program_import\PartMoziSource;

function partmozi_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$html = file_get_contents(__DIR__ . '/../../../tests/fixtures/partmozi.html');
partmozi_assert(is_string($html), 'The P’Art Mozi fixture must be readable.');
$source = new PartMoziSource(\Drupal::httpClient());
$records = $source->extract($html);

partmozi_assert(count($records) === 1, 'Only the future, ticketed fixture screening must be imported.');
$record = $records[0];
partmozi_assert($record->externalId === '903:2026-09-01:16:30', 'The movie, date, and time must form a stable screening ID.');
partmozi_assert($record->title === 'Teszt családi film', 'The film title must be retained.');
partmozi_assert($record->description === 'Első rész és a folytatás.', 'Collapsed description text must be joined without UI labels: ' . $record->description);
partmozi_assert($record->start->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d H:i') === '2026-09-01 16:30', 'The local screening time must be parsed.');
partmozi_assert($record->ticketUrl === 'https://jegy.example.com/903-1630', 'The screening ticket URL must be retained.');
partmozi_assert($record->sourceUrl === 'https://partmozi.hu/film/teszt-csaladi-film-903', 'The relative film URL must become absolute.');
partmozi_assert($record->categories === ['Mozi', 'Animáció', 'Családi'], 'Cinema and genre categories must be retained.');
partmozi_assert($record->family, 'A family genre must set the family flag.');
partmozi_assert($record->placeName === 'P’Art Mozi', 'Every screening must reference the cinema place.');

print "PASS: P’Art Mozi screening extraction\n";
