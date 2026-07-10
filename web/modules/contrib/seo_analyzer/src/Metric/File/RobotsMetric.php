<?php

namespace Drupal\seo_analyzer\Metric\File;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class RobotsMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the site use a proper robots.txt file?');
    if (empty($this->value)) {
      $this->impact = 1;
      return 'no';
    }
    elseif (stripos($this->value, 'Disallow: /') !== FALSE) {
      $this->impact = 5;
      return $this->t('Robots.txt file blocks some parts of your site from indexing by search engines. Blocking content can have a big negative impact on SEO');
    }
    return $this->t('Yes');
  }
}
