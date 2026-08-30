<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/PackageArchive.php';
require_once __DIR__ . '/../../../src/ContentExporter.php';

use Drupal\content_transfer\ContentExporter;
use Drupal\content_transfer\PackageArchive;

function exporter_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$nodeId = 681;
$node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);
exporter_assert($node !== NULL && !$node->get('field_idea_videos')->isEmpty(), 'The fixture node must contain referenced media.');
$fileSystem = \Drupal::service('file_system');
$temporarySourceUris = [];
foreach ($node->get('field_images')->referencedEntities() as $image) {
  $sourcePath = $fileSystem->realpath($image->getFileUri());
  if ($sourcePath === FALSE || !is_file($sourcePath)) {
    $directory = dirname($image->getFileUri());
    $fileSystem->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
    $fileSystem->saveData('content-transfer-test-image', $image->getFileUri(), \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
    $temporarySourceUris[] = $image->getFileUri();
  }
}
$path = tempnam(sys_get_temp_dir(), 'content-transfer-export-');
exporter_assert($path !== FALSE, 'A temporary export path must be created.');

try {
  $archive = new PackageArchive();
  $exporter = new ContentExporter(
    \Drupal::entityTypeManager(),
    \Drupal::service('entity_field.manager'),
    \Drupal::service('file_system'),
    $archive,
  );
  $summary = $exporter->exportNodes([$nodeId], $path);
  $manifest = $archive->readManifest($path);

  $types = array_count_values(array_column($manifest['entities'], 'entity_type'));
  exporter_assert(($types['node'] ?? 0) === 1, 'Exactly one selected node must be exported.');
  exporter_assert(($types['media'] ?? 0) >= 1, 'Referenced media must be exported.');
  exporter_assert(($types['file'] ?? 0) >= 1, 'Files referenced by media must be exported.');
  exporter_assert($summary['node'] === 1, 'The export summary must count the node.');

  $nodeRecord = array_values(array_filter(
    $manifest['entities'],
    static fn (array $record): bool => $record['entity_type'] === 'node',
  ))[0];
  $reference = $nodeRecord['fields']['field_idea_videos'][0] ?? [];
  exporter_assert(isset($reference['target_uuid'], $reference['target_type']), 'Media references must use portable UUIDs.');
  exporter_assert(!isset($reference['target_id']), 'Source entity IDs must not be exported for media references.');

  foreach ($manifest['entities'] as $record) {
    if ($record['entity_type'] === 'file') {
      exporter_assert(isset($record['payload']), 'Exported files must identify their archive payload.');
      exporter_assert($archive->readEntry($path, $record['payload']) !== '', 'Exported file payload must be readable.');
    }
  }

  print "PASS: node export includes referenced media and files\n";
}
finally {
  if (is_file($path)) {
    unlink($path);
  }
  foreach ($temporarySourceUris as $uri) {
    $fileSystem->delete($uri);
  }
}
