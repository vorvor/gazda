<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Backfills remote program image URLs from each original source page.
 */
final class ProgramImageSynchronizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SourceImageResolver $imageResolver,
  ) {}

  /**
   * Synchronizes program images.
   *
   * @return array{total: int, existing: int, matched: int, updated: int, skipped: int, errors: array<int, string>, source_hosts: array<string, int>}
   *   Synchronization counts and per-node errors.
   */
  public function synchronize(bool $dryRun = FALSE, bool $overwrite = FALSE, int $limit = 0): array {
    $result = [
      'total' => 0,
      'existing' => 0,
      'matched' => 0,
      'updated' => 0,
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
        $imageUrl = $this->imageResolver->resolveUrl($sourceUrl);
        if ($imageUrl === NULL) {
          $result['skipped']++;
          continue;
        }
        $result['matched']++;
        $host = (string) parse_url($imageUrl, PHP_URL_HOST);
        $result['source_hosts'][$host] = ($result['source_hosts'][$host] ?? 0) + 1;
        if ($dryRun) {
          continue;
        }

        $node->set('field_program_image', [
          'uri' => $imageUrl,
          'title' => '',
        ]);
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage('Eredeti programkép URL-je frissítve a forrásoldalról.');
        $node->save();
        $result['updated']++;
      }
      catch (\Throwable $error) {
        $result['errors'][(int) $node->id()] = $error->getMessage();
      }
    }

    ksort($result['source_hosts']);
    return $result;
  }

}
