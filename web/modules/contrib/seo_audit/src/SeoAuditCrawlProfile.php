<?php

namespace Drupal\seo_audit;

use Psr\Http\Message\UriInterface;
use spatie\Crawler\CrawlProfiles\CrawlProfile;
use Drupal\Core\Cache\Cache;

/**
 * Custom crawl profile for SEO audit module.
 *
 * This profile limits crawling based on the base URL and robots.txt rules.
 */
class SeoAuditCrawlProfile extends CrawlProfile {

  /**
   * The base URL used for crawling.
   *
   * @var string
   */
  protected string $baseUrl;

  /**
   * The robots.txt parser instance.
   *
   * @var \RobotsTxtParser
   */
  protected \RobotsTxtParser $robotsParser;

  /**
   * Constructs the crawl profile.
   *
   * @param string $baseUrl
   *   The base URL to crawl.
   */
  public function __construct(string $baseUrl) {
    $this->baseUrl = rtrim($baseUrl, '/');
    $this->robotsParser = $this->loadRobotsTxtParser();
  }

  /**
   * Load and parse robots.txt from cache or fetch if not cached.
   */
  private function loadRobotsTxtParser(): \RobotsTxtParser {
    $cid = 'seo_audit:robots:' . sha1($this->baseUrl);
    $cache = \Drupal::cache()->get($cid);

    if ($cache && isset($cache->data) && is_string($cache->data)) {
      return new \RobotsTxtParser($cache->data);
    }

    $robotsUrl = $this->baseUrl . '/robots.txt';
    $stream = @fopen($robotsUrl, 'r');
    $robotsContent = is_resource($stream) ? stream_get_contents($stream) : '';

    // Save to cache for 6 hours (21600 seconds)
    \Drupal::cache()->set($cid, $robotsContent, time() + 21600, [Cache::PERMANENT]);

    return new \RobotsTxtParser($robotsContent);
  }

  /**
   * Determines whether the given URL should be crawled.
   *
   * This method checks if the URL:
   * - Starts with the configured base domain.
   * - Does not point to a file with an excluded extensions.
   * - Respects robots.txt rules for the user-agent '*'.
   *
   * @param \Psr\Http\Message\UriInterface $url
   *   The URL to evaluate.
   *
   * @return bool
   *   TRUE if the URL should be crawled, FALSE otherwise.
   */
  public function shouldCrawl(UriInterface $url): bool {
    $urlStr = (string) $url;

    // Only crawl URLs under the base domain.
    if (!str_starts_with($urlStr, $this->baseUrl)) {
      return FALSE;
    }

    $path = parse_url($urlStr, PHP_URL_PATH) ?: '/';

    // Skip URLs pointing to files with certain extensions.
    $excludedExtensions = ['zip', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'exe', 'mp4', 'mov', 'doc', 'docx', 'xls', 'xlsx'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, $excludedExtensions, TRUE)) {
      return FALSE;
    }

    // Respect robots.txt rules for user-agent '*'.
    return $this->robotsParser->isAllowed($path, '*');
  }

}
