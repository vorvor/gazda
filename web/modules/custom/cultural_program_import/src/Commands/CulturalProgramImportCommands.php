<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import\Commands;

use Drupal\cultural_program_import\CulturalProgramImporter;
use Drupal\cultural_program_import\ProgramImageSynchronizer;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;

/**
 * Drush commands for Szentendre cultural program imports.
 */
final class CulturalProgramImportCommands extends DrushCommands {

  public function __construct(
    private readonly CulturalProgramImporter $importer,
    private readonly ProgramImageSynchronizer $imageSynchronizer,
  ) {
    parent::__construct();
  }

  /**
   * Imports current cultural programs and referenced places.
   *
   * @param string $source
   *   Source key, or `all`.
   * @param array $options
   *   Command options.
   *
   * @command cultural-program:import
   * @aliases cpi
   * @option dry-run Report changes without saving content.
   * @usage cultural-program:import
   * @usage cultural-program:import partmozi
   * @usage cultural-program:import all --dry-run
   */
  public function import(string $source = 'all', array $options = ['dry-run' => FALSE]): int {
    $result = $this->importer->import($source, (bool) $options['dry-run']);
    foreach ($result['sources'] as $key => $count) {
      $this->output()->writeln(sprintf('%s: %d', $key, $count));
    }
    $this->output()->writeln(sprintf(
      'Fetched: %d; created: %d; updated: %d; unchanged: %d; places created: %d; places updated: %d; source errors: %d',
      $result['fetched'],
      $result['created'],
      $result['updated'],
      $result['unchanged'],
      $result['places_created'],
      $result['places_updated'],
      count($result['errors']),
    ));
    foreach ($result['errors'] as $key => $message) {
      $this->logger()->error(sprintf('%s: %s', $key, $message));
    }
    return $result['errors'] === [] ? Command::SUCCESS : Command::FAILURE;
  }

  /**
   * Downloads card images from each program's original source page.
   *
   * @param array $options
   *   Command options.
   *
   * @command cultural-program:image-sync
   * @aliases cpis
   * @option dry-run Discover usable images without changing content.
   * @option overwrite Replace existing program images.
   * @option limit Process at most this many nodes; zero means all.
   * @usage cultural-program:image-sync --dry-run
   * @usage cultural-program:image-sync
   */
  public function imageSync(array $options = [
    'dry-run' => FALSE,
    'overwrite' => FALSE,
    'limit' => 0,
  ]): int {
    $result = $this->imageSynchronizer->synchronize(
      (bool) $options['dry-run'],
      (bool) $options['overwrite'],
      max(0, (int) $options['limit']),
    );
    $this->output()->writeln(sprintf(
      'Programs: %d; existing: %d; matched: %d; downloaded: %d; reused: %d; skipped: %d; errors: %d',
      $result['total'],
      $result['existing'],
      $result['matched'],
      $result['downloaded'],
      $result['reused'],
      $result['skipped'],
      count($result['errors']),
    ));
    foreach ($result['source_hosts'] as $host => $count) {
      $this->output()->writeln(sprintf('%s: %d', $host, $count));
    }
    foreach ($result['errors'] as $nid => $message) {
      $this->logger()->error(sprintf('Node %d: %s', $nid, $message));
    }
    return $result['errors'] === [] ? Command::SUCCESS : Command::FAILURE;
  }

}
