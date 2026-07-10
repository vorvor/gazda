<?php

namespace Drupal\seo_analyzer\Metric\Page\Url;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class LengthMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('The size of the page URL');
    if ($this->value > 75) {
      $this->impact = 4;
      return $this->t("The site URL is very long. You should consider using some shorter URL");
    }
    if ($this->value > 60) {
      $this->impact = 1;
      return $this->t("You should consider using some shorter URL");
    }
    return 'The size of URL is ok';
  }
}
