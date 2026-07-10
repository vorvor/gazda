<?php

namespace Drupal\seo_audit\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class CrawlResultsController.
 *
 * Provides a page controller for viewing SEO audit results.
 */
class CrawlResultsController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a CrawlResultsController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The database connection service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The user service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('current_user')
    );
  }

  /**
   * Displays the SEO crawl results dashboard.
   *
   * @return array
   *   A render array for the results dashboard page.
   */
  public function resultsDashboard() {
    $current_user_id = $this->currentUser->id();

    // Query to fetch crawl results for the current user.
    $query = $this->database->select('seo_audit_crawl_result', 'r')
      ->fields('r', ['id', 'url', 'created', 'completed', 'status'])
      ->condition('r.user_id', $current_user_id)
      ->orderBy('r.created', 'DESC');
    $results = $query->execute()->fetchAll();

    $rows = [];
    foreach ($results as $result) {
      // Create a link to view the crawl report.
      $view_link = Link::fromTextAndUrl($this->t('View'), Url::fromRoute('seo_audit.view_results', ['id' => $result->id]))->toString();

      // Format timestamps.
      $created = $this->dateFormatter->format($result->created, 'short');
      $completed = $result->completed
        ? $this->dateFormatter->format($result->completed, 'short')
        : $this->t('Pending');

      // Add a row for each result.
      $rows[] = [
        'data' => [
          'url' => $result->url,
          'created' => $created,
          'completed' => $completed,
          'status' => ucfirst($result->status),
          'view' => $view_link,
        ],
      ];
    }

    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('URL'),
        $this->t('Requested'),
        $this->t('Completed'),
        $this->t('Status'),
        $this->t('Actions'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No SEO crawl results found.'),
    ];
  }

  /**
   * View individual crawl results.
   *
   * @param int $id
   *   The crawl result ID.
   *
   * @return array
   *   A render array for the detailed crawl results page.
   */
  public function viewCrawlResults($id) {
    $result = $this->loadCrawlResult($id);

    if (!$result) {
      throw new NotFoundHttpException();
    }

    $crawl_data = json_decode($result->results, TRUE);

    if ($crawl_data === NULL) {
      throw new BadRequestHttpException('Crawl results are not valid.');
    }

    $created = $this->dateFormatter->format($result->created, 'short');
    $completed = $result->completed
      ? $this->dateFormatter->format($result->completed, 'short')
      : $this->t('Pending');

    // Build the detailed view page.
    $build = [
      '#theme' => 'seo_audit_crawl_results_view',
      '#id' => $id,
      '#title' => $this->t('Crawl Results for URL: @url', ['@url' => $result->url]),
      '#url' => $result->url,
      '#created' => $created,
      '#completed' => $completed,
      '#status' => ucfirst($result->status),
      '#crawl_data' => $crawl_data,
    ];

    return $build;
  }

  /**
   * Loads a crawl result record from the database for the current user.
   *
   * Ensures that the returned crawl result belongs to the current user.
   *
   * @param int|string $id
   *   The ID of the crawl result to load.
   *
   * @return object|false
   *   The crawl result record as an object if found, FALSE otherwise.
   */
  private function loadCrawlResult($id) {
    $current_user_id = $this->currentUser->id();

    return $this->database->select('seo_audit_crawl_result', 'r')
      ->fields('r')
      ->condition('r.id', $id)
      ->condition('r.user_id', $current_user_id)
      ->execute()
      ->fetchObject();
  }

  /**
   * Download crawl report in json format.
   */
  public function downloadJson($id) {
    $result = $this->loadCrawlResult($id);
    if (!$result) {
      throw new NotFoundHttpException();
    }

    $filename = $this->generateFilename($result->url, $id, 'json');

    $crawl_data = json_decode($result->results, TRUE);
    if ($crawl_data === FALSE) {
      throw new BadRequestHttpException('Failed to unserialize crawl results.');
    }

    $response = new Response(
      json_encode($crawl_data, JSON_PRETTY_PRINT),
      200,
      [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      ]
    );

    return $response;
  }

  /**
   * Download crawl report in csv format.
   */
  public function downloadCsv($id) {
    $result = $this->loadCrawlResult($id);
    if (!$result) {
      throw new NotFoundHttpException();
    }
    $filename = $this->generateFilename($result->url, $id, 'csv');
    $data = json_decode($result->results, TRUE);
    $temp_path = sys_get_temp_dir() . '/' . $filename;

    $fp = fopen($temp_path, 'w');
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $value = json_encode($value);
      }
      fputcsv($fp, [$key, $value]);
    }
    fclose($fp);

    $response = new BinaryFileResponse($temp_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    return $response;
  }

  /**
   * Download crawl report in pdf format.
   */
  public function downloadPdf($id) {
    $result = $this->loadCrawlResult($id);

    if (!$result) {
      throw new NotFoundHttpException();
    }

    $data = json_decode($result->results ?? '{}', TRUE);
    if (!is_array($data)) {
      return new Response('Invalid crawl results format.', 500);
    }

    $checked_options = json_decode($result->checked_options ?? '{}', TRUE);

    $created = $this->dateFormatter->format($result->created, 'short');
    $completed = $this->dateFormatter->format($result->completed, 'short');

    $html = '<table style="width: 100%; margin-bottom: 20px;">';
    $html .= '<tr>';
    $html .= '<td style="font-size: 30px; font-weight: bold; color: #236fb1;">' . $this->t('SEO Audit Report') . '</td>';
    $html .= '<td style="text-align: right; font-size: 14px;">' . $this->t('<strong>Requested:</strong> @created', ['@created' => $created]) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td>' . $this->t('<strong>URL:</strong> @url', ['@url' => htmlspecialchars($result->url)]) . '</td>';
    $html .= '<td style="text-align: right; font-size: 14px;">' . $this->t('<strong>Completed:</strong> @completed', ['@completed' => $completed]) . '</td>';
    $html .= '</tr>';
    $html .= '</table>';

    $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%" style="table-layout: fixed; border-collapse: collapse; font-family: sans-serif; font-size: 13px;">';
    $html .= '<thead style="background-color: #f3f4f9;"><tr>';
    $html .= '<th style="width: 30%; word-break: break-word;">' . $this->t('Page URL') . '</th>';
    $html .= '<th style="width: 5%;">' . $this->t('Status Code') . '</th>';

    $columns = [
      'check_h1' => $this->t('H1'),
      'check_title' => $this->t('Title'),
      'check_meta_description' => $this->t('Meta Description'),
      'check_meta_robots' => $this->t('Meta Robots'),
      'check_img_alt' => $this->t('Image Alt Text'),
      'check_broken_links' => $this->t('Broken Links'),
      'visual_breadcrumb' => $this->t('Visual Breadcrumb'),
      'jsonld_breadcrumb' => $this->t('JsonLD Breadcrumb'),
    ];

    foreach ($columns as $key => $label) {
      if (!empty($checked_options[$key])) {
        $html .= '<th>' . htmlspecialchars($label) . '</th>';
      }
    }

    $html .= '</tr></thead><tbody>';

    foreach ($data as $url => $checks) {
      $html .= '<tr>';
      $html .= '<td style="word-wrap: break-word; background-color: #f3f4f9;"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></td>';
      $html .= $this->renderStatusCodeCell($checks['status_code']);

      foreach (array_keys($columns) as $key) {
        if (!empty($checked_options[$key])) {
          $result_key = match ($key) {
            'check_h1' => 'h1',
            'check_title' => 'title',
            'check_meta_description' => 'meta-description',
            'check_meta_robots' => 'meta-robots',
            'check_img_alt' => 'image-alt-text',
            'check_broken_links' => 'broken-links',
            'visual_breadcrumb' => 'visual-breadcrumb',
            'jsonld_breadcrumb' => 'jsonld-breadcrumb',
          };
          $value = $checks[$result_key] ?? '-';
          $color = (strpos(strtolower($value), 'found') !== FALSE) ? '#cdf9cc' : '#f9cccc';
          $html .= '<td style="background-color:' . $color . '; text-align: center;">' . htmlspecialchars($value) . '</td>';
        }
      }

      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $filename = $this->generateFilename($result->url, $id, 'pdf');

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return new Response(
      $dompdf->output(),
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      ]
    );
  }

  /**
   * Renders a status code cell with colored text.
   *
   * @param int|string $status_code
   *   The HTTP status code to display.
   *
   * @return string
   *   An HTML <td> element with the status code styled by color.
   */
  public function renderStatusCodeCell($status_code) {
    $color = match (TRUE) {
      $status_code >= 200 && $status_code < 300 => '#28a745',
      $status_code >= 300 && $status_code < 400 => '#17a2b8',
      $status_code >= 400 && $status_code < 500 => '#fd7e14',
      $status_code >= 500 => '#dc3545',
      default => 'black',
    };

    return '<td style="text-align: center; font-weight: bold; color: ' . $color . ';">' . htmlspecialchars($status_code) . '</td>';
  }

  /**
   * Generates a sanitized filename based on URL, ID, and extension.
   *
   * @param string $url
   *   The URL to extract the host from.
   * @param int|string $id
   *   The unique identifier for the result.
   * @param string $extension
   *   The desired file extension (e.g., 'json', 'pdf').
   *
   * @return string
   *   The generated filename.
   */
  protected function generateFilename(string $url, $id, string $extension): string {
    $host = parse_url($url, PHP_URL_HOST);
    $sanitized_host = str_replace('.', '-', $host);
    return "seo-crawl-{$sanitized_host}-{$id}.{$extension}";
  }

}
