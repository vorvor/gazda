<?php
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

if (!is_array($body) || !isset($body['file']) || !isset($body['rows']) || !is_array($body['rows'])) {
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

// --- Write the CSV rows ---
$tmpPath = $csvPath . '.tmp';
$handle  = fopen($tmpPath, 'w');

if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write ' . $filename]);
    exit;
}

fputcsv($handle, [
    'id', 'name', 'allergy', 'cls', 'time',
    'arrived', 'late', 'cancelled', 'reason', 'notes', 'checkedOut',
]);

foreach ($body['rows'] as $person) {
    fputcsv($handle, [
        isset($person['id']) ? $person['id'] : '',
        isset($person['name']) ? $person['name'] : '',
        isset($person['allergy']) ? $person['allergy'] : '',
        isset($person['cls']) ? $person['cls'] : '',
        isset($person['time']) ? $person['time'] : '',
        !empty($person['arrived']) ? '1' : '0',
        !empty($person['late']) ? '1' : '0',
        !empty($person['cancelled']) ? '1' : '0',
        isset($person['reason']) ? $person['reason'] : '',
        isset($person['notes']) ? $person['notes'] : '',
        !empty($person['checkedOut']) ? '1' : '0',
    ]);
}

fclose($handle);
rename($tmpPath, $csvPath);

// --- Record this file's session state in meta.json ---
$meta = [];
if (is_file($metaPath)) {
    $decoded = json_decode(file_get_contents($metaPath), true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}

$meta[$filename] = ['session' => $session];

file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true]);
