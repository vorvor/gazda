<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/ProgramSynchronizer.php';

use Drupal\cultural_program_import\ProgramRecord;
use Drupal\cultural_program_import\ProgramSynchronizer;

function cultural_sync_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
$transaction = $database->startTransaction();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$now = new DateTimeImmutable('2026-08-31 18:00:00', new DateTimeZone('Europe/Budapest'));

try {
  $synchronizer = new ProgramSynchronizer(\Drupal::entityTypeManager(), \Drupal::currentUser());
  $record = new ProgramRecord(
    sourceName: 'Test Kulturális Forrás',
    externalId: 'event-100',
    priority: 50,
    title: 'Teszt kulturális program',
    description: 'Eredeti leírás.',
    start: new DateTimeImmutable('2026-09-05 18:30:00', new DateTimeZone('Europe/Budapest')),
    end: new DateTimeImmutable('2026-09-05 20:00:00', new DateTimeZone('Europe/Budapest')),
    allDay: FALSE,
    placeName: 'Teszt Kulturális Ház',
    placeAddress: '2000 Szentendre, Teszt utca 1.',
    placeWebsite: 'https://example.com/place',
    sourceUrl: 'https://example.com/events/100',
    ticketUrl: 'https://example.com/tickets/100',
    price: '2500 Ft',
    categories: ['Koncert', 'Családi'],
    family: TRUE,
    status: 'scheduled',
    sourceUpdated: new DateTimeImmutable('2026-08-30 12:00:00', new DateTimeZone('Europe/Budapest')),
  );

  $created = $synchronizer->synchronize([$record], FALSE, $now);
  cultural_sync_assert($created === ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'places_created' => 1, 'places_updated' => 0], 'The first synchronization must create one place and one program.');

  $programIds = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'cultural_program')
    ->condition('field_source_name', 'Test Kulturális Forrás')
    ->condition('field_external_id', 'event-100')
    ->execute();
  cultural_sync_assert(count($programIds) === 1, 'The program must be queryable by source and external ID.');
  $program = $storage->load(reset($programIds));
  cultural_sync_assert($program->label() === 'Teszt kulturális program', 'The imported title must be saved.');
  cultural_sync_assert($program->get('field_program_description')->value === 'Eredeti leírás.', 'The imported description must be saved.');
  cultural_sync_assert($program->get('field_program_end')->value === '2026-09-05T18:00:00', 'The end date must be stored in UTC.');
  cultural_sync_assert($program->get('field_program_family')->value === '1', 'The family-program flag must be saved.');
  cultural_sync_assert($program->get('field_program_place')->referencedEntities()[0]->label() === 'Teszt Kulturális Ház', 'The program must reference its cultural place.');
  cultural_sync_assert(count($program->get('field_program_category')->referencedEntities()) === 2, 'Source categories must become referenced taxonomy terms.');
  $originalRevisionId = $program->getRevisionId();

  $emptyUpdate = new ProgramRecord(
    sourceName: $record->sourceName,
    externalId: $record->externalId,
    priority: $record->priority,
    title: $record->title,
    description: '',
    start: $record->start,
    end: NULL,
    allDay: $record->allDay,
    placeName: $record->placeName,
    placeAddress: '',
    placeWebsite: '',
    sourceUrl: $record->sourceUrl,
    ticketUrl: '',
    price: '',
    categories: [],
    family: $record->family,
    status: $record->status,
    sourceUpdated: NULL,
  );
  $unchanged = $synchronizer->synchronize([$emptyUpdate], FALSE, $now);
  cultural_sync_assert($unchanged['created'] === 0 && $unchanged['unchanged'] === 1, 'A repeat import with empty optional source values must be unchanged: ' . json_encode($unchanged));
  $storage->resetCache([$program->id()]);
  $preserved = $storage->load($program->id());
  cultural_sync_assert($preserved->get('field_program_description')->value === 'Eredeti leírás.', 'An empty imported description must not clear existing content.');
  cultural_sync_assert($preserved->get('field_program_end')->value === '2026-09-05T18:00:00', 'An empty imported end date must not clear the existing end date.');
  cultural_sync_assert($preserved->get('field_program_ticket')->uri === 'https://example.com/tickets/100', 'An empty imported ticket URL must not clear the existing ticket URL.');

  $nextDay = $now->modify('+1 day');
  $seenAgain = $synchronizer->synchronize([$emptyUpdate], FALSE, $nextDay);
  cultural_sync_assert($seenAgain['updated'] === 0 && $seenAgain['unchanged'] === 1, 'A last-seen refresh must not be reported as a material program update.');
  $storage->resetCache([$program->id()]);
  $seenProgram = $storage->load($program->id());
  cultural_sync_assert($seenProgram->getRevisionId() === $originalRevisionId, 'A last-seen-only refresh must not create a content revision.');
  cultural_sync_assert($seenProgram->get('field_program_last_seen')->value === '2026-09-01T16:00:00', 'The operational last-seen timestamp must still advance.');

  $placeRecord = static function (string $sourceName, string $externalId, int $priority, string $address): ProgramRecord {
    return new ProgramRecord(
      sourceName: $sourceName,
      externalId: $externalId,
      priority: $priority,
      title: 'Helyszín-prioritás teszt ' . $externalId,
      description: '',
      start: new DateTimeImmutable('2030-06-01 10:00:00', new DateTimeZone('Europe/Budapest')),
      end: NULL,
      allDay: FALSE,
      placeName: 'Stabil Kulturális Helyszín',
      placeAddress: $address,
      placeWebsite: 'https://example.com/' . $externalId,
      sourceUrl: 'https://example.com/events/' . $externalId,
      ticketUrl: '',
      price: '',
      categories: [],
      family: FALSE,
      status: 'scheduled',
      sourceUpdated: NULL,
    );
  };
  $lowPriority = $placeRecord('Szentendrei Programkereső', 'low-priority', 10, '2000 Szentendre, Régi cím 1.');
  $highPriority = $placeRecord('Ferenczy Múzeumi Centrum', 'high-priority', 100, '2000 Szentendre, Hiteles cím 2.');
  $synchronizer->synchronize([$lowPriority, $highPriority], FALSE, $now);
  $stableRerun = $synchronizer->synchronize([$lowPriority, $highPriority], FALSE, $now);
  cultural_sync_assert($stableRerun['places_updated'] === 0, 'Conflicting place metadata must settle on the higher-priority source without oscillating.');
  $placeIds = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'cultural_place')
    ->condition('title', 'Stabil Kulturális Helyszín')
    ->execute();
  $stablePlace = $storage->load(reset($placeIds));
  cultural_sync_assert($stablePlace->get('field_place_address')->value === '2000 Szentendre, Hiteles cím 2.', 'The higher-priority place address must win.');
  cultural_sync_assert($stablePlace->get('field_source_name')->value === 'Ferenczy Múzeumi Centrum', 'The authoritative place source must be retained.');

  print "PASS: cultural places and programs synchronize idempotently\n";
}
finally {
  $transaction->rollBack();
}
