<?php

namespace Drupal\seo_audit;

use Symfony\Component\DomCrawler\Crawler;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Analyzes the presence and markup of breadcrumbs on a webpage.
 */
class BreadcrumbAnalyzer {

  use StringTranslationTrait;

  /**
   * Analyzes a webpage for breadcrumb presence and markup.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler instance for the page.
   *
   * @return array
   *   An associative array containing:
   *   - has_visual: Whether a visual breadcrumb is found.
   *   - has_jsonld: Whether a JSON-LD breadcrumb is found.
   *   - visual_markup: The HTML of visual breadcrumbs.
   *   - jsonld_markup: The raw JSON-LD breadcrumb scripts.
   */
  public function analyze(Crawler $crawler): array {
    return [
      'has_visual' => $this->hasVisualBreadcrumb($crawler),
      'has_jsonld' => $this->hasJsonLdBreadcrumb($crawler),
      'visual_markup' => $this->getVisualBreadcrumbMarkup($crawler),
      'jsonld_markup' => $this->getJsonLdBreadcrumbMarkup($crawler),
    ];
  }

  /**
   * Checks if a visual breadcrumb exists.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler instance.
   *
   * @return string
   *   'Found' if a visual breadcrumb is detected, otherwise 'Missing'.
   */
  private function hasVisualBreadcrumb(Crawler $crawler): string {
    return $crawler->filter('nav[aria-label="breadcrumb"], [class*="breadcrumb"], [id*="breadcrumb"]')->count() > 0 ? $this->t('Found') : $this->t('Missing');
  }

  /**
   * Checks if a JSON-LD breadcrumb exists.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler instance.
   *
   * @return string
   *   'Found' if a JSON-LD breadcrumb is detected, otherwise 'Missing'.
   */
  private function hasJsonLdBreadcrumb(Crawler $crawler): string {
    return count($this->getJsonLdBreadcrumbMarkup($crawler)) > 0 ? $this->t('Found') : $this->t('Missing');
  }

  /**
   * Gets the HTML markup of visual breadcrumbs.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler instance.
   *
   * @return array
   *   An array of HTML strings representing breadcrumb elements.
   */
  private function getVisualBreadcrumbMarkup(Crawler $crawler): array {
    $markup = [];
    $nodes = $crawler->filter('nav[aria-label="breadcrumb"], [class*="breadcrumb"], [id*="breadcrumb"]');

    foreach ($nodes as $node) {
      $dom = new \DOMDocument();
      @$dom->appendChild($dom->importNode($node, TRUE));
      $markup[] = trim($dom->saveHTML());
    }

    return $markup;
  }

  /**
   * Gets JSON-LD breadcrumb markup from script tags.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler instance.
   *
   * @return array
   *   An array of raw JSON-LD strings representing breadcrumb data.
   */
  private function getJsonLdBreadcrumbMarkup(Crawler $crawler): array {
    $markup = [];

    $crawler->filterXPath('//script[@type="application/ld+json"]')->each(function (Crawler $node) use (&$markup) {
      $jsonText = $node->text();
      $json = json_decode($jsonText, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return;
      }

      $objects = is_array($json) && array_keys($json) === range(0, count($json) - 1) ? $json : [$json];

      foreach ($objects as $obj) {
        if (($obj['@type'] ?? '') === 'BreadcrumbList') {
          $markup[] = $jsonText;
        }
      }
    });

    return $markup;
  }

}
