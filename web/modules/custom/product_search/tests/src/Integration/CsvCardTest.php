<?php

use Drupal\product_search\Controller\ProductSearchController;

$controller = ProductSearchController::create(\Drupal::getContainer());
$method = new ReflectionMethod($controller, 'buildSearchResults');
$build = $method->invoke($controller, [], ['Szöges kábelrögzítő bilincs', 'szalag', 'Ásó']);
$cards = $build['csv'];
if (($cards[0]['placeholder']['text']['#plain_text'] ?? '') !== 'S'
  || ($cards[2]['placeholder']['text']['#plain_text'] ?? '') !== 'Á') {
  throw new RuntimeException('Cards must display the uppercase Unicode initial.');
}
$style = $cards[0]['placeholder']['#attributes']['style'] ?? '';
if (!$style || $style !== $cards[1]['placeholder']['#attributes']['style']
  || $style === $cards[2]['placeholder']['#attributes']['style']) {
  throw new RuntimeException('Initial colors must be deterministic and vary by letter.');
}
$html = (string) \Drupal::service('renderer')->renderRoot($build);
if (!str_contains($html, 'GAZDABOLT') || !str_contains($html, 'termék fotó nem elérhető jelenleg')) {
  throw new RuntimeException('Cards must keep the shop label and photo notice.');
}
print "PASS: Unicode initials, stable letter colors, shop label, and photo notice.\n";
