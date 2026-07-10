<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class KeywordDescriptionMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Does the meta description contain the keyword?');
    $this->name = 'Keyword' . $this->value['type'];

    // Check if there is a meta description.
    if (!isset($this->value['text']) || empty($this->value['text'])) {
      $this->impact = 10;
      $this->value['text'] = $this->t('MISSING!');
      return $this->t('You don\'t have a meta description on your page. You should add this because it\'s really important for your SEO');
    }

    // Check if the description contains the keyword.
    if (stripos($this->value['text'], $this->value['keyword']) === FALSE) {
      $this->impact = $this->value['impact'];
      return $this->t('Can not find the keyword in the meta description tag. Adding it could improve SEO');
    }
    return $this->t('Good! Found the keyword in the meta description tag');
  }
}
