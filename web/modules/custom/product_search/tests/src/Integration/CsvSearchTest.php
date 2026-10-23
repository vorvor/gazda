<?php

declare(strict_types=1);

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\product_search\Controller\ProductSearchController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks CSV results through both public search entry points.
 */
function product_search_csv_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$transaction = \Drupal::database()->startTransaction();
try {
  $controller = ProductSearchController::create(\Drupal::getContainer());
  $token = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $request = new Request([], ['q' => 'Koaxiális'], [], [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
  $response = $controller->ajaxSearch($request);
  $data = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
  product_search_csv_assert(str_contains($data['html'], 'product-search-csv-placeholder'), 'CSV tiles must have a photo-placeholder area.');
  product_search_csv_assert(str_contains($data['html'], 'Koaxiális antenna csatlakozó'), 'AJAX must include matching CSV product names.');
  product_search_csv_assert(str_contains($data['html'], 'termék fotó nem elérhető jelenleg'), 'CSV results must include the requested photo notice.');
  $find = new ReflectionMethod($controller, 'findProductNodeIds');
  $node_count = count($find->invoke($controller, 'Koaxiális'));
  product_search_csv_assert($data['count'] > $node_count, 'The response count must include CSV matches.');
  $page = $controller->page(Request::create('/search-product?keyword=Koaxiális'));
  $html = (string) \Drupal::service('renderer')->renderRoot($page);
  product_search_csv_assert(str_contains($html, 'Koaxiális antenna csatlakozó'), 'The initial page must include CSV matches.');
  product_search_csv_assert(str_contains($html, 'termék fotó nem elérhető jelenleg'), 'The initial page must include the photo notice.');
  print "PASS: CSV names and photo notices appear in AJAX and initial page results; counts include CSV matches.\n";
}
finally {
  $transaction->rollBack();
}
