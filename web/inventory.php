<?php

/**
 * @file
 * Live, standalone photo inventory. No Drupal bootstrap is required.
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

/**
 * Escapes plain text for HTML text and attributes.
 */
function inventory_escape(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Read the membership list once per request, matching exact unique IDs.
$on_setalbe = [];
$membership_error = NULL;
$membership_handle = @fopen(__DIR__ . '/gazdabolt_full_stock/products_on_setaljbe.csv', 'rb');
if ($membership_handle === FALSE) {
  $membership_error = 'A products_on_setaljbe.csv fájl nem olvasható; a zöld jelölés nem elérhető.';
}
else {
  $membership_columns = fgetcsv($membership_handle, 0, ',', '"', '');
  if (is_array($membership_columns)) {
    $membership_columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $membership_columns[0] ?? '');
  }
  $id_column = is_array($membership_columns) ? array_search('unique_id', $membership_columns, TRUE) : FALSE;
  $source_column = is_array($membership_columns) ? array_search('forrás', $membership_columns, TRUE) : FALSE;
  if ($id_column === FALSE) {
    $membership_error = 'A products_on_setaljbe.csv fejlécéből hiányzik a unique_id oszlop; a zöld jelölés nem elérhető.';
  }
  else {
    while (($values = fgetcsv($membership_handle, 0, ',', '"', '')) !== FALSE) {
      if ($values === [NULL]) {
        continue;
      }
      if (count($values) !== count($membership_columns)) {
        $membership_error = 'A products_on_setaljbe.csv egyik sora hibás; a zöld jelölés nem elérhető.';
        $on_setalbe = [];
        break;
      }
      if ($values[$id_column] !== '') {
        $source = $source_column !== FALSE ? $values[$source_column] : '';
        // Keep the green marker even when a source URL is missing or unsafe.
        $sources = [];
        foreach (preg_split('/;\s+/', $source) as $url) {
          if (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], TRUE)) {
            $sources[] = $url;
          }
        }
        $on_setalbe[$values[$id_column]] = $sources;
      }
    }
  }
  fclose($membership_handle);
}

$photos = [];
foreach (new DirectoryIterator(__DIR__ . '/gazdabolt_full_stock') as $file) {
  // Labelled copies and subdirectories are deliberately not inventory photos.
  if ($file->isFile() && preg_match('/^(?:processed_)?(\d{8}_\d{6})\.(?:jpe?g|png|webp|gif|avif)$/i', $file->getFilename(), $match)) {
    $photos[$file->getFilename()] = $match[1];
  }
}
ksort($photos, SORT_NATURAL);
$products = [];
$total = 0;
$error = NULL;
$handle = @fopen(__DIR__ . '/gazdabolt_full_stock/merged_products.csv', 'rb');
if ($handle === FALSE) {
  $error = 'A merged_products.csv fájl nem olvasható.';
}
else {
  $columns = fgetcsv($handle, 0, ',', '"', '');
  if (is_array($columns)) {
    $columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $columns[0] ?? '');
  }
  $required = ['unique_id', 'termék neve', 'leírás', 'termék típusa'];
  if (!is_array($columns) || array_diff($required, $columns)) {
    $error = 'A CSV fejlécéből hiányzik egy kötelező oszlop.';
  }
  else {
    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
      if ($values === [NULL]) {
        continue;
      }
      if (count($values) !== count($columns)) {
        $error = 'A CSV egyik sora hibás: az oszlopok száma nem egyezik a fejléccel.';
        break;
      }
      $row = array_combine($columns, $values);
      $key = preg_match('/^(\d{8}_\d{6})-\d+$/', $row['unique_id'], $match) ? $match[1] : '';
      $products[$key][] = $row;
      $total++;
    }
  }
  fclose($handle);
}
if ($error !== NULL) {
  http_response_code(500);
  // Never present a partially read CSV as a complete inventory.
  $products = [];
  $total = 0;
}
$unmatched = array_diff_key($products, array_fill_keys(array_values($photos), TRUE));

