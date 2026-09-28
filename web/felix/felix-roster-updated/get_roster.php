<?php
require_once __DIR__ . '/auth.php';

// Reads data/<file>.csv (one dated project file) and returns its rows
// plus its saved session state (checkin/checkout) as JSON.

header('Content-Type: application/json; charset=utf-8');

$dataDir  = __DIR__ . '/data';
$metaPath = $dataDir . '/meta.json';

$requested = isset($_GET['file']) ? $_GET['file'] : '';
$filename  = basename($requested); // strip any path components

// Only accept files that actually match our naming convention and exist.
if ($filename === '' || !preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing file parameter']);
    exit;
}

$csvPath = $dataDir . '/' . $filename;

if (!is_file($csvPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$roster = [];

if (($handle = fopen($csvPath, 'r')) !== false) {
    $header = fgetcsv($handle); // id,name,allergy,cls,time,arrived,late,cancelled,reason,notes,checkedOut

    while (($row = fgetcsv($handle)) !== false) {
        if (!isset($row[0]) || $row[0] === null || $row[0] === '') {
            continue;
        }

        $roster[] = [
            'id'         => trim($row[0]),
            'name'       => isset($row[1]) ? trim($row[1]) : '',
            'allergy'    => isset($row[2]) ? $row[2] : '',
            'cls'        => isset($row[3]) ? $row[3] : '',
            'time'       => isset($row[4]) ? $row[4] : '',
            'arrived'    => isset($row[5]) && $row[5] === '1',
            'late'       => isset($row[6]) && $row[6] === '1',
            'cancelled'  => isset($row[7]) && $row[7] === '1',
            'reason'     => isset($row[8]) ? $row[8] : '',
            'notes'      => isset($row[9]) ? $row[9] : '',
            'checkedOut' => isset($row[10]) && $row[10] === '1',
            'flag'       => isset($row[11]) ? $row[11] : '',
        ];
    }

    fclose($handle);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Could not open ' . $filename]);
    exit;
}

$session = 'checkin';
$closed = false;
if (is_file($metaPath)) {
    $meta = json_decode(file_get_contents($metaPath), true);
    if (is_array($meta) && isset($meta[$filename]['session'])) {
        $session = $meta[$filename]['session'];
    }
    if (is_array($meta) && !empty($meta[$filename]['closed'])) {
        $closed = true;
    }
}

echo json_encode(['rows' => $roster, 'session' => $session, 'closed' => $closed]);
