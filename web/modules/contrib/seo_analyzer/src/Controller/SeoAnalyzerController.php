<?php

namespace Drupal\seo_analyzer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Utility\Error;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\seo_analyzer\Analyzer;
use ReflectionException;
use Drupal\seo_analyzer\HttpClient\Exception\HttpException;

/**
 * Class SeoAnalyzerController.
 *
 * @package Drupal\seo_analyzer\Controller
 */
class SeoAnalyzerController extends ControllerBase {

  /**
   * Set some background classes based on the negative impact value.
   */
  protected $backgroundClasses = [];


  /**
   * Constructs a SeoAnalyzerController object.
   */
  public function __construct() {
    $this->backgroundClasses = [
      0 => 'bg-success',
      1 => 'bg-warning',
      2 => 'bg-warning',
      3 => 'bg-warning',
      4 => 'bg-warning',
      5 => 'bg-warning',
      6 => 'bg-danger',
      7 => 'bg-danger',
      8 => 'bg-danger',
      9 => 'bg-danger',
      10 => 'bg-danger',
    ];
  }

  /**
   * Generate the asset statistics page.
   * 
   * @param \Drupal\node\NodeInterface $node
   *   The Node entity.
   *
   * @return array
   *   The page build to be rendered.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws ReflectionException
   */
  public function generateAnalyzerPage(NodeInterface $node) {
    // Get the full node url string: https://mysite.com/nl/demo
    $options = ['absolute' => TRUE];
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options);
    $url = $url->toString();
    // Get current hostname.
    $host = \Drupal::request()->getHost();
    // Get the node url without scheme: mysite.com/nl/demo
    $page_url = $host . Url::fromRoute('entity.node.canonical', ['node' => $node->id()], [])->toString();
    // Get node langcode.
    $langcode = $node->get('langcode')->value;
    // Fallback keyword.
    $keyword = 'keyword';
    // Get keyword from query parameter.
    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
      $keyword = $_GET['keyword'];
    }

    try {
      $results = (new Analyzer())->analyzeUrl($url, $keyword, $langcode);
    }
    catch (HttpException $e) {
      // Log the exception to watchdog.
      Error::logException(\Drupal::logger('seo_analyzer'), $e, 'Error loading page');
      // Show an error page.
      return [
        '#theme' => 'page_seo_analyzer_error',
        '#message' => $e->getMessage(),
        '#attached' => ['library' => ['seo_analyzer/styling']],
      ];
    }
    catch (ReflectionException $e) {
      // Log the exception to watchdog.
      Error::logException(\Drupal::logger('seo_analyzer'), $e, 'Error loading metric file');
      // Show an error page.
      return [
        '#theme' => 'page_seo_analyzer_error',
        '#message' => $e->getMessage(),
        '#attached' => ['library' => ['seo_analyzer/styling']],
      ];
    }

    // Create and return a render array.
    return [
      '#theme' => 'page_seo_analyzer',
      '#search_form' => \Drupal::formBuilder()->getForm('Drupal\seo_analyzer\Form\KeywordForm'),
      '#keyword_analytics' => $this->createKeywordAnalyticsForm($results, $keyword),
      '#content_analytics' => $this->createContentAnalyticsForm($results, $url, $page_url),
      '#general_analytics' => $this->createGeneralAnalyticsForm($results, $url, $page_url),
      '#attached' => ['library' => ['seo_analyzer/styling']],
    ];
  }

  /**
   * Display the seo analytics for a given keyword.
   *
   * @param array $results
   *   Array returned by the analyzeUrl() function.
   * @param string $keyword
   *   The keyword that will be used to check SEO for the content against.
   *
   * @return array
   *   A render array that displays the seo analytics.
   */
  private function createKeywordAnalyticsForm($results, $keyword) {
    $keyword_analytics_form = [];
    $keyword_analytics_form['keyword_analytics'] = [
      '#type' => 'details',
      '#title' => $this->t("Keyword analytics for '@keyword'", ['@keyword' => $keyword]),
      '#open' => TRUE,
    ];
    $keyword_analytics_form['keyword_analytics']['content'] = [
      '#theme' => 'table_keyword_analytics',
      '#bgclasses' => $this->backgroundClasses,
      '#data' => [
        'meta_title' => $results['KeywordTitle'],
        'meta_description' => $results['KeywordDescription'],
        'heading_keywords' => $results['PageKeywordHeaders'],
        'heading_keyword_density' => $results['PageHeadersKeywordDensity'],
        'content_keyword_density' => $results['KeywordDensityKeyword'],
        'path_keyword' => $results['KeywordUrlPath'],
        'domain_keyword' => $results['KeywordURL'],
      ],
    ];

    return $keyword_analytics_form;
  }

  /**
   * Display the content analytics for a node's content.
   *
   * @param array $results
   *   Array returned by the analyzeUrl() function.
   * @param string $url
   *   Full node url string: https://mysite.com/nl/demo
   * @param string $page_url
   *   Node url string without scheme: mysite.com/nl/demo
   *
   * @return array
   *   A render array that displays the seo analytics.
   */
  private function createContentAnalyticsForm($results, $url, $page_url) {
    $content_analytics_form = [];
    $content_analytics_form['content_analytics'] = [
      '#type' => 'details',
      '#title' => $this->t("Content analytics for @url", ['@url' => $url]),
      '#open' => TRUE,
    ];
    $content_analytics_form['content_analytics']['content'] = [
      '#theme' => 'table_content_analytics',
      '#bgclasses' => $this->backgroundClasses,
      '#page_url' => $page_url,
      '#data' => [
        'meta_tag_length' => $results['PageMeta'],
        'heading_structure' => $results['PageHeaders'],
        'page_content_ratio' => $results['PageContentRatio'],
        'image_alt_texts' => $results['PageAlts'],
        'page_url_size' => $results['PageUrlLength'],
      ],
    ];

    return $content_analytics_form;
  }

  /**
   * Display the general seo analytics for a node.
   *
   * @param array $results
   *   Array returned by the analyzeUrl() function.
   * @param string $url
   *   Full node url string: https://mysite.com/nl/demo
   * @param string $page_url
   *   Node url string without scheme: mysite.com/nl/demo
   *
   * @return array
   *   A render array that displays the seo analytics.
   */
  private function createGeneralAnalyticsForm($results, $url, $page_url) {
    $general_analytics_form = [];
    $general_analytics_form['general_analytics'] = [
      '#type' => 'details',
      '#title' => $this->t("General analytics for @url", ['@url' => $url]),
      '#open' => TRUE,
    ];
    $general_analytics_form['general_analytics']['content'] = [
      '#theme' => 'table_general_analytics',
      '#bgclasses' => $this->backgroundClasses,
      '#page_url' => $page_url,
      '#data' => [
        'is_encrypted' => $results['PageSSL'],
        'has_redirect' => $results['PageRedirect'],
        'content_size' => $results['PageContentSize'],
        'page_load_time' => $results['PageLoadTime'],
        'robots_txt' => $results['FileRobots'],
        'sitemap' => $results['FileSitemap'],
      ],
    ];

    return $general_analytics_form;
  }
}