/**
 * Renders product rows in their original CSV order.
 */
function inventory_products(array $rows, array $on_setalbe = []): void {
  if (!$rows) {
    echo '<p class="empty">Ehhez a fényképhez nincs kapcsolódó termékbejegyzés a CSV-ben.</p>';
    return;
  }
  echo '<ol>';
  foreach ($rows as $row) {
    $class = isset($on_setalbe[$row['unique_id']]) ? ' class="on-setalbe"' : '';
    echo '<li' . $class . ' data-product-id="' . inventory_escape($row['unique_id']) . '"><h3>' . inventory_escape($row['termék neve']) . '</h3>';
    echo '<p class="meta">Azonosító: ' . inventory_escape($row['unique_id']) . ' · Terméktípus: ' . inventory_escape($row['termék típusa']) . '</p>';
    if (trim($row['leírás']) !== '') {
      echo '<p class="description">' . nl2br(inventory_escape($row['leírás'])) . '</p>';
    }
    foreach ($on_setalbe[$row['unique_id']] ?? [] as $source) {
      echo '<p><a class="setaljbe-link" href="' . inventory_escape($source) . '" target="_blank" rel="noopener noreferrer">Megtekintés a Sétálj be oldalon</a></p>';
    }
    echo '</li>';
  }
  echo '</ol>';
}
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gazdabolt – képes termékleltár</title>
<link rel="stylesheet" href="gazdabolt_full_stock/inventory.css">
</head>
<body>
<header id="top"><h1>Gazdabolt – képes termékleltár</h1>
<?php if ($error === NULL): ?>
<p><?= count($photos) ?> feldolgozott fénykép · <?= $total ?> termékbejegyzés</p>
<?php endif; ?>
<p>A fényképek alatt az egyedi azonosító alapján hozzájuk kapcsolt termékek szerepelnek. A képre kattintva az eredeti fotó nyitható meg.</p>
<p>Forrás: <a href="merged_products.csv">merged_products.csv</a></p></header>
<main>
<?php if ($membership_error !== NULL): ?>
<p class="empty" role="alert"><?= inventory_escape($membership_error) ?></p>
<?php endif; ?>
<?php if ($error !== NULL): ?>
<p class="empty" role="alert"><?= inventory_escape($error) ?></p>
<?php else: ?>
<?php $first = TRUE; ?>
<?php foreach ($photos as $filename => $photo_id): ?>
<?php $rows = $products[$photo_id] ?? []; ?>
<article id="photo-<?= inventory_escape($photo_id) ?>" data-photo="gazdabolt_full_stock/<?= inventory_escape($filename) ?>">
<figure><a href="gazdabolt_full_stock/<?= rawurlencode($filename) ?>" target="_blank" rel="noopener"><img src="gazdabolt_full_stock/<?= rawurlencode($filename) ?>" alt="Gazdabolt készletfotó: <?= inventory_escape($photo_id) ?>" loading="<?= $first ? 'eager' : 'lazy' ?>" decoding="async"></a><figcaption><?= inventory_escape($filename) ?></figcaption></figure>
<div class="products"><h2>Kapcsolódó termékek (<?= count($rows) ?>)</h2>
<?php inventory_products($rows, $on_setalbe); ?>
</div></article>
<?php $first = FALSE; ?>
<?php endforeach; ?>
<?php if ($unmatched): ?>
<article id="unmatched"><div class="products"><h2>Fénykép nélküli termékbejegyzések</h2>
<p class="empty">Ezekhez az azonosítókhoz nem található megfelelő fénykép a könyvtárban.</p>
<?php inventory_products(array_merge(...array_values($unmatched)), $on_setalbe); ?>
</div></article>
<?php endif; ?>
<?php endif; ?>
</main><footer>A CSV és a fényképek aktuális tartalmából, minden betöltéskor frissítve. <a href="#top">Vissza az oldal tetejére</a></footer>
</body>
</html>
