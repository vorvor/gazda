<?php

namespace Drupal\seo_analyzer;

use Drupal\seo_analyzer\HttpClient\Client;
use Drupal\seo_analyzer\HttpClient\ClientInterface;
use Drupal\seo_analyzer\HttpClient\Exception\HttpException;
use Drupal\seo_analyzer\Metric\KeywordBasedMetricInterface;
use Drupal\seo_analyzer\Metric\MetricFactory;
use ReflectionException;
use Drupal\seo_analyzer\Parser\Parser;
use Drupal\seo_analyzer\Parser\ParserInterface;

class Page {
  const LANGCODE = 'langcode';
  const STOP_WORDS = 'stop_words';
  const KEYWORD = 'keyword';
  const IMPACT = 'impact';
  const TEXT = 'text';
  const HEADERS = 'headers';

  /**
   * @var string URL of web page
   */
  public $url;

  /**
   * @var array Configuration
   */
  public $config = [];

  /**
   * @var string Page langcode
   */
  public $langcode = 'en';

  /**
   * @var string Keyword to use in analyse
   */
  public $keyword;

  /**
   * @var array Stop word used in keyword density analyse
   */
  public $stopWords = [];

  /**
   * @var string Web page content (html)
   */
  public $content;

  /**
   * @var array Web page factors values
   */
  public $factors = [];

  /**
   * @var ClientInterface
   */
  public $client;

  /**
   * @var ParserInterface
   */
  public $parser;

  /**
   * Page constructor.
   *
   * @param string|null $url
   * @param string $langcode
   * @param ClientInterface|null $client
   * @param ParserInterface|null $parser
   */
  public function __construct(
    string $url = NULL,
    $langcode = 'en',
    ClientInterface $client = NULL,
    ParserInterface $parser = NULL
  ) {
    $this->client = $client ?? new Client();
    $this->parser = $parser ?? new Parser();

    if (!empty($url)) {
      $this->url = $this->setUpUrl($url);
      $this->getContent();
    }

    $this->config = [
      'langcode' => 'en',
      'client' => new Client(),
      'parser' => new Parser(),
      'factors' => [
        Factor::SSL,
        Factor::REDIRECT,
        Factor::CONTENT_SIZE,
        Factor::META,
        Factor::HEADERS,
        Factor::CONTENT_RATIO,
        [Factor::DENSITY_PAGE => 'keywordDensity'],
        [Factor::DENSITY_HEADERS => 'headersKeywordDensity'],
        Factor::ALTS,
        Factor::URL_LENGTH,
        Factor::LOAD_TIME,
        [Factor::KEYWORD_URL => 'keywordUrl'],
        [Factor::KEYWORD_PATH => 'keywordPath'],
        [Factor::KEYWORD_TITLE => 'keywordTitle'],
        [Factor::KEYWORD_DESCRIPTION => 'keywordDescription'],
        Factor::KEYWORD_HEADERS,
        [Factor::KEYWORD_DENSITY => 'keywordDensity']
      ]
    ];
    $this->config['langcode'] = $langcode;
    $this->langcode = $langcode;
  }

  /**
   * Verifies URL and sets up some basic metrics.
   *
   * @param string $url
   * @return string URL
   */
  protected function setUpUrl(string $url): string {
    $parsedUrl = parse_url($url);
    if (empty($parsedUrl['scheme'])) {
      $url = 'http://' . $url;
      $parsedUrl = parse_url($url);
    }
    $this->setFactor(Factor::URL_PARSED, $parsedUrl);

    if (strcmp($parsedUrl['scheme'], 'https') === 0) {
      $this->setFactor(Factor::SSL, TRUE);
    }
    $this->setFactor(
      Factor::URL_LENGTH,
      strlen($this->getFactor(Factor::URL_PARSED_HOST) . $this->getFactor(Factor::URL_PARSED_PATH))
    );
    return $url;
  }

