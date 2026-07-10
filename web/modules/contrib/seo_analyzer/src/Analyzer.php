<?php

namespace Drupal\seo_analyzer;

use InvalidArgumentException;
use ReflectionException;
use Drupal\seo_analyzer\Page;
use Drupal\seo_analyzer\HttpClient\Client;
use Drupal\seo_analyzer\HttpClient\ClientInterface;
use Drupal\seo_analyzer\HttpClient\Exception\HttpException;
use Drupal\seo_analyzer\Metric\MetricFactory;
use Drupal\seo_analyzer\Metric\MetricInterface;

class Analyzer {
  /**
   * @var Page Web page to analyze
   */
  public $page;

  /**
   * @var string Default langcode to use for translations
   */
  public $langcode = 'en';

  /**
   * @var array Metrics array
   */
  public $metrics = [];

  /**
   * @var ClientInterface
   */
  public $client;

  /**
   * @param Page|null $page Page to analyze
   * @param ClientInterface|null $client
   */
  public function __construct(Page $page = NULL, ClientInterface $client = NULL) {
    $this->client = $client;
    if (empty($client)) {
      $this->client = new Client();
    }

    if (!empty($page)) {
      $this->page = $page;
    }
  }

  /**
   * Analyzes page at specified url.
   *
   * @param string $url Url to analyze
   * @param string|null $keyword
   * @param string|null $langcode
   * @return array
   * @throws ReflectionException
   */
  public function analyzeUrl(string $url, string $keyword = NULL, string $langcode = NULL): array {
    if (!empty($langcode)) {
      $this->langcode = $langcode;
    }
    $this->page = new Page($url, $langcode, $this->client);
    if (!empty($keyword)) {
      $this->page->keyword = $keyword;
    }
    return $this->analyze();
  }

  /**
   * Analyzes html document from file.
   *
   * @param string $filename
   * @param string|null $langcode
   * @return array
   * @throws ReflectionException
   */
  public function analyzeFile(string $filename, string $langcode = NULL): array {
    $this->page = new Page(NULL, $langcode, $this->client);
    $this->page->content = file_get_contents($filename);
    return $this->analyze();
  }

  /**
   * Analyzes html document from string.
   *
   * @param string $htmlString
   * @param string|null $langcode
   * @return array
   * @throws ReflectionException
   */
  public function analyzeHtml(string $htmlString, string $langcode = NULL): array {
    $this->page = new Page(NULL, $langcode, $this->client);
    $this->page->content = $htmlString;
    return $this->analyze();
  }

  /**
   * Starts analysis of a Page.
   *
   * @return array
   * @throws ReflectionException
   */
  public function analyze() {

    if (empty($this->page)) {
      throw new InvalidArgumentException('No Page to analyze');
    }
    if (empty($this->metrics)) {
      $this->metrics = $this->getMetrics();
    }
    $results = [];
    foreach ($this->metrics as $metric) {
      if ($analysisResult = $metric->analyze()) {
        $results[$metric->name] = $this->formatResults($metric, $analysisResult);
      }
    }
    return $results;
  }

  /**
   * Returns available metrics list for a Page
   *
   * @return array
   * @throws ReflectionException
   */
  public function getMetrics(): array {
    return array_merge($this->page->getMetrics(), $this->getFilesMetrics());
  }

  /**
   * Returns file related metrics.
   *
   * @return array
   * @throws ReflectionException
   */
  public function getFilesMetrics(): array {
    return [
      'robots' => MetricFactory::get('file.robots', $this->getFileContent(
        $this->page->getFactor('url.parsed.scheme') . '://' . $this->page->getFactor('url.parsed.host'),
        'robots.txt'
      )),
      'sitemap' => MetricFactory::get('file.sitemap', $this->getFileContent(
        $this->page->getFactor('url.parsed.scheme') . '://' . $this->page->getFactor('url.parsed.host'),
        'sitemap.xml'
      ))
    ];
  }

  /**
   * Downloads file from Page's host.
   *
   * @param $url
   * @param $filename
   * @return bool|string
   */
  protected function getFileContent($url, $filename) {
    try {
      $response = $this->client->get($url . '/' . $filename);
      $content = $response->getBody()->getContents();
    }
    catch (HttpException $e) {
      return FALSE;
    }
    return $content;
  }

  /**
   * Formats metric analysis results.
   *
   * @param MetricInterface $metric
   * @param $results
   * @return array
   */
  protected function formatResults(MetricInterface $metric, string $results): array {

    return [
      'analysis' => $results,
      'name' => $metric->name,
      'description' => $metric->description,
      'value' => $metric->value,
      'negative_impact' => $metric->impact,
    ];
  }
}
