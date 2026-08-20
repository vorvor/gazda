<?php

namespace Drupal\public_holiday\Plugin\Block;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays details when the current day is a public holiday.
 *
 * @Block(
 *   id = "public_holiday_today",
 *   admin_label = @Translation("Public holiday today"),
 *   category = @Translation("Custom")
 * )
 */
class PublicHolidayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a PublicHolidayBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $request_time = $this->time->getRequestTime();
    $timezone_name = $this->siteTimezone();
    $date = $this->dateFormatter->format($request_time, 'custom', 'Y-m-d', $timezone_name);
    $holidays = $this->database->select('public_holiday', 'ph')
      ->fields('ph', ['name', 'weekday'])
      ->condition('date', $date)
      ->condition('type', 1)
      ->execute()
      ->fetchAll();

    $cache = [
      'max-age' => $this->secondsUntilTomorrow($request_time, $timezone_name),
      'tags' => ['public_holiday:data', 'config:system.date'],
    ];

    if (!$holidays) {
      return ['#cache' => $cache];
    }

    $weekday_names = [
      1 => $this->t('hétfő'),
      2 => $this->t('kedd'),
      3 => $this->t('szerda'),
      4 => $this->t('csütörtök'),
      5 => $this->t('péntek'),
      6 => $this->t('szombat'),
      7 => $this->t('vasárnap'),
    ];
    $items = [];

    foreach ($holidays as $holiday) {
      $items[] = $this->t('@name – @date (@weekday)', [
        '@name' => $holiday->name,
        '@date' => $this->dateFormatter->format($request_time, 'custom', 'Y. F j.', $timezone_name),
        '@weekday' => $weekday_names[(int) $holiday->weekday],
      ]);
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['public-holiday-today'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Ma munkaszüneti nap van'),
      ],
      'details' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'extra' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Az üzlet nyitvatartása eltérhet a megszokottól, vagy átmenetileg zárva tarthat. Mielőtt felkeresné, érdemes telefonon érdeklődni!'),
      ],
      '#cache' => $cache,
    ];
  }

  /**
   * Calculates a cache lifetime that ends at the next local midnight.
   */
  protected function secondsUntilTomorrow(int $request_time, string $timezone_name): int {
    $timezone = new \DateTimeZone($timezone_name);
    $now = (new \DateTimeImmutable('@' . $request_time))->setTimezone($timezone);

    return max(1, $now->modify('tomorrow')->getTimestamp() - $request_time);
  }

  /**
   * Returns the timezone used consistently for lookup, display, and caching.
   */
  protected function siteTimezone(): string {
    return $this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get();
  }

}
