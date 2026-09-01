<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/ProgramRecord.php';
require_once __DIR__ . '/../../../src/ProgramSynchronizer.php';
require_once __DIR__ . '/../../../src/ProgramSourceRegistryInterface.php';
require_once __DIR__ . '/../../../src/CulturalProgramImporter.php';

use Drupal\cultural_program_import\CulturalProgramImporter;
use Drupal\cultural_program_import\ProgramRecord;
use Drupal\cultural_program_import\ProgramSourceRegistryInterface;
use Drupal\cultural_program_import\ProgramSynchronizer;
use Psr\Log\NullLogger;

function cultural_import_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$transaction = \Drupal::database()->startTransaction();
try {
  $registry = new class implements ProgramSourceRegistryInterface {
    public function keys(): array {
      return ['working', 'broken'];
    }

    public function fetch(string $source, ?DateTimeImmutable $from = NULL): array {
      if ($source === 'broken') {
        throw new RuntimeException('Synthetic source failure');
      }
      return [new ProgramRecord(
        sourceName: 'Importer test source',
        externalId: 'importer-test-1',
        priority: 50,
        title: 'Importer continuation test',
        description: 'Imported despite a sibling source failure.',
        start: new DateTimeImmutable('2032-04-05 17:00:00', new DateTimeZone('Europe/Budapest')),
        end: NULL,
        allDay: FALSE,
        placeName: 'Importer test venue',
        placeAddress: 'Szentendre, Teszt utca 2.',
        placeWebsite: '',
        sourceUrl: 'https://example.test/importer-event',
        ticketUrl: '',
        price: '',
        categories: ['Teszt'],
        family: FALSE,
        status: 'scheduled',
        sourceUpdated: NULL,
      )];
    }
  };

  $importer = new CulturalProgramImporter(
    new ProgramSynchronizer(\Drupal::entityTypeManager(), \Drupal::currentUser()),
    $registry,
    \Drupal::lock(),
    new NullLogger(),
  );
  $result = $importer->import('all', FALSE, new DateTimeImmutable('2032-04-01 00:00:00', new DateTimeZone('Europe/Budapest')));

  cultural_import_assert($result['created'] === 1, 'A working source must still be synchronized.');
  cultural_import_assert($result['sources']['working'] === 1, 'The successful source count must be reported.');
  cultural_import_assert(isset($result['errors']['broken']), 'The failed source must be reported without aborting sibling sources.');
  cultural_import_assert(count($result['errors']) === 1, 'Only the failed source may be reported as an error.');
  print "PASS: source failures are isolated during cultural program import\n";
}
finally {
  $transaction->rollBack();
}
