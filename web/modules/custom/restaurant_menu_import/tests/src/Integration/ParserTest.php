<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/MealRecord.php';
require_once __DIR__ . '/../../../src/SourceResolver.php';
require_once __DIR__ . '/../../../src/PdfMealExtractor.php';
require_once __DIR__ . '/../../../src/HtmlMealExtractor.php';

use Drupal\restaurant_menu_import\HtmlMealExtractor;
use Drupal\restaurant_menu_import\PdfMealExtractor;
use Drupal\restaurant_menu_import\SourceResolver;

function assert_same(mixed $expected, mixed $actual, string $message): void {
  if ($expected !== $actual) {
    throw new RuntimeException($message . "\nExpected: " . var_export($expected, TRUE) . "\nActual: " . var_export($actual, TRUE));
  }
}

$resolver = new SourceResolver();
$sources = $resolver->resolve("https://example.com/menu.pdf\nhttps://example.com/chef.pdf\n");
assert_same(2, count($sources), 'Direct PDF URLs must resolve separately.');
assert_same('pdf', $sources[0]['type'], 'A .pdf URL must resolve as a PDF source.');

$fragment = $resolver->resolve('https://example.com/#block-menu');
assert_same('html_fragment', $fragment[0]['type'], 'A URL fragment must resolve as an HTML fragment source.');
assert_same('block-menu', $fragment[0]['selector'], 'The fragment ID must be preserved.');

$section = $resolver->resolve('PDF documents from section with class .menu-downloads on https://example.com/');
assert_same('html_pdf_section', $section[0]['type'], 'The PDF-section instruction must be recognized.');
assert_same('menu-downloads', $section[0]['selector'], 'The section class must be preserved.');

$html_section = $resolver->resolve('section wit class .meals on site https://burgerplus.hu/etlap');
assert_same('html_section', $html_section[0]['type'], 'A named HTML section must be recognized.');
assert_same('meals', $html_section[0]['selector'], 'The HTML section class must be preserved.');

$pdf_text = <<<'TEXT'
SÁRKÁNY SALÁTA
Paradicsom, paprika, uborka és füstölt tofu (6)
3200 HUF

ERDEI GOMBAKRÉMLEVES CIPÓBAN
Illatos gombák, fehérbor, pirított hagyma (1, 7, 12)
3800 HUF
TEXT;
$pdf_records = (new PdfMealExtractor())->extractText($pdf_text, 'https://example.com/menu.pdf');
assert_same(2, count($pdf_records), 'Two contiguous PDF menu entries must be extracted.');
assert_same('SÁRKÁNY SALÁTA', $pdf_records[0]->name, 'The PDF meal title must be preserved.');
assert_same('3200.00', $pdf_records[0]->price, 'The PDF price must be normalized.');
assert_same('Paradicsom, paprika, uborka és füstölt tofu', $pdf_records[0]->description, 'Allergen suffixes must be removed from descriptions.');

$jumbled_pdf = <<<'TEXT'
lángos
rántott sajt egy kicsit másképp
3300 HUF
TEXT;
assert_same(0, count((new PdfMealExtractor())->extractText($jumbled_pdf, 'https://example.com/jumbled.pdf')), 'Ambiguous PDF columns must be skipped.');

$variant_pdf = <<<'TEXT'
VARGÁNYÁS RIZOTTÓ
Arborio rizs, vargánya, tejszín
4800 / 6200* HUF
MÁKSÜTI
Alma, mák (1, 3)
3100 HUF
DRAGON SALAD
Tomato and lettuce
3200 HUF
TEXT;
$variant_records = (new PdfMealExtractor())->extractText($variant_pdf, 'https://example.com/variants.pdf');
assert_same(1, count($variant_records), 'Unsupported multi-price rows and a following English translation must not poison Hungarian records.');
assert_same('MÁKSÜTI', $variant_records[0]->name, 'The valid meal after a skipped variant must still be extracted.');

$html = <<<'HTML'
<div id="block-menu">
  <div class="views-row">
    <div class="views-field-title"><a>avgolemono - görög csirkeraguleves</a></div>
    <div class="views-field-field-dish-price">1690.-</div>
    <div class="views-field-body">citromos csirkehúsleves, tojássárgájával</div>
  </div>
</div>
HTML;
$html_records = (new HtmlMealExtractor())->extract($html, 'https://example.com/', 'block-menu');
assert_same(1, count($html_records), 'The selected HTML block must produce one meal.');
assert_same('avgolemono - görög csirkeraguleves', $html_records[0]->name, 'The HTML title must be extracted.');
assert_same('1690.00', $html_records[0]->price, 'The HTML price must be normalized.');

$card_html = <<<'HTML'
<div class="meals">
  <div class="card meal__item">
    <h2 class="food-name"><b>Kentucky smash burger</b></h2>
    <p class="card-description">buci, dupla smash marhahúspogácsa, cheddar sajt</p>
    <a class="btn to-cart">2 990 Ft</a>
  </div>
</div>
HTML;
$card_records = (new HtmlMealExtractor())->extract($card_html, 'https://burgerplus.hu/etlap', NULL, 'meals');
assert_same(1, count($card_records), 'A meal card in the selected class must be extracted.');
assert_same('Kentucky smash burger', $card_records[0]->name, 'The meal-card title must be extracted.');
assert_same('2990.00', $card_records[0]->price, 'The meal-card price must be normalized.');

print "PASS: source resolution and basic menu extraction\n";
