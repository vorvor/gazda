<?php

namespace Drupal\seo_audit\Plugin\QueueWorker;

use Drupal\Core\Url;
use Drupal\Core\Queue\QueueWorkerBase;
use Spatie\Crawler\Crawler;
use GuzzleHttp\Client;
use Drupal\seo_audit\SeoAuditCrawlObserver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\seo_audit\SeoAuditCrawlProfile;
use Drupal\seo_audit\BreadcrumbAnalyzer;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Process the SEO audit crawl queue.
 */
#[QueueWorker(
  id: 'seo_audit_crawl_queue',
  title: new TranslatableMarkup('SEO Audit Crawl Queue'),
  cron: ['time' => 60]
)]
class CrawlerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The breadcrumb analyzer.
   *
   * @var \Drupal\seo_audit\BreadcrumbAnalyzer
   */
  protected $breadcrumbAnalyzer;

  /**
   * Constructs a new CrawlerQueueWorker.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\seo_audit\BreadcrumbAnalyzer $breadcrumb_analyzer
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, ConfigFactoryInterface $config_factory, BreadcrumbAnalyzer $breadcrumb_analyzer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->configFactory = $config_factory;
    $this->breadcrumbAnalyzer = $breadcrumb_analyzer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('seo_audit.breadcrumb_analyzer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $settings = $this->configFactory->get('seo_audit.settings');
    $concurrency = $settings->get('crawl_concurrency') ?? 1;
    $limit = $settings->get('crawl_limit') ?? 100;
    $depth = $settings->get('crawl_depth') ?? 1;
    $notify = $settings->get('crawl_notify_enabled') ?? 0;

    $record_id = $data['record_id'];
    $url = $data['url'];
    $user_id = $data['user_id'];
    $checked_options = json_decode($data['checked_options'], TRUE);
    $results = [];
    if (!isset($record_id, $url, $user_id)) {
      \Drupal::logger('seo_audit')->warning('Queue item is missing expected data: @data', ['@data' => print_r($data, TRUE)]);
      return;
    }

    $crawler = Crawler::create()
      ->setCrawlProfile(new SeoAuditCrawlProfile($url))
      ->setCrawlObserver(new SeoAuditCrawlObserver($url, $this->client, $this->breadcrumbAnalyzer, $checked_options, $results))
      ->setConcurrency($concurrency)
      ->setMaximumDepth($depth);
    if ($limit > 0) {
      $crawler->setCurrentCrawlLimit($limit);
    }
    $crawler->startCrawling($url);

    $this->saveCrawlResult($record_id, $data['checked_options'], $results);

    if ($notify) {
      $this->sendNotification($record_id);
    }

  }

  /**
   * Save crawled data to the database.
   */
  public function saveCrawlResult($record_id, $checked_options, $results) {
    $serialized_results = json_encode($results);
    \Drupal::database()->update('seo_audit_crawl_result')
      ->fields([
        'checked_options' => $checked_options,
        'results' => $serialized_results,
        'completed' => \Drupal::time()->getCurrentTime(),
        'status' => 'completed',
      ])
      ->condition('id', $record_id)
      ->execute();
  }

  /**
   * Sends a notification email on crawl completion.
   */
  protected function sendNotification($id) {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = \Drupal::service('request_stack');
    $request = $request_stack->getCurrentRequest();
    $base_url = $request->getSchemeAndHttpHost();

    $pdf_url = Url::fromRoute('seo_audit.download_pdf', ['id' => $id])
      ->setOption('base_url', $base_url)
      ->setAbsolute()
      ->toString();

    $build = [
      '#theme' => 'seo_audit_notification',
      '#report_url' => $pdf_url,
    ];
    $html_body = $renderer->renderRoot($build);

    $site_mail = $this->configFactory->get('system.site')->get('mail');
    $settings = $this->configFactory->get('seo_audit.settings');
    $emails = array_map('trim', explode(',', $settings->get('crawl_notify_emails')));

    $module = 'seo_audit';
    $key = 'crawl_notify';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params = [
      'subject' => $this->t('SEO Audit Crawl Completed via Native'),
      'message' => $html_body,
      'report_url' => $pdf_url,
      'headers' => [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Reply-To' => $site_mail,
      ],
    ];

    /** @var \Drupal\Core\Mail\MailManagerInterface $mailManager */
    $mailManager = \Drupal::service('plugin.manager.mail');

    foreach ($emails as $to) {
      if (!empty($to)) {
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
        if ($result['result'] !== TRUE) {
          \Drupal::logger('seo_audit')->error($this->t('Email failed to send to @to'), ['@to' => $to]);
        }
      }
    }
  }

}
