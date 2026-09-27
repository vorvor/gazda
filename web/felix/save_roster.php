<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit_log.php';

// Receives { file, session, rows } as JSON and:
//   - rewrites data/<file>.csv with the current rows
//   - records that file's session (checkin/checkout) in data/meta.json
// so both the row statuses and the check-in/check-out phase survive a
// reload, per project file.

header('Content-Type: application/json; charset=utf-8');

$dataDir  = __DIR__ . '/data';
$metaPath = $dataDir . '/meta.json';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['file']) || !is_string($body['file']) || !isset($body['rows']) || !is_array($body['rows'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$filename = basename($body['file']);
$session  = isset($body['session']) && $body['session'] === 'checkout' ? 'checkout' : 'checkin';

if ($filename === '' || !preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/', $filename)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file name']);
    exit;
}

$csvPath = $dataDir . '/' . $filename;

if (!is_file($csvPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'File not found']);
    exit;
}

$ids = [];
foreach ($body['rows'] as $person) {
    if (!is_array($person) || !isset($person['id']) || !is_scalar($person['id']) || (string) $person['id'] === '' || isset($ids[(string) $person['id']])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid or duplicate person ID']);
        exit;
    }
    foreach ($person as $value) {
        if ($value !== null && !is_scalar($value)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid person field']);
            exit;
        }
    }
    $ids[(string) $person['id']] = true;
}

$actions = ['save', 'undo', 'session', 'checkout', 'arrived', 'diet', 'flag', 'notes', 'late', 'cancelled'];
$action = isset($body['action']) && in_array($body['action'], $actions, true) ? $body['action'] : 'save';
try {
    saveRosterWithAudit($dataDir, $filename, $session, $body['rows'], $authenticatedUser, $action);
} catch (Throwable $error) {
    error_log('Roster/audit save failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save roster and audit log']);
    exit;
}

echo json_encode(['ok' => true]);
