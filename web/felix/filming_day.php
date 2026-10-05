<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments_db.php';
header('Content-Type: application/json; charset=utf-8');
function filmingDayError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    filmingDayError(405, 'Nem támogatott kérés.');
}
$date = $_GET['date'] ?? '';
if ($date !== '' && !felixValidFilmingDate($date)) filmingDayError(400, 'Érvénytelen forgatási nap.');
try {
    $db = felixAssignmentsDb();
    if ($date === '') $date = felixDefaultFilmingDate($db);
    $day = felixFilmingDay($db, $date);
    echo json_encode([
        'day' => $day,
        'assigned' => felixFilmingAssigned($db, $day['id']),
        'days' => $db->dayList(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Felix filming day database failure: ' . get_class($e));
    filmingDayError(503, 'A CSV-tároló most nem érhető el. Próbálja újra később.');
}
