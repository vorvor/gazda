<?php

namespace Drupal\seo_analyzer\Metric\File;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class SitemapMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the site use a site map file "sitemap.xml"?');
    if (empty($this->value)) {
      $this->impact = 1;
      return $this->t('You should consider adding a sitemap.xml file, as this could help with indexing');
    }
    return $this->t('Yes');
  }
}
