<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class LoadTimeMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Time used to load the page [sec.]');
    if ($this->value === FALSE) {
      return $this->t('The page load time could not be measured');
    }
    $this->value = round($this->value, 2);
    switch (TRUE) {
      case ($this->value > 3):
        $this->impact = 8;
        $message = $this->t('The site takes very long to load. You should definitely consider rebuilding the page and/or change the hosting provider');
        break;
      case ($this->value > 1):
        $this->impact = 2;
        $message = $this->t('You should optimise your site for faster loading, as this could have strong impact on SEO');
        break;
      default:
        $message = $this->t('The site loads very fast');
        break;
    }
    return $message;
  }
}
