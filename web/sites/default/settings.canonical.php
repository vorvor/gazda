<?php

/**
 * @file
 * Canonical production host validation shared by deployed environments.
 *
 * Include this file at the end of sites/default/settings.php:
 * @code
 * if (file_exists($app_root . '/' . $site_path . '/settings.canonical.php')) {
 *   include $app_root . '/' . $site_path . '/settings.canonical.php';
 * }
 * @endcode
 */

$settings['trusted_host_patterns'] = [
  '^setaljbe\.hu$',
];

// Lando's development hostname is not accepted in production. Lando provides
// the LANDO environment variable to its appserver containers.
if (getenv('LANDO') === 'ON') {
  $settings['trusted_host_patterns'][] = '^gazda\.lndo\.site$';
}
