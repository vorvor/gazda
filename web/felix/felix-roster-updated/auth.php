<?php
require_once __DIR__ . '/session_auth.php';

if (isset($_SESSION['user']) && isset($demoUsers[$_SESSION['user']])) {
    $authenticatedUser = $_SESSION['user'];
    unset($demoUsers);
    session_write_close();
    return;
}

// A browser may keep sending Basic credentials after logout. Require an
// explicit form login instead of silently authenticating those credentials.
if (!empty($_SESSION['logged_out'])) {
    session_write_close();
    if (basename($_SERVER['SCRIPT_NAME']) === 'index.php') {
        header('Location: login.php', true, 303);
    } else {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'You are logged out. Please sign in again.']);
    }
    exit;
}

$authenticatedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$password = $_SERVER['PHP_AUTH_PW'] ?? '';

// Some CGI/FastCGI servers forward Authorization without PHP_AUTH_* values.
if ($authenticatedUser === '') {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($authorization, 'Basic ') === 0) {
        $credentials = base64_decode(substr($authorization, 6), true);
        if ($credentials !== false && strpos($credentials, ':') !== false) {
            [$authenticatedUser, $password] = explode(':', $credentials, 2);
        }
    }
}

header('Cache-Control: no-store');
if (!isset($demoUsers[$authenticatedUser]) || !password_verify($password, $demoUsers[$authenticatedUser])) {
    header('WWW-Authenticate: Basic realm="Felix Roster", charset="UTF-8"');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Please sign in to view the roster.';
    exit;
}
unset($password, $demoUsers, $authorization, $credentials);
session_regenerate_id(true);
$_SESSION['user'] = $authenticatedUser;
session_write_close();
