<?php

namespace Drupal\product_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\product_search\SearchAnalytics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays aggregate product-search statistics.
 */
final class SearchStatisticsController extends ControllerBase {

  public function __construct(
    private readonly SearchAnalytics $searchAnalytics,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('product_search.search_analytics'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the search-statistics report.
   */
  public function page(): array {
    $summary = $this->searchAnalytics->getSummary();
    $rows = [];

    foreach ($this->searchAnalytics->getStatistics() as $statistic) {
      $rows[] = [
        ['data' => ['#plain_text' => (string) $statistic->normalized_term]],
        ['data' => (int) $statistic->search_count],
        ['data' => (int) $statistic->no_result_count],
        ['data' => number_format((float) $statistic->average_result_count, 1, ',', ' ')],
        ['data' => $this->dateFormatter->format((int) $statistic->last_searched, 'short')],
      ];
    }

    return [
      '#attached' => [
        'library' => ['product_search/statistics'],
      ],
      'introduction' => [
        '#markup' => '<p class="product-search-statistics__intro">' . $this->t('Az összesítés a legalább négy karakteres kereséseket mutatja. A keresési napló nem tartalmaz IP-címet, felhasználói vagy munkamenet-azonosítót.') . '</p>',
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['product-search-statistics__summary']],
        'total' => $this->buildSummaryCard($this->t('Összes keresés'), $summary['total_searches']),
        'unique' => $this->buildSummaryCard($this->t('Különböző kifejezések'), $summary['unique_terms']),
        'empty' => $this->buildSummaryCard($this->t('Találat nélküli keresések'), $summary['no_result_searches']),
      ],
      'table' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['product-search-statistics__table']],
        '#header' => [
          $this->t('Keresett kifejezés'),
          $this->t('Keresések'),
          $this->t('Találat nélkül'),
          $this->t('Átlagos találatszám'),
          $this->t('Utolsó keresés'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Még nincs legalább négy karakteres rögzített keresés.'),
        '#sticky' => TRUE,
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Builds one summary metric card.
   */
  private function buildSummaryCard(string $label, int $value): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['product-search-statistics__metric']],
      'value' => [
        '#markup' => '<strong class="product-search-statistics__value">' . $value . '</strong>',
      ],
      'label' => [
        '#markup' => '<span class="product-search-statistics__label">' . $label . '</span>',
      ],
    ];
  }

}
