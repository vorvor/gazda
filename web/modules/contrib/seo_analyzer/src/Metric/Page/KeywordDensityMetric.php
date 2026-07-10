<?php

namespace Drupal\seo_analyzer\Metric\Page;

class KeywordDensityMetric extends AbstractKeywordDensityMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Keyword density in page content');
    if (is_null($this->value['text'])) {
      return $this->t('Please enter a keyword first');
    }
    $keywords = $this->analyseKeywords($this->value['text'], $this->value['stop_words']);
    //unset($this->value);
    $this->value['keywords'] = $keywords;
    $overusedWords = $this->getOverusedKeywords($keywords);
    if (!empty($this->keyword)) {
      return $this->analyzeWithKeyword($keywords, $overusedWords);
    }
    if (!empty($overusedWords)) {
      $this->value['overused'] = $overusedWords;
      $this->impact = 3;
      return $this->t('There are some overused keywords on site. You should consider limiting the use of overused keywords');
    }
    return $this->t('The keywords density on the site looks good');
  }

  protected function analyzeWithKeyword(array $keywords, array $overusedWords): string {
    $this->name = 'KeywordDensityKeyword';
    //unset($this->value);
    $this->value['keywords'] = $keywords;
    $this->value['keyword'] = $this->keyword;
    foreach ($keywords as $group) {
      foreach ($group as $phrase => $count) {
        if (stripos($phrase, $this->keyword) !== FALSE) {
          if (in_array($this->keyword, $overusedWords)) {
            $this->impact = 4;
            return $this->t('The keyword is overused on the site. Try to reduce its occurrence');
          }
          return $this->t('Good! The keyword is among the most popular keywords on the site');
        }
      }
    }
    $this->impact = 4;
    return $this->t('You should consider adding your keyword to the site content');
  }
}
