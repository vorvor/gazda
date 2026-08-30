<?php

declare(strict_types=1);

namespace Drupal\content_transfer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Exports nodes and their directly referenced media and files.
 */
final class ContentExporter {

  /**
   * Exported entity records keyed by entity type and UUID.
   *
   * @var array<string, array>
   */
  private array $records = [];

  /**
   * Payload paths keyed by their archive path.
   *
   * @var array<string, string>
   */
  private array $payloadFiles = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly PackageArchive $archive,
  ) {}

  /**
   * Exports selected nodes to a ZIP package.
   *
   * @param int[] $nodeIds
   *   Node IDs to export.
   * @param string $destination
   *   Local destination path.
   *
   * @return array<string, int>
   *   Entity counts keyed by entity type.
   */
  public function exportNodes(array $nodeIds, string $destination): array {
    $nodeIds = array_values(array_unique(array_map('intval', $nodeIds)));
    if ($nodeIds === []) {
      throw new \InvalidArgumentException('Select at least one node to export.');
    }

    $this->records = [];
    $this->payloadFiles = [];
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nodeIds);
    if (count($nodes) !== count($nodeIds)) {
      throw new \InvalidArgumentException('One or more selected nodes do not exist.');
    }
    foreach ($nodeIds as $nodeId) {
      $this->captureEntity($nodes[$nodeId]);
    }

    $records = array_values($this->records);
    usort($records, static function (array $left, array $right): int {
      $weights = ['file' => 0, 'media' => 1, 'node' => 2];
      return [$weights[$left['entity_type']] ?? 99, $left['uuid']]
        <=> [$weights[$right['entity_type']] ?? 99, $right['uuid']];
    });

    $manifest = [
      'created' => gmdate(DATE_ATOM),
      'entities' => $records,
    ];
    $this->archive->create($destination, $manifest, $this->payloadFiles);

    $counts = ['node' => 0, 'media' => 0, 'file' => 0];
    foreach ($records as $record) {
      $counts[$record['entity_type']] = ($counts[$record['entity_type']] ?? 0) + 1;
    }
    return $counts;
  }

  /**
   * Captures an entity and its supported dependencies.
   *
   * @return bool
   *   TRUE when the entity can be included, or FALSE for a missing file.
   */
  private function captureEntity(ContentEntityInterface $entity): bool {
    $entityTypeId = $entity->getEntityTypeId();
    if (!in_array($entityTypeId, ['node', 'media', 'file'], TRUE)) {
      return FALSE;
    }
    $uuid = $entity->uuid();
    $key = $entityTypeId . ':' . $uuid;
    if (isset($this->records[$key])) {
      return TRUE;
    }

    $sourcePath = NULL;
    if ($entity instanceof FileInterface) {
      $sourcePath = $this->fileSystem->realpath($entity->getFileUri());
      if ($sourcePath === FALSE || !is_file($sourcePath)) {
        return FALSE;
      }
    }

    // Reserve the record before walking references to avoid reference cycles.
    $this->records[$key] = [];
    $record = [
      'entity_type' => $entityTypeId,
      'bundle' => $entity->bundle(),
      'uuid' => $uuid,
      'langcode' => $entity->language()->getId(),
      'fields' => [],
    ];

    $entityType = $entity->getEntityType();
    $excluded = array_filter([
      $entityType->getKey('id'),
      $entityType->getKey('revision'),
      $entityType->getKey('bundle'),
      $entityType->getKey('uuid'),
      $entityType->getKey('langcode'),
      $entityType->getKey('owner'),
    ]);
    $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $entity->bundle());

    foreach ($entity->getFields() as $fieldName => $field) {
      $definition = $definitions[$fieldName] ?? NULL;
      if ($definition === NULL || in_array($fieldName, $excluded, TRUE) || $definition->isComputed() || $definition->isReadOnly() || $definition->isInternal() || $field->isEmpty()) {
        continue;
      }

      $values = $field->getValue();
      foreach ($field as $delta => $item) {
        if (!isset($values[$delta]['target_id'])) {
          continue;
        }
        $target = $item->entity;
        if (!$target instanceof ContentEntityInterface || $target->uuid() === NULL) {
          continue;
        }
        $targetType = $target->getEntityTypeId();
        if (in_array($targetType, ['media', 'file'], TRUE) && !$this->captureEntity($target)) {
          unset($values[$delta]);
          continue;
        }
        $values[$delta]['target_type'] = $targetType;
        $values[$delta]['target_uuid'] = $target->uuid();
        unset($values[$delta]['target_id']);
      }
      $record['fields'][$fieldName] = array_values($values);
    }

    if ($entity instanceof FileInterface) {
      $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $entity->getFilename()) ?: 'file';
      $payload = sprintf('files/%s/%s', $uuid, $filename);
      $record['payload'] = $payload;
      $this->payloadFiles[$payload] = $sourcePath;
    }

    $this->records[$key] = $record;
    return TRUE;
  }

}
