<?php
// Presentation-only accounts shared by Basic authentication and explicit login.
$demoUsers = [
    'Elek' => '$2y$12$fQ1MGMHXQmfLrJfMKmQx/OUOj3KzeoWZMmXNVmxrr4QUCFwCV7Ldy',
    'Kecsu' => '$2y$12$fQ1MGMHXQmfLrJfMKmQx/OUOj3KzeoWZMmXNVmxrr4QUCFwCV7Ldy',
];

// Display labels only; access-control permissions are not yet configured.
$userRoleLabels = [
    'Elek' => 'Administrator',
    'Kecsu' => 'Crowd marshall',
    'Thomas' => 'Reader',
];

session_name('FELIXSESSID');
$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
session_start([
    'use_strict_mode' => true,
    'cookie_path' => $cookiePath,
    'cookie_httponly' => true,
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
]);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf'];

function validLoginCsrf() {
    return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
