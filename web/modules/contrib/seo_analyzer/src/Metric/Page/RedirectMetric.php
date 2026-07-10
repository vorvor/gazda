<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class RedirectMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the page redirect to another url?');
    if ($this->value['redirect']) {
      $this->impact = 2;
      return $this->t('You should avoid redirects, as this could have impact on SEO');
    }
    return $this->t('No');
  }
}
