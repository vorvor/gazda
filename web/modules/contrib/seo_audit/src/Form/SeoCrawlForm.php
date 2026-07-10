<?php

namespace Drupal\seo_audit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a form to initiate SEO crawling.
 */
class SeoCrawlForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $timeService;

  /**
   * Constructs a SeoCrawlForm object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The time service.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    Connection $database,
    QueueFactory $queue_factory,
    MessengerInterface $messenger,
    TimeInterface $time_service,
  ) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->queueFactory = $queue_factory;
    $this->messenger = $messenger;
    $this->timeService = $time_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('database'),
      $container->get('queue'),
      $container->get('messenger'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'seo_crawler_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter Site URL to Crawl'),
      '#description' => $this->t('Adds the site to the crawler pipeline.'),
      '#required' => TRUE,
      '#placeholder' => 'e.g., https://example.com',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    $this->configFactory;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $url = $form_state->getValue('url');
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('url', $this->t('The URL entered is not a valid URL.'));
    }
    if (!preg_match('#^https?://#i', $url)) {
      $form_state->setErrorByName('url', $this->t('The URL must start with http:// or https://.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $checked_options = $this->getCrawlOptions();
    $checked_options_json = json_encode($checked_options);
    $url = $form_state->getValue('url');
    $user_id = $this->currentUser->id();

    $record_id = $this->database->insert('seo_audit_crawl_result')
      ->fields([
        'user_id' => $user_id,
        'url' => $url,
        'results' => '',
        'created' => $this->timeService->getCurrentTime(),
        'status' => 'pending',
        'checked_options' => '',
      ])
      ->execute();

    $queue = $this->queueFactory->get('seo_audit_crawl_queue');
    $queue->createItem([
      'record_id' => $record_id,
      'url' => $url,
      'user_id' => $user_id,
      'checked_options' => $checked_options_json,
    ]);

    $dashboard_link = Link::fromTextAndUrl(
    $this->t('Crawl results page'),
    Url::fromRoute('seo_audit.results_dashboard')
    )->toString();

    $this->messenger->addMessage(
    $this->t("Crawl request has been scheduled. Logs will appear on the @dashboard_link. If notifications are enabled, you'll receive a crawl completion email.", [
      '@dashboard_link' => $dashboard_link,
    ])
    );

  }

  /**
   * Retrieves the current crawl options from the settings configuration.
   *
   * This method fetches the latest configuration values at the time
   * the crawl is requested, ensuring that any recent changes are
   * reflected immediately.
   *
   * @return array
   *   An associative array of the selected crawl option flags.
   */
  public function getCrawlOptions(): array {
    $config = $this->config('seo_audit.settings');
    $data = $config->getRawData();

    return !empty($data) ? [
      'check_h1' => $data['check_h1'] ?? FALSE,
      'check_title' => $data['check_title'] ?? FALSE,
      'check_meta_description' => $data['check_meta_description'] ?? FALSE,
      'check_meta_robots' => $data['check_meta_robots'] ?? FALSE,
      'check_img_alt' => $data['check_img_alt'] ?? FALSE,
      'check_broken_links' => $data['check_broken_links'] ?? FALSE,
      'visual_breadcrumb' => $data['visual_breadcrumb'] ?? FALSE,
      'jsonld_breadcrumb' => $data['jsonld_breadcrumb'] ?? FALSE,
    ] : [];
  }

}
