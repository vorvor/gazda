<?php
// Read-only personal card. Source filenames and legacy IDs are not registry IDs.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments_db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function personCardError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
function personCardFile(string $file): bool {
    return $file === basename($file) && preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/D', $file);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    personCardError(405, 'Nem támogatott kérés.');
}
$id = $_GET['id'] ?? '';
$file = $_GET['file'] ?? null;
$name = $_GET['name'] ?? null;
if (!is_string($id) || $id === '' || ($name !== null && !is_string($name)) ||
    ($file !== null && (!is_string($file) || !personCardFile($file))) ||
    ($file === null && !preg_match('/^[1-9][0-9]*$/D', $id))) {
    personCardError(400, 'Érvénytelen személyazonosító vagy projektfájl.');
}
try {
    $db = felixAssignmentsDb();
    $canonical = $id;
    if ($file !== null) {
        $matches = array_filter($db->sources, fn($s) => $s['file'] === $file && $s['legacy_id'] === $id && ($name === null || $s['name'] === $name));
        $ids = array_unique(array_column($matches, 'person_id'));
        if (count($ids) !== 1) personCardError(404, 'Nincs egyértelmű nyilvántartási kapcsolat ehhez a bejegyzéshez.');
        $canonical = (string)reset($ids);
    }
    $person = $db->person($canonical);
    if (!$person) personCardError(404, 'A személy nem található.');
    $rosters = [];
    $seen = [];
    $fields = ['id','name','allergy','cls','time','arrived','late','cancelled','reason','notes','checkedOut','flag'];
    foreach ($db->sources as $source) {
        if ((string)$source['person_id'] !== $canonical || !personCardFile($source['file'])) continue;
        $owners = array_unique(array_column(array_filter($db->sources, fn($s) =>
            $s['file'] === $source['file'] && $s['legacy_id'] === $source['legacy_id'] && $s['name'] === $source['name']), 'person_id'));
        if (count($owners) !== 1) continue;
        $path = felixDataDir() . '/' . $source['file'];
        if (!is_file($path) || is_link($path)) continue;
        foreach (felixReadCsv($path) as $row) {
            // A stale mapping must never reveal a replacement person's data.
            if (trim($row['id'] ?? '') !== $source['legacy_id'] || trim($row['name'] ?? '') !== $source['name']) continue;
            $key = $source['file'] . "\0" . $source['legacy_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $rosters[] = ['file' => $source['file'], 'date' => substr($source['file'], 0, 10), 'row' => array_intersect_key($row, array_flip($fields))];
        }
    }
    usort($rosters, fn($a, $b) => strcmp($a['file'], $b['file']));
    $assignments = [];
    foreach ($db->assignments as $assignment) {
        if ((string)$assignment['person_id'] !== $canonical) continue;
        foreach ($db->days as $day) if ($day['id'] === $assignment['day_id']) {
            $assignments[] = ['date' => $day['date'], 'title' => $day['title'], 'assigned_by' => $assignment['assigned_by'], 'created_at' => $assignment['created_at']];
        }
    }
    echo json_encode(['person' => array_intersect_key($person, array_flip(['id','name','city','fictional'])), 'rosters' => $rosters, 'assignments' => $assignments], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    personCardError(503, 'A mentett személyadatok most nem érhetők el. Próbáld újra.');
}
