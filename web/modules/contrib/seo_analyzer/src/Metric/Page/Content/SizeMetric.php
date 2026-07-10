<?php

namespace Drupal\seo_analyzer\Metric\Page\Content;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class SizeMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('The size of the page html source');
    switch (TRUE) {
      case ($this->value === FALSE):
        $this->impact = 10;
        $message = $this->t("Can not read your page content");
        break;
      case ($this->value === 0):
        $this->impact = 10;
        $message = $this->t("Looks that your site content is empty");
        break;
      case ($this->value > 80000):
        $this->impact = 3;
        $message = $this->t("The site is very big. You should consider rebuilding the page to optimise it's size");
        break;
      case ($this->value > 30000):
        $this->impact = 1;
        $message = $this->t("You should consider some optimisation of the page to decrease it's size");
        break;
      default:
        $message = $this->t('The size of your page is ok');
        break;
    }
    return $message;
  }
}
