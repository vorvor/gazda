<?php

namespace Drupal\seo_analyzer\Metric\Page;

class HeadersKeywordDensityMetric extends AbstractKeywordDensityMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Keyword density in page headers');
    $keywords = $overusedWords = $headers = [];
    if (!empty($this->value['headers'])) {
      foreach ($this->value['headers'] as $header => $headersContent) {
        $keywords[$header] = $this->analyseKeywords(
          implode(" ", $headersContent),
          $this->value['stop_words'],
          3
        );
        $overUsed = $this->getOverusedKeywords($keywords[$header], 35, 3);
        if (!empty($overUsed)) {
          $headers[] = $header;
          $overusedWords = array_merge($overusedWords, $overUsed);
        }
      }
    }
    $this->value = $keywords;
    if (!empty($overusedWords)) {
      $this->impact = 4;
      $this->value['overused'] = $overusedWords;
      return $this->t('There are some overused keywords in headers. You should consider limiting the use of those keywords');
    }
    return $this->t('The keywords density in headers looks good');
  }
}
