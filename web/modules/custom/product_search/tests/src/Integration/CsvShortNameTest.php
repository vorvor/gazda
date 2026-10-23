<?php

use Drupal\product_search\Controller\ProductSearchController;

$search = \Drupal::service('product_search.csv_search');
$search->search('Limezöld műanyag lombseprű');
if (!method_exists($search, 'shortName') || $search->shortName('Limezöld műanyag lombseprű') !== 'Lombseprű') {
  throw new RuntimeException('The CSV short name must be available separately from the full name.');
}
$controller = ProductSearchController::create(\Drupal::getContainer());
$method = new ReflectionMethod($controller, 'buildSearchResults');
$build = $method->invoke($controller, [], ['Limezöld műanyag lombseprű']);
$card = $build['csv'][0];
if ($card['name']['text']['#plain_text'] !== 'Lombseprű'
  || $card['description']['text']['#plain_text'] !== 'Limezöld műanyag lombseprű'
  || $card['placeholder']['text']['#plain_text'] !== 'L') {
  throw new RuntimeException('Cards must show the short title, its initial, and the full-name description.');
}
if ($search->shortName('New product without short name') !== 'New product without short name') {
  throw new RuntimeException('Missing short names must safely fall back to the original name.');
}
$html = (string) \Drupal::service('renderer')->renderRoot($build);
if (!str_contains($html, 'Lombseprű</h3>') || !str_contains($html, 'product-search-csv-description')) {
  throw new RuntimeException('Short titles and full descriptions must render in the card.');
}
print "PASS: CSV short title, full-name description, initial, and fallback.\n";
