<?php

declare(strict_types=1);

namespace Drupal\content_transfer;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;

/**
 * Imports portable content packages by entity UUID.
 */
final class ContentImporter {

  /**
   * Resolved entity IDs keyed by entity type and UUID.
   *
   * @var array<string, int|string>
   */
  private array $idMap = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly FileSystemInterface $fileSystem,
    private readonly AccountProxyInterface $currentUser,
    private readonly PackageArchive $archive,
  ) {}

  /**
   * Imports a package.
   *
   * @return array<string, array<string, int>>
   *   Created, updated, and skipped counts keyed by entity type.
   */
  public function import(string $path, bool $updateExisting): array {
    $manifest = $this->archive->readManifest($path);
    $records = $this->validateRecords($manifest['entities']);
    $this->idMap = [];
    $result = [
      'created' => ['node' => 0, 'media' => 0, 'file' => 0],
      'updated' => ['node' => 0, 'media' => 0, 'file' => 0],
      'skipped' => ['node' => 0, 'media' => 0, 'file' => 0],
    ];

    usort($records, static function (array $left, array $right): int {
      $weights = ['file' => 0, 'media' => 1, 'node' => 2];
      return [$weights[$left['entity_type']], $left['uuid']]
        <=> [$weights[$right['entity_type']], $right['uuid']];
    });

    foreach ($records as $record) {
      $entityTypeId = $record['entity_type'];
      $existing = $this->loadByUuid($entityTypeId, $record['uuid']);
      if ($existing !== NULL) {
        $this->assertBundleMatches($existing, $record);
        $this->idMap[$entityTypeId . ':' . $record['uuid']] = $existing->id();
        if (!$updateExisting) {
          $result['skipped'][$entityTypeId]++;
          continue;
        }
      }

      $entity = $existing ?? $this->createEntity($record);
      if ($entity instanceof FileInterface) {
        $this->writeFilePayload($entity, $record, $path, $existing !== NULL);
      }
      $this->applyFields($entity, $record);
      if ($existing !== NULL && $entity instanceof RevisionableInterface && $entity->getEntityType()->isRevisionable()) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();
      $this->idMap[$entityTypeId . ':' . $record['uuid']] = $entity->id();
      $result[$existing === NULL ? 'created' : 'updated'][$entityTypeId]++;
    }

    return $result;
  }

  /**
   * Validates package records before any writes occur.
   */
  private function validateRecords(array $records): array {
    $seen = [];
    foreach ($records as $record) {
      if (!is_array($record) || !isset($record['entity_type'], $record['bundle'], $record['uuid'], $record['langcode'], $record['fields']) || !is_array($record['fields'])) {
        throw new \InvalidArgumentException('The package contains an invalid entity record.');
      }
      $entityTypeId = $record['entity_type'];
      if (!in_array($entityTypeId, ['node', 'media', 'file'], TRUE)) {
        throw new \InvalidArgumentException(sprintf('Entity type "%s" is not supported.', $entityTypeId));
      }
      if (!Uuid::isValid($record['uuid'])) {
        throw new \InvalidArgumentException('The package contains an invalid entity UUID.');
      }
      $key = $entityTypeId . ':' . $record['uuid'];
      if (isset($seen[$key])) {
        throw new \InvalidArgumentException(sprintf('The package contains duplicate entity "%s".', $key));
      }
      $seen[$key] = TRUE;

      if ($entityTypeId !== 'file' && !isset($this->bundleInfo->getBundleInfo($entityTypeId)[$record['bundle']])) {
        throw new \InvalidArgumentException(sprintf('The required %s bundle "%s" is not installed.', $entityTypeId, $record['bundle']));
      }
      if ($entityTypeId === 'file' && (!isset($record['payload']) || !is_string($record['payload']))) {
        throw new \InvalidArgumentException('An exported file has no payload path.');
      }
    }
    return $records;
  }

  /**
   * Creates an unsaved entity from package metadata.
   */
  private function createEntity(array $record): ContentEntityInterface {
    $entityType = $this->entityTypeManager->getDefinition($record['entity_type']);
    $values = [
      $entityType->getKey('uuid') => $record['uuid'],
      $entityType->getKey('langcode') => $record['langcode'],
    ];
    if ($bundleKey = $entityType->getKey('bundle')) {
      $values[$bundleKey] = $record['bundle'];
    }
    if ($ownerKey = $entityType->getKey('owner')) {
      $values[$ownerKey] = $this->currentUser->id();
    }
    return $this->entityTypeManager->getStorage($record['entity_type'])->create($values);
  }

  /**
   * Applies exported fields after resolving portable references.
   */
  private function applyFields(ContentEntityInterface $entity, array $record): void {
    $definitions = $this->entityFieldManager->getFieldDefinitions($record['entity_type'], $record['bundle']);
    foreach ($record['fields'] as $fieldName => $values) {
      $definition = $definitions[$fieldName] ?? NULL;
      if ($definition === NULL) {
        throw new \InvalidArgumentException(sprintf('The destination is missing field "%s" on %s:%s.', $fieldName, $record['entity_type'], $record['bundle']));
      }
      if ($definition->isComputed() || $definition->isReadOnly() || $definition->isInternal() || ($entity instanceof FileInterface && in_array($fieldName, ['uri', 'filesize'], TRUE))) {
        continue;
      }
      $entity->set($fieldName, $this->resolveReferences($values));
    }
  }

  /**
   * Replaces UUID reference metadata with destination entity IDs.
   */
  private function resolveReferences(array $values): array {
    foreach ($values as $delta => $value) {
      if (!is_array($value) || !isset($value['target_type'], $value['target_uuid'])) {
        continue;
      }
      $targetType = $value['target_type'];
      $targetUuid = $value['target_uuid'];
      $mapKey = $targetType . ':' . $targetUuid;
      $targetId = $this->idMap[$mapKey] ?? NULL;
      if ($targetId === NULL) {
        $target = $this->loadByUuid($targetType, $targetUuid);
        $targetId = $target?->id();
      }
      if ($targetId === NULL) {
        throw new \InvalidArgumentException(sprintf('Referenced entity "%s" is not available on the destination.', $mapKey));
      }
      $values[$delta]['target_id'] = $targetId;
      unset($values[$delta]['target_type'], $values[$delta]['target_uuid']);
    }
    return $values;
  }

  /**
   * Writes an archived file payload to a controlled public location.
   */
  private function writeFilePayload(FileInterface $entity, array $record, string $archivePath, bool $existing): void {
    $contents = $this->archive->readEntry($archivePath, $record['payload']);
    $filename = $this->firstFieldValue($record['fields']['filename'] ?? []) ?: basename($record['payload']);
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'file';
    $destination = $existing
      ? $entity->getFileUri()
      : sprintf('public://content-transfer/%s/%s', $record['uuid'], $filename);
    $directory = dirname($destination);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException(sprintf('Unable to prepare file destination "%s".', $directory));
    }
    $savedUri = $this->fileSystem->saveData($contents, $destination, FileSystemInterface::EXISTS_REPLACE);
    if ($savedUri === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write imported file "%s".', $filename));
    }
    $entity->setFileUri($savedUri);
    $entity->setFilename($filename);
  }

  /**
   * Loads a content entity by UUID.
   */
  private function loadByUuid(string $entityTypeId, string $uuid): ?ContentEntityInterface {
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $entity = $storage->load(reset($ids));
    return $entity instanceof ContentEntityInterface ? $entity : NULL;
  }

  /**
   * Ensures a UUID does not resolve to an incompatible bundle.
   */
  private function assertBundleMatches(ContentEntityInterface $entity, array $record): void {
    if ($entity->bundle() !== $record['bundle']) {
      throw new \InvalidArgumentException(sprintf('Existing entity %s:%s has an incompatible bundle.', $record['entity_type'], $record['uuid']));
    }
  }

  /**
   * Returns a scalar from a standard field value array.
   */
  private function firstFieldValue(array $values): ?string {
    $value = $values[0]['value'] ?? NULL;
    return is_scalar($value) ? (string) $value : NULL;
  }

}
