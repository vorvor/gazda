<?php

declare(strict_types=1);

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\product_search\Controller\ProductSearchController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fails the integration check when a condition is not met.
 */
function product_search_endpoint_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$database = \Drupal::database();
$transaction = $database->startTransaction();
try {
  $database->delete('product_search_query_log')->execute();
  $route = \Drupal::service('router.route_provider')
    ->getRouteByName('product_search.ajax');
  product_search_endpoint_assert($route->getMethods() === ['POST'], 'The state-changing AJAX endpoint must accept POST only.');
  product_search_endpoint_assert($route->getRequirement('_csrf_request_header_token') === 'TRUE', 'The state-changing AJAX endpoint must require a CSRF request-header token.');
  $token_route = \Drupal::service('router.route_provider')
    ->getRouteByName('product_search.csrf_token');
  product_search_endpoint_assert($token_route->getMethods() === ['GET'], 'The CSRF token endpoint must be read-only.');

  $controller = \Drupal::service('class_resolver')
    ->getInstanceFromDefinition(ProductSearchController::class);
  $csrf_token = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $authorized_request = static fn (string $query): Request => new Request(
    [],
    ['q' => $query],
    [],
    [],
    [],
    [
      'HTTP_X_CSRF_TOKEN' => $csrf_token,
      'REMOTE_ADDR' => '192.0.2.70',
    ],
  );

  $missing_token_response = $controller->ajaxSearch(new Request([], ['q' => 'should-not-be-logged']));
  product_search_endpoint_assert($missing_token_response->getStatusCode() === 403, 'A POST without a valid CSRF token must be rejected.');

  $controller->ajaxSearch($authorized_request('zzzz-nincs-találat'));
  $controller->ajaxSearch($authorized_request('abc'));
  $controller->ajaxSearch($authorized_request(str_repeat('ő', 300)));

  $records = $database->select('product_search_query_log', 'log')
    ->fields('log')
    ->execute()
    ->fetchAll();

  product_search_endpoint_assert(count($records) === 2, 'The AJAX endpoint should log only queries longer than three characters.');
  product_search_endpoint_assert($records[0]->search_term === 'zzzz-nincs-találat', 'The endpoint should store the submitted query.');
  product_search_endpoint_assert((int) $records[0]->result_count === 0, 'The endpoint should store a zero-result search.');
  product_search_endpoint_assert(mb_strlen($records[1]->search_term) === 255, 'The endpoint must bound search terms before querying and storing them.');

  print "PASS: the AJAX endpoint records qualifying search terms.\n";
}
finally {
  $transaction->rollBack();
}
