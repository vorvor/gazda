<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

use InvalidArgumentException;

/**
 * Maps stable source keys to their dedicated adapters.
 */
final class ProgramSourceRegistry implements ProgramSourceRegistryInterface {

  private const TRIBE_SOURCES = [
    'programkereso' => [
      'name' => 'Szentendrei Programkereső',
      'endpoint' => 'https://programok.szentendre.hu/wp-json/tribe/events/v1/events',
      'priority' => 10,
      'fallback_place' => 'Szentendre',
      'website' => 'https://programok.szentendre.hu/',
    ],
    'cultural_center' => [
      'name' => 'Szentendrei Kulturális Központ',
      'endpoint' => 'https://szentendreprogram.hu/wp-json/tribe/events/v1/events',
      'priority' => 60,
      'fallback_place' => 'Szentendrei Kulturális Központ',
      'website' => 'https://szentendreprogram.hu/',
    ],
    'femuz' => [
      'name' => 'Ferenczy Múzeumi Centrum',
      'endpoint' => 'https://femuz.hu/wp-json/tribe/events/v1/events',
      'priority' => 100,
      'fallback_place' => 'Ferenczy Múzeumi Centrum',
      'website' => 'https://femuz.hu/',
    ],
    'teatrum' => [
      'name' => 'Szentendrei Teátrum',
      'endpoint' => 'https://szentendreiteatrum.hu/wp-json/tribe/events/v1/events',
      'priority' => 100,
      'fallback_place' => 'Szentendrei Teátrum',
      'website' => 'https://szentendreiteatrum.hu/',
    ],
  ];

  private const KEYS = [
    'programkereso',
    'cultural_center',
    'partmozi',
    'femuz',
    'skanzen',
    'library',
    'teatrum',
  ];

  public function __construct(
    private readonly TribeEventsSource $tribeEvents,
    private readonly PartMoziSource $partMozi,
    private readonly SkanzenSource $skanzen,
    private readonly LibrarySource $library,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function keys(): array {
    return self::KEYS;
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(string $source, ?\DateTimeImmutable $from = NULL): array {
    if (isset(self::TRIBE_SOURCES[$source])) {
      return $this->tribeEvents->fetch(self::TRIBE_SOURCES[$source], $from);
    }
    return match ($source) {
      'partmozi' => $this->filterFrom($this->partMozi->fetch(), $from),
      'skanzen' => $this->skanzen->fetch($from),
      'library' => $this->library->fetch($from),
      default => throw new InvalidArgumentException(sprintf('Unknown cultural program source: %s', $source)),
    };
  }

  /**
   * Applies an optional lower datetime bound to schedule-only sources.
   *
   * @param \Drupal\cultural_program_import\ProgramRecord[] $records
   *   Normalized records.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Date-filtered records.
   */
  private function filterFrom(array $records, ?\DateTimeImmutable $from): array {
    if ($from === NULL) {
      return $records;
    }
    return array_values(array_filter(
      $records,
      static fn(ProgramRecord $record): bool => ($record->end ?? $record->start) >= $from,
    ));
  }

}
