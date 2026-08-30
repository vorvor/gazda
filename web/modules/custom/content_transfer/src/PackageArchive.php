<?php

declare(strict_types=1);

namespace Drupal\content_transfer;

/**
 * Creates and validates portable content ZIP archives.
 */
final class PackageArchive {

  private const FORMAT = 'content-transfer';
  private const VERSION = 1;
  private const MAX_ENTRIES = 5000;
  private const MAX_UNCOMPRESSED_BYTES = 536870912;
  private const MAX_MANIFEST_BYTES = 10485760;

  /**
   * Creates an archive from a manifest and local payload files.
   *
   * @param string $path
   *   Destination ZIP path.
   * @param array $manifest
   *   Package manifest.
   * @param array<string, string> $payloadFiles
   *   Archive paths keyed to local source paths.
   */
  public function create(string $path, array $manifest, array $payloadFiles): void {
    $manifest['format'] = self::FORMAT;
    $manifest['version'] = self::VERSION;

    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      throw new \RuntimeException('Unable to create the content transfer archive.');
    }

    try {
      $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      if (!$zip->addFromString('manifest.json', $json)) {
        throw new \RuntimeException('Unable to write the package manifest.');
      }
      foreach ($payloadFiles as $archivePath => $sourcePath) {
        $this->assertSafePath($archivePath);
        if (!is_file($sourcePath) || !$zip->addFile($sourcePath, $archivePath)) {
          throw new \RuntimeException(sprintf('Unable to add payload file "%s".', $archivePath));
        }
      }
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Validates an archive and returns its decoded manifest.
   */
  public function readManifest(string $path): array {
    $zip = $this->open($path);
    try {
      if ($zip->numFiles > self::MAX_ENTRIES) {
        throw new \InvalidArgumentException('The package contains too many entries.');
      }

      $totalSize = 0;
      for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if ($stat === FALSE || !isset($stat['name'], $stat['size'])) {
          throw new \InvalidArgumentException('The package contains an unreadable entry.');
        }
        $this->assertSafePath($stat['name']);
        $totalSize += (int) $stat['size'];
        if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
          throw new \InvalidArgumentException('The uncompressed package is too large.');
        }
      }

      $stat = $zip->statName('manifest.json');
      if ($stat === FALSE || (int) $stat['size'] > self::MAX_MANIFEST_BYTES) {
        throw new \InvalidArgumentException('The package manifest is missing or too large.');
      }
      $json = $zip->getFromName('manifest.json');
      if ($json === FALSE) {
        throw new \InvalidArgumentException('The package manifest cannot be read.');
      }
      $manifest = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('The package manifest is not valid JSON.', 0, $exception);
    }
    finally {
      $zip->close();
    }

    if (!is_array($manifest) || ($manifest['format'] ?? NULL) !== self::FORMAT) {
      throw new \InvalidArgumentException('This is not a content transfer package.');
    }
    if (($manifest['version'] ?? NULL) !== self::VERSION) {
      throw new \InvalidArgumentException('The package version is not supported.');
    }
    if (!isset($manifest['entities']) || !is_array($manifest['entities'])) {
      throw new \InvalidArgumentException('The package entity list is invalid.');
    }

    return $manifest;
  }

  /**
   * Reads one previously validated archive entry.
   */
  public function readEntry(string $path, string $entry): string {
    $this->assertSafePath($entry);
    $zip = $this->open($path);
    try {
      $contents = $zip->getFromName($entry);
      if ($contents === FALSE) {
        throw new \InvalidArgumentException(sprintf('Package payload "%s" is missing.', $entry));
      }
      return $contents;
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Opens a ZIP archive.
   */
  private function open(string $path): \ZipArchive {
    if (!is_file($path)) {
      throw new \InvalidArgumentException('The package file does not exist.');
    }
    $zip = new \ZipArchive();
    if ($zip->open($path) !== TRUE) {
      throw new \InvalidArgumentException('The package is not a readable ZIP archive.');
    }
    return $zip;
  }

  /**
   * Rejects absolute and traversal paths.
   */
  private function assertSafePath(string $path): void {
    $normalized = str_replace('\\', '/', $path);
    if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('@^[A-Za-z]:/@', $normalized) || in_array('..', explode('/', $normalized), TRUE)) {
      throw new \InvalidArgumentException(sprintf('The package contains an unsafe path: "%s".', $path));
    }
  }

}
