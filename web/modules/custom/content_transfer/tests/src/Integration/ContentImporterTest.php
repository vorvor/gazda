<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/PackageArchive.php';
require_once __DIR__ . '/../../../src/ContentExporter.php';
require_once __DIR__ . '/../../../src/ContentImporter.php';

use Drupal\content_transfer\ContentExporter;
use Drupal\content_transfer\ContentImporter;
use Drupal\content_transfer\PackageArchive;
use Drupal\Core\File\FileSystemInterface;

function importer_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$entityTypeManager = \Drupal::entityTypeManager();
$fileSystem = \Drupal::service('file_system');
$nodeStorage = $entityTypeManager->getStorage('node');
$nodeId = 681;
$node = $nodeStorage->load($nodeId);
importer_assert($node !== NULL && !$node->get('field_idea_videos')->isEmpty(), 'The fixture node must contain referenced media.');

$temporarySourceUris = [];
foreach ($node->get('field_images')->referencedEntities() as $image) {
  $sourcePath = $fileSystem->realpath($image->getFileUri());
  if ($sourcePath === FALSE || !is_file($sourcePath)) {
    $directory = dirname($image->getFileUri());
    $fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $fileSystem->saveData('content-transfer-test-image', $image->getFileUri(), FileSystemInterface::EXISTS_REPLACE);
    $temporarySourceUris[] = $image->getFileUri();
  }
}

$path = tempnam(sys_get_temp_dir(), 'content-transfer-import-');
importer_assert($path !== FALSE, 'A temporary package path must be created.');
$newEntityPath = tempnam(sys_get_temp_dir(), 'content-transfer-new-');
importer_assert($newEntityPath !== FALSE, 'A second temporary package path must be created.');
$database = \Drupal::database();
$transaction = $database->startTransaction();

try {
  $archive = new PackageArchive();
  $exporter = new ContentExporter(
    $entityTypeManager,
    \Drupal::service('entity_field.manager'),
    $fileSystem,
    $archive,
  );
  $exporter->exportNodes([$nodeId], $path);
  $originalTitle = $node->label();
  $node->setTitle('Content transfer temporary title');
  $node->save();

  $importer = new ContentImporter(
    $entityTypeManager,
    \Drupal::service('entity_field.manager'),
    \Drupal::service('entity_type.bundle.info'),
    $fileSystem,
    \Drupal::currentUser(),
    $archive,
  );
  $result = $importer->import($path, TRUE);

  $nodeStorage->resetCache([$nodeId]);
  $restored = $nodeStorage->load($nodeId);
  importer_assert($restored->label() === $originalTitle, 'Importing with updates enabled must restore exported field values.');
  importer_assert(($result['updated']['node'] ?? 0) === 1, 'The import summary must count the updated node.');
  importer_assert(($result['updated']['media'] ?? 0) >= 1, 'The import summary must count referenced media.');

  $skipped = $importer->import($path, FALSE);
  importer_assert(($skipped['skipped']['node'] ?? 0) === 1, 'Importing without updates must skip an existing node UUID.');

  $newUuid = (new \Drupal\Component\Uuid\Php())->generate();
  $archive->create($newEntityPath, [
    'entities' => [[
      'entity_type' => 'node',
      'bundle' => 'page',
      'uuid' => $newUuid,
      'langcode' => 'hu',
      'fields' => [
        'title' => [['value' => 'Content transfer created page']],
        'status' => [['value' => 1]],
      ],
    ]],
  ], []);
  $created = $importer->import($newEntityPath, TRUE);
  importer_assert(($created['created']['node'] ?? 0) === 1, 'A node with a new UUID must be created.');
  $createdIds = $nodeStorage->getQuery()->accessCheck(FALSE)->condition('uuid', $newUuid)->execute();
  importer_assert(count($createdIds) === 1, 'The newly imported node must be queryable by its source UUID.');

  print "PASS: content import creates, updates, and skips entities by UUID\n";
}
finally {
  $transaction->rollBack();
  if (is_file($path)) {
    unlink($path);
  }
  if (is_file($newEntityPath)) {
    unlink($newEntityPath);
  }
  foreach ($temporarySourceUris as $uri) {
    $fileSystem->delete($uri);
  }
}
