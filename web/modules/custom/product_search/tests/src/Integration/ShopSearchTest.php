<?php

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\product_search\Controller\ProductSearchController;
use Symfony\Component\HttpFoundation\Request;

function shop_search_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$controller = ProductSearchController::create(\Drupal::getContainer());
$find = new ReflectionMethod($controller, 'findProductNodeIds');
$gazda_path = \Drupal::service('path_alias.manager')->getPathByAlias('/bolt/gazdabolt');
$gazda_id = (int) basename($gazda_path);
$shops = \Drupal::entityTypeManager()->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', 'shop')->condition('status', 1)->execute();
$other_ids = array_values(array_diff($shops, [$gazda_id]));
shop_search_assert($gazda_id > 0 && $other_ids !== [], 'The test requires Gazdabolt and another published shop.');
foreach ([$gazda_id, (int) $other_ids[0]] as $shop_id) {
  $shop = \Drupal::entityTypeManager()->getStorage('node')->load($shop_id);
  $route_match = new \Drupal\Core\Routing\RouteMatch('entity.node.canonical', new \Symfony\Component\Routing\Route('/node/{node}'), ['node' => $shop]);
  $definition = \Drupal::service('plugin.manager.block')->getDefinition('product_search_block');
  $block = new \Drupal\product_search\Plugin\Block\ProductSearchBlock([], 'product_search_block', $definition, $route_match);
  $build = $block->build();
  shop_search_assert($build['#shop_id'] === $shop_id, 'The shop block must supply its current shop ID.');
  shop_search_assert(array_values($build['#results']['view']['#arguments']) === [$shop_id], 'Default results must explicitly filter by current shop.');
  shop_search_assert(in_array('route', $block->getCacheContexts(), TRUE), 'Cached blocks must vary by shop route.');
  $html = (string) \Drupal::service('renderer')->renderRoot($build);
  shop_search_assert(str_contains($html, 'data-shop-id="' . $shop_id . '"'), 'The rendered widget must transmit its shop scope to JavaScript.');
  $nids = $find->invoke($controller, 'a', $shop_id);
  foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids) as $node) {
    $references = array_column($node->get('field_shop')->getValue(), 'target_id');
    shop_search_assert(in_array($shop_id, $references), 'Scoped search must exclude every other shop.');
  }
}
$transaction = \Drupal::database()->startTransaction();
try {
  $token = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $request = static fn ($shop, $q) => new Request([], ['q' => $q, 'shop_id' => (string) $shop], [], [], [], ['HTTP_X_CSRF_TOKEN' => $token]);
  $gazda = json_decode($controller->ajaxSearch($request($gazda_id, 'Koaxiális'))->getContent(), TRUE);
  $other = json_decode($controller->ajaxSearch($request($other_ids[0], 'Koaxiális'))->getContent(), TRUE);
  shop_search_assert(str_contains($gazda['html'], 'product-search-csv-result'), 'Gazdabolt must retain its CSV stock results.');
  shop_search_assert(!str_contains($other['html'], 'product-search-csv-result'), 'Other shops must not include Gazdabolt CSV stock.');
  shop_search_assert($controller->ajaxSearch($request('invalid', 'a'))->getStatusCode() === 400, 'Invalid scopes must not become global searches.');
  $empty = json_decode($controller->ajaxSearch($request($gazda_id, ''))->getContent(), TRUE);
  shop_search_assert(str_contains($empty['html'], 'view-display-id-block_1'), 'Empty scoped searches must restore the shop-specific View.');
  print "PASS: shop isolation, Gazdabolt-only CSV stock, invalid scope handling, and scoped empty searches.\n";
}
finally {
  $transaction->rollBack();
}
