<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class KeywordTitleMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the meta title contain the keyword?');
    $this->name = 'Keyword' . $this->value['type'];

    // Check if there is a meta title.
    if (!isset($this->value['text']) || empty($this->value['text'])) {
      $this->impact = 10;
      return $this->t('You don\'t have a meta <title> tag. You should add this because it\'s really important for your SEO');
    }

    // Check if the title contains the keyword.
    if (stripos($this->value['text'], $this->value['keyword']) === FALSE) {
      $this->impact = $this->value['impact'];
      return $this->t('Can not find the keyword in the meta title tag. Adding it could improve SEO');
    }
    return $this->t('Good! Found the keyword in the meta title tag');
  }
}
