<?php

namespace Drupal\seo_audit;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use GuzzleHttp\Client;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Crawl observer for collecting SEO audit information from crawled pages.
 */
class SeoAuditCrawlObserver extends CrawlObserver {

  use StringTranslationTrait;

  /**
   * The HTTP client for making requests.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * The breadcrumb analyzer.
   *
   * @var \Drupal\seo_audit\BreadcrumbAnalyzer
   */
  protected BreadcrumbAnalyzer $breadcrumbAnalyzer;

  /**
   * The base URL being crawled.
   *
   * @var string
   */
  protected string $baseUrl;

  /**
   * The options selected for analysis.
   *
   * @var array
   */
  protected array $checkedOptions;

  /**
   * The results array reference to populate.
   *
   * @var array
   */
  protected $results;

  /**
   * Constructs a new SeoAuditCrawlObserver object.
   *
   * @param string $baseUrl
   *   The base URL to crawl.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client used to make requests.
   * @param \Drupal\seo_audit\BreadcrumbAnalyzer $breadcrumbAnalyzer
   *   The breadcrumb analyzer service.
   * @param array $checkedOptions
   *   An array of selected checks to perform.
   * @param array &$results
   *   A reference to the results array.
   */
  public function __construct(string $baseUrl, Client $client, BreadcrumbAnalyzer $breadcrumbAnalyzer, array $checkedOptions, &$results) {
    $this->client = $client;
    $this->breadcrumbAnalyzer = $breadcrumbAnalyzer;
    $this->baseUrl = rtrim($baseUrl, '/');
    $this->checkedOptions = $checkedOptions;
    $this->results = &$results;
  }

