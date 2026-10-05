<?php
require_once __DIR__ . '/assignments_auth.php';
require_once __DIR__ . '/assignments_db.php';
require_once __DIR__ . '/assignment_roster.php';
header('Content-Type: application/json; charset=utf-8');
function assignmentError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); assignmentError(405, 'Nem támogatott kérés.'); }
if ($method === 'POST' && !validLoginCsrf()) assignmentError(403, 'A biztonsági munkamenet lejárt. Töltse újra az oldalt.');
$date = ($method === 'POST' ? $_POST['date'] ?? '' : $_GET['date'] ?? '');
if ($date !== '' && !felixValidFilmingDate($date)) assignmentError(400, 'Érvénytelen forgatási nap.');
try {
    $db = felixAssignmentsDb();
    if ($date === '') $date = felixDefaultFilmingDate($db);
    $day = felixFilmingDay($db, $date);
    if ($method === 'POST') {
        $id = $_POST['person_id'] ?? '';
        $action = $_POST['action'] ?? '';
        if (!is_string($id) || !ctype_digit($id) || (int)$id < 1 || !in_array($action, ['assign','remove'], true)) assignmentError(400, 'Érvénytelen személy vagy művelet.');
        if (!$db->person($id)) assignmentError(400, 'A személy nem található.');
        $file = felixAssignmentRosterTransaction($db, $date, $authenticatedUser, function (FelixCsvStore $db) use ($date, $action, $id, $authenticatedUser) {
            $day = felixFilmingDay($db, $date);
            if ($action === 'assign') {
                $day = $db->ensureDay($date, 'Forgatási nap');
                $db->assign($day['id'], $id, $authenticatedUser);
            } elseif ($day['id'] !== null) $db->remove($day['id'], $id);
        });
        echo json_encode(['ok' => true, 'file' => $file]);
        exit;
    }
    $search = $_GET['q'] ?? '';
    if (!is_string($search) || mb_strlen($search) > 120) assignmentError(400, 'A keresés legfeljebb 120 karakter lehet.');
    $people = $db->search($search);
    echo json_encode(['day' => $day, 'people' => $people, 'assigned' => felixFilmingAssigned($db, $day['id'])], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    if (in_array($e->getCode(), [409, 423], true)) assignmentError($e->getCode(), $e->getMessage());
    error_log('Felix assignment/roster failure: ' . get_class($e));
    assignmentError(503, 'A CSV-tároló most nem érhető el. Próbálja újra később.');
}
