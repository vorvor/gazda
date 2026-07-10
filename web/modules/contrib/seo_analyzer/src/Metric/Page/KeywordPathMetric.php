<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class KeywordPathMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the page path contain the keyword?');
    $this->name = 'Keyword' . $this->value['type'];
    if (stripos($this->value['text'], $this->value['keyword']) === FALSE) {
      $this->impact = $this->value['impact'];
      return $this->t('Can not find the keyword in path. Adding it could improve SEO');
    }
    return $this->t('Good! Found the keyword in the path');
  }
}
