<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/TribeEventsSource.php';

use Drupal\cultural_program_import\TribeEventsSource;

function tribe_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$json = file_get_contents(__DIR__ . '/../../../tests/fixtures/tribe-events.json');
tribe_assert(is_string($json), 'The Tribe fixture must be readable.');
$source = new TribeEventsSource(\Drupal::httpClient());
$records = $source->extract($json, [
  'name' => 'Szentendrei Programkereső',
  'endpoint' => 'https://programok.szentendre.hu/wp-json/tribe/events/v1/events',
  'priority' => 10,
  'fallback_place' => 'Szentendre',
  'website' => 'https://programok.szentendre.hu/',
]);

tribe_assert(count($records) === 1, 'One fixture event must produce one program record.');
$record = $records[0];
tribe_assert($record->externalId === '18436', 'The event ID must remain stable.');
tribe_assert($record->title === 'Teszt & családi program', 'HTML entities must be decoded in titles.');
tribe_assert($record->description === "Első sor.\nMásodik sor.", 'Event HTML must become readable plain text.');
tribe_assert($record->start->format('Y-m-d H:i:s T') === '2026-09-01 16:00:00 UTC', 'The UTC start date must be parsed exactly.');
tribe_assert($record->end?->format('Y-m-d H:i:s T') === '2026-09-01 17:30:00 UTC', 'The UTC end date must be parsed exactly.');
tribe_assert($record->placeName === 'Dunaparti Művelődési Ház', 'The venue must become the cultural place.');
tribe_assert($record->placeAddress === '2000 Szentendre, Dunakorzó 11/A', 'Venue address components must be normalized.');
tribe_assert($record->ticketUrl === 'https://tickets.example.com/18436', 'The event website must become the ticket URL.');
tribe_assert($record->categories === ['Gyerekprogramok', 'Koncert'], 'Event categories must be retained.');
tribe_assert($record->family, 'Child and family categories must set the family flag.');
tribe_assert($record->price === '2500 Ft', 'The source price must be retained.');

print "PASS: Tribe Events JSON extraction\n";
