<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\NodeInterface;

/**
 * Backfills managed program images from each node's original source page.
 */
final class ProgramImageSynchronizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly SourceImageResolver $imageResolver,
  ) {}

  /**
   * Synchronizes program images.
   *
   * @return array{total: int, existing: int, matched: int, downloaded: int, reused: int, skipped: int, errors: array<int, string>, source_hosts: array<string, int>}
   *   Synchronization counts and per-node errors.
   */
  public function synchronize(bool $dryRun = FALSE, bool $overwrite = FALSE, int $limit = 0): array {
    $result = [
      'total' => 0,
      'existing' => 0,
      'matched' => 0,
      'downloaded' => 0,
      'reused' => 0,
      'skipped' => 0,
      'errors' => [],
      'source_hosts' => [],
    ];

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cultural_program')
      ->sort('nid', 'ASC');
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();

    foreach ($nodeStorage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $result['total']++;
      if (!$node->hasField('field_program_image')) {
        throw new \LogicException('The cultural program image field is not configured.');
      }
      if (!$overwrite && !$node->get('field_program_image')->isEmpty()) {
        $result['existing']++;
        continue;
      }

      $sourceUrl = (string) $node->get('field_source_url')->uri;
      if ($sourceUrl === '') {
        $result['skipped']++;
        continue;
      }

      try {
        $image = $this->imageResolver->resolve($sourceUrl);
        if ($image === NULL) {
          $result['skipped']++;
          continue;
        }
        $result['matched']++;
        $host = (string) parse_url($image['url'], PHP_URL_HOST);
        $result['source_hosts'][$host] = ($result['source_hosts'][$host] ?? 0) + 1;
        if ($dryRun) {
          continue;
        }

        [$file, $created] = $this->storeImage($image);
        $result[$created ? 'downloaded' : 'reused']++;
        $node->set('field_program_image', [
          'target_id' => $file->id(),
          'alt' => $node->label(),
          'title' => '',
          'width' => $image['width'],
          'height' => $image['height'],
        ]);
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage('Programkép frissítve az eredeti forrásoldalról.');
        $node->save();
      }
      catch (\Throwable $error) {
        $result['errors'][(int) $node->id()] = $error->getMessage();
      }
    }

    ksort($result['source_hosts']);
    return $result;
  }

  /**
   * Saves an image once and reuses it when multiple programs share the asset.
   *
   * @param array{url: string, data: string, mime: string, width: int, height: int} $image
   *   Resolved source image.
   *
   * @return array{0: \Drupal\file\FileInterface, 1: bool}
   *   File entity and whether it was created.
   */
  private function storeImage(array $image): array {
    $extension = match ($image['mime']) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
      default => throw new \UnexpectedValueException('Unsupported source image type.'),
    };
    $directory = 'public://cultural-programs';
    if (!$this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      throw new \RuntimeException('Unable to prepare the cultural program image directory.');
    }

    $hash = substr(hash('sha256', $image['url']), 0, 20);
    $uri = sprintf('%s/program-%s.%s', $directory, $hash, $extension);
    $fileStorage = $this->entityTypeManager->getStorage('file');
    $existing = $fileStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', $uri)
      ->range(0, 1)
      ->execute();
    if ($existing !== []) {
      $file = $fileStorage->load(reset($existing));
      if ($file instanceof FileInterface) {
        return [$file, FALSE];
      }
    }

    $file = $this->fileRepository->writeData($image['data'], $uri, FileExists::Error);
    $file->setPermanent();
    $file->save();
    return [$file, TRUE];
  }

}