  /**
   * Downloads page content from URL specified and sets up some base metrics.
   */
  public function getContent() {
    $pageLoadFactors = $this->getPageLoadFactors();
    $this->setFactor(Factor::LOAD_TIME, $pageLoadFactors['time']);
    $this->content = $pageLoadFactors['content'];
    $this->setFactor(Factor::REDIRECT, $pageLoadFactors['redirect']);
    if (empty($this->getFactor(Factor::SSL)) && $this->getSSLResponseCode() == 200) {
      $this->setFactor(Factor::SSL, TRUE);
    }
  }

  /**
   * Sets page load related factors.
   *
   * @return array
   */
  protected function getPageLoadFactors(): array {

    $starTime = microtime(TRUE);
    $response = $this->client->get($this->url);
    $loadTime = number_format((microtime(TRUE) - $starTime), 4);
    $redirect = [
      'redirect' => FALSE,
      'source' => $this->url,
      'destination' => FALSE,
    ];
    if (!empty($redirects = $response->getHeader('X-Guzzle-Redirect-History'))) {
      $redirect = [
        'redirect' => TRUE,
        'source' => $this->url,
        'destination' => end($redirects),
      ];
    }
    return [
      'content' => $response->getBody()->getContents(),
      'time' => $loadTime,
      'redirect' => $redirect
    ];
  }

  /**
   * Returns https response code.
   *
   * @return int|false Http code or false on failure.
   */
  protected function getSSLResponseCode() {
    try {
      return $this->client->get(str_replace('http://', 'https://', $this->url))->getStatusCode();
    }
    catch (HttpException $e) {
      return FALSE;
    }
  }

  /**
   * Parses page's html content setting up related metrics.
   */
  public function parse() {
    if (empty($this->content)) {
      $this->getContent();
    }

    $this->parser->setContent($this->content);
    $this->setFactors([
      Factor::META_META => $this->parser->getMeta(),
      Factor::HEADERS => $this->parser->getHeaders($this->keyword),
      Factor::META_TITLE => $this->parser->getTitle(),
      Factor::TEXT => $this->parser->getText(),
      Factor::ALTS => $this->parser->getImages()
    ]);
  }

  /**
   * Returns page metrics.
   *
   * @return array
   * @throws ReflectionException
   */
  public function getMetrics(): array {
    $this->initializeFactors();
    $metrics = $this->setUpMetrics($this->config['factors']);
    return $metrics;
  }

  /**
   * Sets up and returns page metrics based on configuration specified.
   *
   * @param array $config
   * @return array
   * @throws ReflectionException
   */
  public function setMetrics(array $config) {
    $this->initializeFactors();
    return $this->setUpMetrics($config);
  }

  private function initializeFactors() {
    if (empty($this->dom)) {
      $this->parse();
    }
    $this->setUpContentFactors();
    if (!empty($this->keyword)) {
      $this->setUpContentKeywordFactors($this->keyword);
    }
  }

  /**
   * Sets up page content related factors for page metrics.
   */
  public function setUpContentFactors() {
    $this->setFactors([
      Factor::CONTENT_HTML => $this->content,
      Factor::CONTENT_SIZE => strlen($this->content),
      Factor::CONTENT_RATIO => [
        'content_size' => strlen(preg_replace('!\s+!', ' ', $this->getFactor(Factor::TEXT))),
        'code_size' => strlen($this->content)
      ],
      Factor::DENSITY_PAGE => [
        self::TEXT => $this->getFactor(Factor::TEXT),
        self::LANGCODE => $this->langcode,
        self::STOP_WORDS => $this->stopWords
      ],
      Factor::DENSITY_HEADERS => [
        self::HEADERS => $this->getFactor(Factor::HEADERS),
        self::LANGCODE => $this->langcode,
        self::STOP_WORDS => $this->stopWords
      ]
    ]);
  }

