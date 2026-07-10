<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class AltsMetric extends AbstractMetric {
  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Alternate texts for images');
    if ($this->value['total_images'] === 0) {
      return $this->t('There is nothing to do here as there are no images on the page.');
    }

    if ($this->value['images_without_alt'] > 10) {
      $this->impact = 5;
      return $this->t('There are a lot of images without alternate texts on the page. Every image should be described with alt attribute');
    }

    if ($this->value['images_without_alt'] > 0) {
      $this->impact = 3;
      return $this->t('You should optimise your site by adding missing alt descriptions to images, as this could have strong impact on SEO');
    }

    return $this->t('Good! All images on site have alternate descriptions');
  }
}
