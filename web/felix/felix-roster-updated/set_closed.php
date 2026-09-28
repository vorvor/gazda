<?php
require_once __DIR__ . '/auth.php';

// Closes or reopens a project day. Only an Administrator may call this —
// enforced here server-side, not just hidden in the UI. While closed,
// save_roster.php rejects every edit (see the 423 check there);
// reopening is the only way back, and only an Administrator can do it.

header('Content-Type: application/json; charset=utf-8');

if (($userRoleLabels[$authenticatedUser] ?? '') !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only an administrator can close or reopen a day.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['file']) || !is_string($body['file']) || !array_key_exists('closed', $body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$filename = basename($body['file']);
$closed   = (bool) $body['closed'];

if ($filename === '' || !preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/', $filename)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file name']);
    exit;
}

$dataDir  = __DIR__ . '/data';
$csvPath  = $dataDir . '/' . $filename;
$metaPath = $dataDir . '/meta.json';
$logDir   = __DIR__ . '/logs';

if (!is_file($csvPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'File not found']);
    exit;
}

$lock = fopen($dataDir . '/.save.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not lock saves']);
    exit;
}

try {
    $meta = [];
    if (is_file($metaPath)) {
        $decoded = json_decode(file_get_contents($metaPath), true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $oldClosed = !empty($meta[$filename]['closed']);

    if (!isset($meta[$filename]) || !is_array($meta[$filename])) {
        $meta[$filename] = ['session' => 'checkin'];
    }

    if ($oldClosed === $closed) {
        echo json_encode(['ok' => true, 'closed' => $closed]); // nothing changed
        exit;
    }

    $meta[$filename]['closed'] = $closed;

    $metaTemp = tempnam($dataDir, '.meta-');
    if ($metaTemp === false || file_put_contents($metaTemp, json_encode($meta, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Could not write session metadata');
    }
    if (!rename($metaTemp, $metaPath)) {
        throw new RuntimeException('Could not replace session metadata');
    }

    // --- Audit log entry (per-project + general), same shape as saveRosterWithAudit ---
    if (!is_dir($logDir) && !mkdir($logDir, 0750, true) && !is_dir($logDir)) {
        throw new RuntimeException('Could not create audit directory');
    }

    $event     = bin2hex(random_bytes(16));
    $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d\TH:i:s.uP');
    $action    = $closed ? 'close' : 'reopen';
    $entry     = [$event, $timestamp, $authenticatedUser, $filename, $action, '', '', 'closed', $oldClosed ? '1' : '0', $closed ? '1' : '0'];
    $header    = ['event_id', 'timestamp', 'user', 'project', 'action', 'person_id', 'person_name', 'field', 'old_value', 'new_value'];

    $logPath = $logDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '-log.csv';
    foreach ([$logPath, $logDir . '/general-log.csv'] as $path) {
        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not open audit log');
        }
        fseek($handle, 0, SEEK_END);
        if (ftell($handle) === 0) {
            fputcsv($handle, $header, ',', '"', '');
        }
        fputcsv($handle, $entry, ',', '"', '');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    echo json_encode(['ok' => true, 'closed' => $closed]);
} catch (Throwable $error) {
    error_log('set_closed failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not update the closed state']);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
    if (isset($metaTemp) && $metaTemp && is_file($metaTemp)) {
        unlink($metaTemp);
    }
}