  /**
   * Sets up page content factors keyword related.
   *
   * @param string $keyword
   */
  public function setUpContentKeywordFactors(string $keyword) {

    $meta_description = $this->getFactor(Factor::META)['meta']['description'] ?? $this->getFactor(Factor::META_DESCRIPTION);
    $meta_title = $this->getFactor(Factor::META)['title'] ?? $this->getFactor(Factor::META_TITLE);
    if ($keyword && !empty($keyword)) {
      $meta_description = str_ireplace($keyword, "<strong>$keyword</strong>", $meta_description);
      $meta_title = str_ireplace($keyword, "<strong>$keyword</strong>", $meta_title);
    }

    $this->setFactors([
      Factor::KEYWORD_URL => [
        self::TEXT => $this->getFactor(Factor::URL_PARSED_HOST),
        self::KEYWORD => $keyword,
        self::IMPACT => 5,
        'type' => 'URL'
      ],
      Factor::KEYWORD_PATH => [
        self::TEXT => $this->getFactor(Factor::URL_PARSED_PATH),
        self::KEYWORD => $keyword,
        self::IMPACT => 3,
        'type' => 'UrlPath'
      ],
      Factor::KEYWORD_TITLE => [
        self::TEXT => $meta_title,
        self::KEYWORD => $keyword,
        self::IMPACT => 5,
        'type' => 'Title'
      ],
      Factor::KEYWORD_DESCRIPTION => [
        self::TEXT => $meta_description,
        self::KEYWORD => $keyword,
        self::IMPACT => 3,
        'type' => 'Description'
      ],
      Factor::KEYWORD_HEADERS => [
        self::HEADERS => $this->getFactor(Factor::HEADERS),
        self::KEYWORD => $keyword
      ],
      Factor::KEYWORD_DENSITY => [
        self::TEXT => $this->getFactor(Factor::TEXT),
        self::LANGCODE => $this->langcode,
        self::STOP_WORDS => $this->stopWords,
        self::KEYWORD => $keyword
      ]
    ]);
  }

  /**
   * Sets up page metrics.
   *
   * @param array $config Metrics config
   * @return array
   * @throws ReflectionException
   */
  public function setUpMetrics(array $config = []) {
    $metrics = [];
    foreach ($config as $factor) {
      $metric = $factor;
      if (is_array($factor)) {
        $metric = current($factor);
        $factor = key($factor);
      }
      $metricObject = MetricFactory::get('page.' . $metric, $this->getFactor($factor));
      if (!$metricObject instanceof KeywordBasedMetricInterface || !empty($this->keyword)) {
        $metrics['page_' . str_replace('.', '_', $metric)] = $metricObject;
      }
    }
    return $metrics;
  }

  /**
   * Sets page factor value.
   *
   * @param string $name
   * @param mixed $value
   * @return array
   */
  public function setFactor(string $name, $value) {
    if (count(explode('.', $name)) > 1) {
      $this->setArrayByDot($this->factors, $name, $value);
    }
    else {
      $this->factors[$name] = $value;
    }
    return $this->factors;
  }

  /**
   * Sets array values using string with dot notation.
   *
   * @param array $array Array to be updated
   * @param string $path Dot notated string
   * @param mixed $val Value to be set in array
   * @return array
   */
  protected function setArrayByDot(array &$array, string $path, $val) {
    $loc = &$array;
    foreach (explode('.', $path) as $step) {
      $loc = &$loc[$step];
    }
    return $loc = $val;
  }

  /**
   * Sets multiple page factors values at once.
   *
   * @param array $factors
   */
  public function setFactors(array $factors) {
    foreach ($factors as $factorName => $factorValue) {
      $this->setFactor($factorName, $factorValue);
    }
  }

  /**
   * Returns factor data collected by it's key name.
   *
   * @param string $name
   * @return mixed
   */
  public function getFactor($name) {
    if (strpos($name, '.') !== FALSE) {
      return $this->getNestedFactor($name);
    }
    if (!empty($this->factors[$name])) {
      return $this->factors[$name];
    }
    return FALSE;
  }

  /**
   * Returns factor data collected by it's key name.
   *
   * @param string $name
   * @return mixed
   */
  protected function getNestedFactor($name) {
    $keys = explode('.', $name);
    $value = $this->factors;
    foreach ($keys as $innerKey) {
      if (!array_key_exists($innerKey, $value)) {
        return FALSE;
      }
      $value = $value[$innerKey];
    }
    return $value;
  }
}
