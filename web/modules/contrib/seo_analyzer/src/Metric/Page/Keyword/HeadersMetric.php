<?php

namespace Drupal\seo_analyzer\Metric\Page\Keyword;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class HeadersMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Do the page headings (H1, H2) contain the keyword?');
    if (empty($this->value[self::HEADERS]['h1'][0])
      || stripos($this->value[self::HEADERS]['h1'][0], $this->value['keyword']) === FALSE) {
      $this->impact = 7;
      return $this->t('The main H1 header does not contain the keyword. Adding it could strongly improve SEO');
    }
    if (!empty($this->value[self::HEADERS]['h2'])) {
      $anyHasKeyword = FALSE;
      foreach ($this->value[self::HEADERS]['h2'] as $h2) {
        if (stripos($h2, $this->value['keyword']) !== FALSE) {
          $anyHasKeyword = TRUE;
        }
      }
      if ($anyHasKeyword) {
        return $this->t('Good! The site headings contain the keyword');
      }
    }
    $this->impact = 3;
    return $this->t('The site H2 headings do not contain the keyword. Adding it could strongly improve SEO');
  }
}
