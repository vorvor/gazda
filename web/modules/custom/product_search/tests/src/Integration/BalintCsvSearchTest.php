<?php

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\product_search\Controller\ProductSearchController;
use Symfony\Component\HttpFoundation\Request;

function balint_csv_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$transaction = \Drupal::database()->startTransaction();
try {
  $controller = ProductSearchController::create(\Drupal::getContainer());
  $token = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $alias = \Drupal::service('path_alias.manager');
  $balint = (int) basename($alias->getPathByAlias('/bolt/balint-es-tarsa-kft-villamossagi-szakuzlet'));
  $gazda = (int) basename($alias->getPathByAlias('/bolt/gazdabolt'));
  $search = static function (?int $shop) use ($controller, $token): array {
    $params = ['q' => 'ANCO'];
    if ($shop !== NULL) {
      $params['shop_id'] = (string) $shop;
    }
    return json_decode($controller->ajaxSearch(new Request([], $params, [], [], [], ['HTTP_X_CSRF_TOKEN' => $token]))->getContent(), TRUE);
  };
  foreach ([NULL, $balint] as $scope) {
    $data = $search($scope);
    balint_csv_assert(str_contains($data['html'], 'ANCO csíptetős asztali lámpa'), 'Global and Balint searches must include Balint CSV stock.');
    balint_csv_assert(str_contains($data['html'], 'BÁLINT ÉS TÁRSA'), 'Balint cards must display their own shop label.');
    balint_csv_assert(str_contains($data['html'], 'Asztali lámpa</h3>'), 'Balint cards must use distilled short titles.');
    balint_csv_assert(str_contains($data['html'], 'termék fotó nem elérhető jelenleg'), 'Balint cards must keep the photo notice.');
  }
  balint_csv_assert(!str_contains($search($gazda)['html'], 'BÁLINT ÉS TÁRSA'), 'Gazdabolt must not show Balint stock.');
  $page = $controller->page(Request::create('/search-product?keyword=ANCO'));
  $html = (string) \Drupal::service('renderer')->renderRoot($page);
  balint_csv_assert(str_contains($html, 'ANCO csíptetős asztali lámpa'), 'Initial search pages must include Balint stock.');
  print "PASS: Balint global, scoped and initial-page CSV searches, titles, labels, and shop isolation.\n";
}
finally {
  $transaction->rollBack();
}
