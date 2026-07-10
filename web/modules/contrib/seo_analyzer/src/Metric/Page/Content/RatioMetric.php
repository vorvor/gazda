<?php

namespace Drupal\seo_analyzer\Metric\Page\Content;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class RatioMetric extends AbstractMetric {

  public function __construct($inputData) {
    $this->description = $this->t('The ratio of page content to page code [%]');
    $ratio = 0;
    if (!empty($inputData['code_size'])) {
      $ratio = round($inputData['content_size'] / $inputData['code_size'] * 100, 1);
    }
    parent::__construct($ratio);
  }

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    if ($this->value < 10) {
      $this->impact = 8;
      return $this->t('Content to code ratio is too low. Consider adding more text to your page or remove some unnecessary html code');
    }
    if ($this->value < 20) {
      $this->impact = 5;
      return $this->t('Consider adding more text to your page or remove unnecessary html code. Good content have crucial impact on SEO');
    }
    return $this->t('Page has good content to code ratio');
  }
}
