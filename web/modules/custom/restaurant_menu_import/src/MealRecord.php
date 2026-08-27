<?php

declare(strict_types=1);

namespace Drupal\restaurant_menu_import;

/**
 * A normalized meal extracted from one restaurant source.
 */
final readonly class MealRecord {

  public function __construct(
    public string $sourceId,
    public string $name,
    public string $price,
    public string $description,
    public string $sourceUrl,
  ) {
  }

}