  /**
   * Handles a successfully crawled URL.
   *
   * @param \Psr\Http\Message\UriInterface $url
   *   The URL that was crawled.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response received from the URL.
   * @param \Psr\Http\Message\UriInterface|null $foundOnUrl
   *   The URL where this link was found (optional).
   * @param string|null $linkText
   *   The anchor text for the link (optional).
   */
  public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = NULL, ?string $linkText = NULL): void {
    $status_code = $response->getStatusCode();
    $urlString = (string) $url;
    $crawler = new Crawler((string) $response->getBody(), $urlString);

    $this->results[$urlString]['status_code'] = $status_code;

    if (!empty($this->checkedOptions['check_h1'])) {
      $this->results[$urlString]['h1'] = $this->checkH1Tag($crawler);
    }

    if (!empty($this->checkedOptions['check_title'])) {
      $this->results[$urlString]['title'] = $this->checkTitleTag($crawler);
    }

    if (!empty($this->checkedOptions['check_meta_description'])) {
      $this->results[$urlString]['meta-description'] = $this->checkMetaDescription($crawler);
    }

    if (!empty($this->checkedOptions['check_meta_robots'])) {
      $this->results[$urlString]['meta-robots'] = $this->checkMetaRobots($crawler);
    }

    if (!empty($this->checkedOptions['check_img_alt'])) {
      $this->results[$urlString]['image-alt-text'] = $this->checkAltAttributes($crawler);
    }

    if (!empty($this->checkedOptions['check_broken_links'])) {
      $this->results[$urlString]['broken-links'] = $this->checkBrokenLinks($crawler, $urlString);
    }

    if (!empty($this->checkedOptions['visual_breadcrumb']) || !empty($this->checkedOptions['jsonld_breadcrumb'])) {
      $breadcrumb = $this->breadcrumbAnalyzer->analyze($crawler);

      if (!empty($this->checkedOptions['visual_breadcrumb'])) {
        $this->results[$urlString]['visual-breadcrumb'] = $breadcrumb['has_visual'];
      }

      if (!empty($this->checkedOptions['jsonld_breadcrumb'])) {
        $this->results[$urlString]['jsonld-breadcrumb'] = $breadcrumb['has_jsonld'];
      }
    }
  }

  /**
   * Handles a failed crawl attempt and retries up to 3 times.
   *
   * @param \Psr\Http\Message\UriInterface $url
   *   The URL that failed.
   * @param \GuzzleHttp\Exception\RequestException $requestException
   *   The exception thrown.
   * @param \Psr\Http\Message\UriInterface|null $foundOnUrl
   *   The URL where this link was found (optional).
   * @param string|null $linkText
   *   The anchor text for the link (optional).
   */
  public function crawlFailed(
    UriInterface $url,
    RequestException $requestException,
    ?UriInterface $foundOnUrl = NULL,
    ?string $linkText = NULL,
  ): void {
    $maxRetries = 3;
    $attempt = 0;

    while ($attempt < $maxRetries) {
      try {
        $attempt++;
        $this->client->request('GET', (string) $url, ['timeout' => 60]);
        return;
      }
      catch (RequestException $e) {
        if ($attempt >= $maxRetries) {
          \Drupal::logger('seo_audit')->info(
          $this->t('Failed crawling @url: @message', [
            '@url' => $url,
            '@message' => $e->getMessage(),
          ])
          );
        }
      }
    }
  }

  /**
   * Checks for the presence of an <h1> tag on the page.
   *
   * The <h1> tag typically represents the main heading of a page and is
   * important for both accessibility and SEO. This method verifies whether
   * at least one <h1> tag exists in the HTML content.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   *
   * @return string
   *   "Found" if an <h1> tag is present, or "Missing" otherwise.
   */
  private function checkH1Tag(Crawler $crawler): string {
    return $crawler->filter('h1')->count() > 0 ? $this->t('Found') : $this->t('Missing');
  }

  /**
   * Checks for the presence of the <title> tag in the page.
   *
   * The <title> tag defines the title shown in browser tabs and is
   * essential for SEO. This method verifies whether the tag exists
   * in the page's HTML structure.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   *
   * @return string
   *   "Found" if the <title> tag exists, or "Missing" if it's absent.
   */
  private function checkTitleTag(Crawler $crawler): string {
    $title = $crawler->filter('title');
    return $title->count() > 0 ? $this->t('Found') : $this->t('Missing');
  }

  /**
   * Checks for the presence of a meta description tag in the page.
   *
   * Looks for a `<meta name="description">` tag in the page's HTML.
   * This tag is commonly used for SEO to provide a summary of the page content.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   *
   * @return string
   *   "Found" if the meta description tag is present, or "Missing" otherwise.
   */
  private function checkMetaDescription(Crawler $crawler): string {
    $meta = $crawler->filter('meta[name="description"]');
    return $meta->count() > 0 ? $this->t('Found') : $this->t('Missing');

  }

  /**
   * Checks for the presence of a meta robots tag in the page.
   *
   * Searches the page's `<head>` section for a `<meta name="robots">` tag.
   * If found, returns its `content` value (e.g., "index, follow").
   * If not found, reports the tag as missing.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   *
   * @return string
   *   The value of the robots meta tag if present,
   *   or "Missing" if the tag is not found.
   */
  private function checkMetaRobots(Crawler $crawler): string {
    $meta = $crawler->filter('meta[name="robots"]');
    if ($meta->count() > 0) {
      $content = $meta->attr('content');
      return $this->t('Found: @content', ['@content' => $content]);
    }
    else {
      return $this->t("Missing");
    }
  }

  /**
   * Checks for images missing `alt` attributes on the page.
   *
   * Iterates through all `<img>` tags in the given page content,
   * and counts those that are either missing the `alt` attribute
   * entirely or have an empty `alt` value.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   *
   * @return string
   *   A message indicating how many images are missing `alt` attributes,
   *   or a success message if all images have valid `alt` text.
   */
  private function checkAltAttributes(Crawler $crawler): string {
    $images = $crawler->filter('img');
    $missingAltCount = 0;

    foreach ($images as $img) {
      if (!$img->hasAttribute('alt') || trim($img->getAttribute('alt')) === '') {
        $missingAltCount++;
      }
    }

    return $missingAltCount > 0
    ? $this->t('@count image(s) missing alt attributes.', ['@count' => $missingAltCount])
    : $this->t('Alt attribute found on all images.');

  }

  /**
   * Checks for broken links on the given page.
   *
   * Crawls all anchor (`<a>`) tags with valid `href` attributes, resolves
   * their absolute URLs based on the page's base URL, and performs a GET
   * request to detect broken links (HTTP status code >= 400).
   *
   * Skips mailto:, javascript:, and anchor-only links.
   * Uses a timeout and error handling to prevent crawl failures.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler for the page being audited.
   * @param string $baseUrl
   *   The base URL of the page, used to resolve relative links.
   *
   * @return string
   *   A summary message indicating the number of broken links found, or
   *   a success message if none are broken.
   */
  private function checkBrokenLinks(Crawler $crawler, string $baseUrl): string {
    $links = $crawler->filter('a[href]');
    $brokenLinks = [];

    foreach ($links as $link) {
      $href = $link->getAttribute('href');
      if (preg_match('/^(mailto:|javascript:|#)/', $href)) {
        continue;
      }

      $absoluteUrl = (string) UriResolver::resolve(
      new Uri($baseUrl),
      new Uri($href)
      );

      try {
        $response = $this->client->request('GET', $absoluteUrl, [
          'http_errors' => FALSE,
          'timeout' => 5,
          'allow_redirects' => TRUE,
        ]);
        if ($response->getStatusCode() >= 400) {
          $brokenLinks[] = $absoluteUrl;
        }
      }
      catch (TransferException $e) {
        $brokenLinks[] = $absoluteUrl;
      }
    }

    $count = count($brokenLinks);
    return $count === 0
    ? $this->t('No broken links found.')
    : $this->t('@count broken link(s)', ['@count' => $count]);
  }

}
