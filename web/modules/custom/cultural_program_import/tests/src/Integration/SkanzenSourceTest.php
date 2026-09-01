<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/SkanzenSource.php';

use Drupal\cultural_program_import\SkanzenSource;

function skanzen_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$html = file_get_contents(__DIR__ . '/../../../tests/fixtures/skanzen-detail.html');
skanzen_assert(is_string($html), 'The Skanzen fixture must be readable.');
$source = new SkanzenSource(\Drupal::httpClient());
$record = $source->extractDetail($html, 'https://skanzen.hu/hu/programok/rendezvenyek/oszi-program');

skanzen_assert($record !== NULL, 'A dated Skanzen detail page must produce a record.');
skanzen_assert($record->externalId === '1095', 'The Skanzen post ID must remain stable.');
skanzen_assert($record->title === 'Őszi családi hétvége', 'The Skanzen title must be retained: ' . $record->title);
skanzen_assert($record->start->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d H:i') === '2026-10-24 09:00', 'The Hungarian range start and displayed time must be parsed.');
skanzen_assert($record->end?->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d H:i') === '2026-11-01 17:00', 'The Hungarian cross-month range end must be parsed.');
skanzen_assert($record->description === '2026. október 24 – november 1. között családi programokkal várjuk.', 'The lead must become a plain-text description.');
skanzen_assert($record->categories === ['Skanzen', 'Rendezvények'], 'The Skanzen subcategory must be retained.');
skanzen_assert($record->family, 'Family wording must set the family flag.');
skanzen_assert($record->placeName === 'Skanzen', 'The event must reference the Skanzen place.');

print "PASS: Skanzen dated event extraction\n";
