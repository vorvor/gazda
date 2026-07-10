<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class HeadersMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Html headings metric');
    switch (TRUE) {
      case (empty($this->value)):
        $this->impact = 7;
        $message = $this->t('Looks the site has no headings at all. You should rebuild your page structure as html headings have strong impact on SEO');
        break;
      case (empty($this->value['h1'])):
        $this->impact = 5;
        $message = $this->t('There is no H1 heading on the site. You should rebuild your page to use main heading as this has strong impact on SEO');
        break;
      case (count($this->value['h1']) > 1):
        $this->impact = 3;
        $message = $this->t('There are multiple H1 headings on the site. You should use only one main heading on the site');
        break;
      case (strlen($this->value['h1'][0]) > 35):
        $this->impact = 3;
        $message = $this->t('The H1 heading is too long. You should consider changing it to something shorter including your main keyword');
        break;
      case (empty($this->value['h2'])):
        $this->impact = 3;
        $message = $this->t('There are no H2 headings on the site. You should consider rebuild your page to use proper headings structure');
        break;
      case (count($this->value['h2']) > 5):
        $this->impact = 1;
        $message = $this->t('There are a lot of H2 headings on the site. You should limit number of H2 headings');
        break;
      case (empty($this->value['h3'])):
        $this->impact = 1;
        $message = $this->t('There are no H3 header on the site. Using proper headings structure can improve the SEO');
        break;
      default:
        $message = $this->t('The headings structure on the site looks very good');
        break;
    }
    return $message;
  }
}
