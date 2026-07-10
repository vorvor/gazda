<?php

namespace Drupal\gazda_seo\Commands;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class GazdaSeoCommands extends DrushCommands {

  /**
   * Bulk updates image alt and title fields for all nodes.
   *
   * @command gazda-seo:update-images
   * @aliases gsu-img
   * @usage gazda-seo:update-images
   *   Updates empty image alt and title fields with node labels.
   */
  public function updateImages() {
    $this->output()->writeln('Starting image alt and title update...');

    // We call the function from the .module file.
    // Note: Since this function uses batch_set(), it won't actually run
    // the batch in a non-interactive Drush session unless we handle it.
    // However, for Drush, it's often better to run the logic directly
    // or use drush_backend_batch_process().

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE);
    $nids = $query->execute();

    if (empty($nids)) {
      $this->output()->writeln('No nodes found to update.');
      return;
    }

    $count = 0;
    $updated = 0;
    $total = count($nids);

    foreach (array_chunk($nids, 50) as $chunk) {
      $nodes = $storage->loadMultiple($chunk);
      foreach ($nodes as $node) {
        if (gazda_seo_update_node_images($node)) {
          $node->save();
          $updated++;
        }
        $count++;
      }
      $this->output()->writeln("Processed $count/$total nodes...");
    }

    $this->output()->writeln("Update complete. $updated nodes updated.");
  }

}
