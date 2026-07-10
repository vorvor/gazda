<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class KeywordMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('OLD Does the meta description contain the keyword?');
    $this->name = 'Keyword' . $this->value['type'];
    if (stripos($this->value['text'], $this->value['keyword']) === FALSE) {
      $this->impact = $this->value['impact'];
      return $this->t('Can not find the keyword in the meta description tag. Adding it could improve SEO');
    }
    return $this->t('Good! Found the keyword in the meta description tag');
  }
}
