<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import;

/**
 * Resolves configured source keys into normalized cultural programs.
 */
interface ProgramSourceRegistryInterface {

  /**
   * Returns every available source key in deterministic order.
   *
   * @return string[]
   *   Source keys.
   */
  public function keys(): array;

  /**
   * Fetches one source.
   *
   * @return \Drupal\cultural_program_import\ProgramRecord[]
   *   Normalized records.
   */
  public function fetch(string $source, ?\DateTimeImmutable $from = NULL): array;

}
