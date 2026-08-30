<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/PackageArchive.php';

use Drupal\content_transfer\PackageArchive;

function package_archive_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$temporaryFiles = [];
$createArchive = static function (array $manifest, ?string $extraEntry = NULL) use (&$temporaryFiles): string {
  $path = tempnam(sys_get_temp_dir(), 'content-transfer-test-');
  if ($path === FALSE) {
    throw new RuntimeException('Unable to create a temporary test file.');
  }
  $temporaryFiles[] = $path;
  $zip = new ZipArchive();
  package_archive_assert($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE, 'The test ZIP must open.');
  $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
  if ($extraEntry !== NULL) {
    $zip->addFromString($extraEntry, 'unsafe');
  }
  $zip->close();
  return $path;
};

try {
  $archive = new PackageArchive();
  $manifest = $archive->readManifest($createArchive([
    'format' => 'content-transfer',
    'version' => 1,
    'entities' => [],
  ]));
  package_archive_assert($manifest['version'] === 1, 'A supported manifest must be read.');

  $unsafeRejected = FALSE;
  try {
    $archive->readManifest($createArchive([
      'format' => 'content-transfer',
      'version' => 1,
      'entities' => [],
    ], '../outside.txt'));
  }
  catch (InvalidArgumentException $exception) {
    $unsafeRejected = str_contains($exception->getMessage(), 'unsafe path');
  }
  package_archive_assert($unsafeRejected, 'An unsafe ZIP path must be rejected.');

  $versionRejected = FALSE;
  try {
    $archive->readManifest($createArchive([
      'format' => 'content-transfer',
      'version' => 2,
      'entities' => [],
    ]));
  }
  catch (InvalidArgumentException $exception) {
    $versionRejected = str_contains($exception->getMessage(), 'version');
  }
  package_archive_assert($versionRejected, 'An unsupported version must be rejected.');

  print "PASS: package archive validation\n";
}
finally {
  foreach ($temporaryFiles as $path) {
    if (is_file($path)) {
      unlink($path);
    }
  }
}
