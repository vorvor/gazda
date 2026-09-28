<?php
require_once __DIR__ . '/session_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the Log out button.');
}
if (!validLoginCsrf()) {
    http_response_code(403);
    exit('Invalid logout request.');
}
// Keep a logout marker so cached browser Basic credentials cannot log back in.
$_SESSION = ['logged_out' => true, 'csrf' => bin2hex(random_bytes(32))];
session_regenerate_id(true);
session_write_close();
header('Location: login.php', true, 303);
