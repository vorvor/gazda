<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments_db.php';
header('Content-Type: application/json; charset=utf-8');

$allProjects = ($_GET['scope'] ?? '') === 'all';
$file = $allProjects ? null : ($_GET['file'] ?? '');
if (!$allProjects && (!is_string($file) || $file !== basename($file) || !preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/', $file))) {
    http_response_code(400);
    echo json_encode(['error' => 'Érvénytelen projektfájl']);
    exit;
}
if (!$allProjects && !is_file(felixDataDir() . '/' . $file)) {
    http_response_code(404);
    echo json_encode(['error' => 'A projekt nem található']);
    exit;
}

$path = dirname(felixDataDir()) . '/logs/' . ($allProjects ? 'general-log.csv' : pathinfo($file, PATHINFO_FILENAME) . '-log.csv');
if (!file_exists($path)) {
    echo json_encode(['file' => $file, 'records' => []]);
    exit;
}
$handle = is_file($path) ? fopen($path, 'r') : false;
if (!$handle) {
    http_response_code(500);
    echo json_encode(['error' => 'Nem sikerült beolvasni a projekt naplóját']);
    exit;
}
try {
    // Do not read a partially appended audit event.
    if (!flock($handle, LOCK_SH)) throw new RuntimeException('Could not lock project log');
    $header = fgetcsv($handle, 0, ',', '"', '');
    $records = [];
    if ($header !== false) {
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) !== count($header)) throw new RuntimeException('Invalid log record');
            $record = array_combine($header, $row);
            if ($allProjects || ($record['project'] ?? '') === $file) $records[] = $record;
        }
    }
    echo json_encode(['file' => $file, 'records' => array_reverse($records)], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Nem sikerült beolvasni a projekt naplóját']);
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}
