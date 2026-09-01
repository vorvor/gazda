<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Creates and updates cultural places and programs without destructive gaps.
 */
final class ProgramSynchronizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Synchronizes normalized program records.
   *
   * Empty optional source values intentionally preserve destination content.
   * Programs omitted by a source are also left unchanged.
   *
   * @param \Drupal\cultural_program_import\ProgramRecord[] $records
   *   Normalized program records.
   * @param bool $dryRun
   *   Whether to report without saving entities.
   * @param \DateTimeImmutable|null $now
   *   Import time, primarily injectable for deterministic tests.
   *
   * @return array{created: int, updated: int, unchanged: int, places_created: int, places_updated: int}
   *   Synchronization counts.
   */
  public function synchronize(array $records, bool $dryRun = FALSE, ?\DateTimeImmutable $now = NULL): array {
    $result = [
      'created' => 0,
      'updated' => 0,
      'unchanged' => 0,
      'places_created' => 0,
      'places_updated' => 0,
    ];
    $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    usort($records, static fn(ProgramRecord $left, ProgramRecord $right): int => [$left->priority, $left->sourceName, $left->externalId] <=> [$right->priority, $right->sourceName, $right->externalId]);

    foreach ($records as $record) {
      if (!$record instanceof ProgramRecord) {
        continue;
      }
      [$place, $placeState] = $this->upsertPlace($record, $dryRun);
      if ($placeState === 'created') {
        $result['places_created']++;
      }
      elseif ($placeState === 'updated') {
        $result['places_updated']++;
      }

      $state = $this->upsertProgram($record, $place, $now, $dryRun);
      $result[$state]++;
    }

    return $result;
  }

  /**
   * Creates or enriches one cultural place.
   *
   * @return array{0: \Drupal\node\NodeInterface, 1: string}
   *   Place and state (`created`, `updated`, or `unchanged`).
   */
  private function upsertPlace(ProgramRecord $record, bool $dryRun): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $place = NULL;
    $normalizedName = $this->normalize($record->placeName);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cultural_place')
      ->execute();
    foreach ($storage->loadMultiple($ids) as $candidate) {
      if ($candidate instanceof NodeInterface && $this->normalize($candidate->label()) === $normalizedName) {
        $place = $candidate;
        break;
      }
    }

    if (!$place instanceof NodeInterface) {
      $values = [
        'type' => 'cultural_place',
        'title' => $record->placeName,
        'langcode' => 'hu',
        'status' => 1,
        'uid' => $this->currentUser->id(),
        'promote' => 0,
        'sticky' => 0,
      ];
      if ($record->placeAddress !== '') {
        $values['field_place_address'] = $record->placeAddress;
      }
      if ($record->placeWebsite !== '') {
        $values['field_place_website'] = ['uri' => $record->placeWebsite];
        $values['field_source_url'] = ['uri' => $record->placeWebsite];
      }
      $values['field_source_name'] = $record->sourceName;
      $place = $storage->create($values);
      if (!$dryRun) {
        $place->save();
      }
      return [$place, 'created'];
    }

    $changed = FALSE;
    $existingSource = (string) $place->get('field_source_name')->value;
    $incomingIsMoreAuthoritative = $existingSource === '' || $record->priority > $this->sourcePriority($existingSource);
    if ($record->placeAddress !== '' && ($place->get('field_place_address')->isEmpty() || $incomingIsMoreAuthoritative) && $place->get('field_place_address')->value !== $record->placeAddress) {
      $place->set('field_place_address', $record->placeAddress);
      $changed = TRUE;
    }
    if ($record->placeWebsite !== '' && ($place->get('field_place_website')->isEmpty() || $incomingIsMoreAuthoritative) && $place->get('field_place_website')->uri !== $record->placeWebsite) {
      $place->set('field_place_website', ['uri' => $record->placeWebsite]);
      if ($place->get('field_source_url')->isEmpty() || $incomingIsMoreAuthoritative) {
        $place->set('field_source_url', ['uri' => $record->placeWebsite]);
      }
      $changed = TRUE;
    }
    if ($incomingIsMoreAuthoritative && $existingSource !== $record->sourceName) {
      $place->set('field_source_name', $record->sourceName);
      $changed = TRUE;
    }
    if ($changed && !$dryRun) {
      $place->setNewRevision(TRUE);
      $place->setRevisionLogMessage('Kulturális programforrásból frissített helyszín.');
      $place->save();
    }
    return [$place, $changed ? 'updated' : 'unchanged'];
  }

  /**
   * Creates or updates one cultural program.
   */
  private function upsertProgram(ProgramRecord $record, NodeInterface $place, \DateTimeImmutable $now, bool $dryRun): string {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cultural_program')
      ->condition('field_source_name', $record->sourceName)
      ->condition('field_external_id', $record->externalId)
      ->range(0, 1)
      ->execute();
    $program = $ids === [] ? NULL : $storage->load(reset($ids));
    $exactSource = $program instanceof NodeInterface;

    if (!$program instanceof NodeInterface && $place->id() !== NULL) {
      $candidateIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'cultural_program')
        ->condition('field_program_start', $this->formatDate($record->start))
        ->condition('field_program_place.target_id', $place->id())
        ->execute();
      foreach ($storage->loadMultiple($candidateIds) as $candidate) {
        if ($candidate instanceof NodeInterface && $this->normalize($candidate->label()) === $this->normalize($record->title)) {
          $program = $candidate;
          break;
        }
      }
    }

    $categoryIds = $record->categories === [] ? [] : $this->resolveCategories($record->categories, $dryRun);
    if (!$program instanceof NodeInterface) {
      $values = [
        'type' => 'cultural_program',
        'title' => $record->title,
        'langcode' => 'hu',
        'status' => 1,
        'uid' => $this->currentUser->id(),
        'promote' => 0,
        'sticky' => 0,
        'field_program_start' => $this->formatDate($record->start),
        'field_program_place' => ['target_id' => $place->id()],
        'field_program_family' => $record->family,
        'field_program_all_day' => $record->allDay,
        'field_program_status' => $record->status,
        'field_external_id' => $record->externalId,
        'field_source_name' => $record->sourceName,
        'field_source_url' => ['uri' => $record->sourceUrl],
        'field_program_last_seen' => $this->formatDate($now),
      ];
      $this->addOptionalCreateValues($values, $record, $categoryIds);
      $program = $storage->create($values);
      if (!$dryRun) {
        $program->save();
      }
      return 'created';
    }

    $existingPriority = $this->sourcePriority((string) $program->get('field_source_name')->value);
    if (!$exactSource && $record->priority < $existingPriority) {
      return 'unchanged';
    }

    $changed = FALSE;
    $changed = $this->setScalar($program, 'title', $record->title) || $changed;
    $changed = $this->setScalar($program, 'field_program_start', $this->formatDate($record->start)) || $changed;
    $changed = $this->setScalar($program, 'field_program_place', ['target_id' => $place->id()]) || $changed;
    $changed = $this->setScalar($program, 'field_program_family', $record->family) || $changed;
    $changed = $this->setScalar($program, 'field_program_all_day', $record->allDay) || $changed;
    $changed = $this->setScalar($program, 'field_program_status', $record->status) || $changed;
    $changed = $this->setScalar($program, 'field_external_id', $record->externalId) || $changed;
    $changed = $this->setScalar($program, 'field_source_name', $record->sourceName) || $changed;
    $changed = $this->setScalar($program, 'field_source_url', ['uri' => $record->sourceUrl]) || $changed;
    $refreshLastSeen = $this->shouldRefreshLastSeen($program, $now);
    if ($refreshLastSeen) {
      $program->set('field_program_last_seen', $this->formatDate($now));
    }

    if ($record->description !== '') {
      $changed = $this->setScalar($program, 'field_program_description', ['value' => $record->description, 'format' => 'plain_text']) || $changed;
    }
    if ($record->end !== NULL) {
      $changed = $this->setScalar($program, 'field_program_end', $this->formatDate($record->end)) || $changed;
    }
    if ($record->ticketUrl !== '') {
      $changed = $this->setScalar($program, 'field_program_ticket', ['uri' => $record->ticketUrl]) || $changed;
    }
    if ($record->price !== '') {
      $changed = $this->setScalar($program, 'field_program_price', $record->price) || $changed;
    }
    if ($categoryIds !== []) {
      $changed = $this->setScalar($program, 'field_program_category', array_map(static fn($id): array => ['target_id' => $id], $categoryIds)) || $changed;
    }
    if ($record->sourceUpdated !== NULL) {
      $changed = $this->setScalar($program, 'field_program_source_updated', $this->formatDate($record->sourceUpdated)) || $changed;
    }
    if (!$program->isPublished()) {
      $program->setPublished();
      $changed = TRUE;
    }

    if ($changed && !$dryRun) {
      $program->setNewRevision(TRUE);
      $program->setRevisionLogMessage('Kulturális programforrásból frissítve.');
      $program->save();
    }
    elseif ($refreshLastSeen && !$dryRun) {
      $program->setNewRevision(FALSE);
      $program->save();
    }
    return $changed ? 'updated' : 'unchanged';
  }

  /**
   * Refreshes operational source presence at most once per UTC day.
   */
  private function shouldRefreshLastSeen(NodeInterface $program, \DateTimeImmutable $now): bool {
    $stored = (string) $program->get('field_program_last_seen')->value;
    if ($stored === '') {
      return TRUE;
    }
    $lastSeen = \DateTimeImmutable::createFromFormat(
      '!' . DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
      $stored,
      new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE),
    );
    if (!$lastSeen instanceof \DateTimeImmutable) {
      return TRUE;
    }
    $utcNow = $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $lastSeen->format('Y-m-d') !== $utcNow->format('Y-m-d');
  }

  /**
   * Adds non-empty optional values while creating a program.
   */
  private function addOptionalCreateValues(array &$values, ProgramRecord $record, array $categoryIds): void {
    if ($record->description !== '') {
      $values['field_program_description'] = ['value' => $record->description, 'format' => 'plain_text'];
    }
    if ($record->end !== NULL) {
      $values['field_program_end'] = $this->formatDate($record->end);
    }
    if ($record->ticketUrl !== '') {
      $values['field_program_ticket'] = ['uri' => $record->ticketUrl];
    }
    if ($record->price !== '') {
      $values['field_program_price'] = $record->price;
    }
    if ($categoryIds !== []) {
      $values['field_program_category'] = array_map(static fn($id): array => ['target_id' => $id], $categoryIds);
    }
    if ($record->sourceUpdated !== NULL) {
      $values['field_program_source_updated'] = $this->formatDate($record->sourceUpdated);
    }
  }

  /**
   * Resolves category labels to taxonomy term IDs.
   *
   * @return int[]
   *   Term IDs in source order.
   */
  private function resolveCategories(array $categories, bool $dryRun): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'cultural_program_category')
      ->execute();
    $byName = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      if ($term instanceof TermInterface) {
        $byName[$this->normalize($term->label())] = $term;
      }
    }

    $resolved = [];
    foreach (array_values(array_unique(array_filter(array_map('trim', $categories)))) as $category) {
      $key = $this->normalize($category);
      $term = $byName[$key] ?? NULL;
      if (!$term instanceof TermInterface) {
        $term = $storage->create([
          'vid' => 'cultural_program_category',
          'name' => $category,
          'langcode' => 'hu',
        ]);
        if (!$dryRun) {
          $term->save();
          $byName[$key] = $term;
        }
      }
      if ($term->id() !== NULL) {
        $resolved[] = (int) $term->id();
      }
    }
    return $resolved;
  }

  /**
   * Sets one field only when its normalized item values differ.
   */
  private function setScalar(NodeInterface $entity, string $fieldName, mixed $value): bool {
    $clone = clone $entity->get($fieldName);
    $clone->setValue($value);
    if ($entity->get($fieldName)->equals($clone)) {
      return FALSE;
    }
    $entity->set($fieldName, $value);
    return TRUE;
  }

  /**
   * Formats a source date in Drupal's UTC storage format.
   */
  private function formatDate(\DateTimeImmutable $date): string {
    return $date
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

  /**
   * Returns the known authority of an existing source.
   */
  private function sourcePriority(string $sourceName): int {
    return match ($sourceName) {
      'Szentendrei Programkereső' => 10,
      'Szentendrei Kulturális Központ' => 60,
      'P’Art Mozi',
      'Ferenczy Múzeumi Centrum',
      'Skanzen',
      'Hamvas Béla Pest Megyei Könyvtár',
      'Szentendrei Teátrum' => 100,
      default => 0,
    };
  }

  /**
   * Builds a case- and punctuation-insensitive matching key.
   */
  private function normalize(string $value): string {
    return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value)));
  }

}
