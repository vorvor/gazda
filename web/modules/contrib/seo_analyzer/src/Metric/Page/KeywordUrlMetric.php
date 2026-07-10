<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class KeywordUrlMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the website domain contain the keyword?');
    $this->name = 'Keyword' . $this->value['type'];
    if (stripos($this->value['text'], $this->value['keyword']) === FALSE) {
      $this->impact = $this->value['impact'];
      return $this->t('The website domain does not contain the keyword. A domain including the keyword could improve SEO');
    }
    return $this->t('Good! your website domain contains the keyword');
  }
}
