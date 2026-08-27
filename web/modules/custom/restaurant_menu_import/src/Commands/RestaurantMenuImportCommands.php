<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import\Commands;

use Drupal\restaurant_menu_import\RestaurantMenuImporter;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for restaurant menu imports.
 */
final class RestaurantMenuImportCommands extends DrushCommands {

  public function __construct(
    private readonly RestaurantMenuImporter $importer,
  ) {
    parent::__construct();
  }

  /**
   * Imports meals from one restaurant's configured sources.
   *
   * @param int $restaurant_id
   *   Restaurant node ID.
   * @param array $options
   *   Command options.
   *
   * @command restaurant-menu:import
   * @aliases rmi
   * @option dry-run Report changes without saving meal content.
   * @usage restaurant-menu:import 540
   * @usage restaurant-menu:import 540 --dry-run
   */
  public function import(int $restaurant_id, array $options = ['dry-run' => FALSE]): void {
    $result = $this->importer->import($restaurant_id, (bool) $options['dry-run']);
    $this->output()->writeln(sprintf(
      'Sources: %d; extracted: %d; created: %d; updated: %d; unchanged: %d; errors: %d',
      $result['sources'],
      $result['extracted'],
      $result['created'],
      $result['updated'],
      $result['unchanged'],
      $result['errors'],
    ));
  }

}
