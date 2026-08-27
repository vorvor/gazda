<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/MealRecord.php';
require_once __DIR__ . '/../../../src/SourceResolver.php';
require_once __DIR__ . '/../../../src/PdfMealExtractor.php';
require_once __DIR__ . '/../../../src/HtmlMealExtractor.php';
require_once __DIR__ . '/../../../src/MealSynchronizer.php';
require_once __DIR__ . '/../../../src/RestaurantMenuImporter.php';

use Drupal\restaurant_menu_import\HtmlMealExtractor;
use Drupal\restaurant_menu_import\MealSynchronizer;
use Drupal\restaurant_menu_import\PdfMealExtractor;
use Drupal\restaurant_menu_import\RestaurantMenuImporter;
use Drupal\restaurant_menu_import\SourceResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use Smalot\PdfParser\Parser;

$html = <<<'HTML'
<div id="block-views-steaks-block-6">
  <div class="views-row">
    <div class="views-field-title">avgolemono - görög csirkeraguleves</div>
    <div class="views-field-field-dish-price">1690.-</div>
    <div class="views-field-body">citromos csirkehúsleves</div>
  </div>
</div>
HTML;
$mock = new MockHandler([new Response(200, ['Content-Type' => 'text/html'], $html)]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
$importer = new RestaurantMenuImporter(
  \Drupal::entityTypeManager(),
  $client,
  \Drupal::lock(),
  new NullLogger(),
  new SourceResolver(),
  new PdfMealExtractor(),
  new HtmlMealExtractor(),
  new MealSynchronizer(\Drupal::entityTypeManager()),
  new Parser(),
);
$result = $importer->import(414, TRUE);
if ($result['extracted'] !== 1 || $result['sources'] !== 1) {
  throw new RuntimeException('A dry run must extract one meal from the configured fragment source.');
}
if ($result['updated'] !== 1) {
  throw new RuntimeException('The dry run must report the existing changed meal as an update.');
}

$empty_mock = new MockHandler([new Response(200, ['Content-Type' => 'text/html'], '<div id="block-views-steaks-block-6"></div>')]);
$empty_importer = new RestaurantMenuImporter(
  \Drupal::entityTypeManager(),
  new Client(['handler' => HandlerStack::create($empty_mock)]),
  \Drupal::lock(),
  new NullLogger(),
  new SourceResolver(),
  new PdfMealExtractor(),
  new HtmlMealExtractor(),
  new MealSynchronizer(\Drupal::entityTypeManager()),
  new Parser(),
);
$empty_result = $empty_importer->import(414, TRUE);
if ($empty_result['errors'] !== 1) {
  throw new RuntimeException('A readable source with no confidently extracted meals must be reported as an error.');
}

print "PASS: restaurant import orchestration supports a dry run\n";
