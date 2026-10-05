<?php
require_once __DIR__ . '/auth.php';
// Actual allowlist for this module; never derive permissions from display labels.
function felixAssignmentsAdmin(string $user): bool { return $user === 'Elek'; }
if (!felixAssignmentsAdmin($authenticatedUser)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Ehhez a funkcióhoz adminisztrátori jogosultság szükséges.']);
    exit;
}
