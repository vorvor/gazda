<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class SSLMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the site use an encrypted connection?');
    if (empty($this->value)) {
      $this->impact = 3;
      return $this->t('You should use encrypted connection, as this could have strong impact on SEO');
    }
    return $this->t('Yes');
  }
}
