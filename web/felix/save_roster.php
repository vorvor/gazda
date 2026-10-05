<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments_db.php';
require_once __DIR__ . '/audit_log.php';

// Receives { file, session, rows } as JSON and:
//   - rewrites data/<file>.csv with the current rows
//   - records that file's session (checkin/checkout) in data/meta.json
// so both the row statuses and the check-in/check-out phase survive a
// reload, per project file.

header('Content-Type: application/json; charset=utf-8');

$dataDir  = felixDataDir();
$metaPath = $dataDir . '/meta.json';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['file']) || !is_string($body['file']) || !isset($body['rows']) || !is_array($body['rows'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen kérésadatok']);
    exit;
}

$filename = basename($body['file']);
$session  = isset($body['session']) && $body['session'] === 'checkout' ? 'checkout' : 'checkin';

if ($filename === '' || !preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/', $filename)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen fájlnév']);
    exit;
}

$csvPath = $dataDir . '/' . $filename;

if (!is_file($csvPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'A fájl nem található']);
    exit;
}

$currentMeta = [];
if (is_file($metaPath)) {
    $decoded = json_decode(file_get_contents($metaPath), true);
    if (is_array($decoded)) {
        $currentMeta = $decoded;
    }
}
if (!empty($currentMeta[$filename]['closed'])) {
    http_response_code(423); // Locked
    echo json_encode(['ok' => false, 'error' => 'Ez a nap le van zárva, nem szerkeszthető.']);
    exit;
}

$ids = [];
foreach ($body['rows'] as $person) {
    if (!is_array($person) || !isset($person['id']) || !is_scalar($person['id']) || (string) $person['id'] === '' || isset($ids[(string) $person['id']])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy ismétlődő személyazonosító']);
        exit;
    }
    foreach ($person as $value) {
        if ($value !== null && !is_scalar($value)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Érvénytelen személyadatmező']);
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
    $status = in_array($error->getCode(), [409, 423], true) ? $error->getCode() : 500;
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $status === 500 ? 'Nem sikerült menteni a névsort és a módosítási naplót' : $error->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
