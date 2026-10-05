<?php
// Explicit operator-run backfill; never run during an ordinary list/get request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/assignments_db.php';
require_once __DIR__ . '/assignment_roster.php';
try {
    $db = felixAssignmentsDb();
    $dates = [];
    foreach ($db->days as $day) if (felixFilmingAssigned($db, $day['id'])) $dates[] = $day['date'];
    sort($dates);
    foreach ($dates as $date) {
        $file = felixAssignmentRosterTransaction($db, $date, 'assignment-backfill', function (FelixCsvStore $db): void {});
        fwrite(STDOUT, $date . ': ' . ($file ?? 'empty') . "\n");
    }
    fwrite(STDOUT, 'Synchronized dates: ' . count($dates) . "\n");
} catch (Throwable $error) {
    // Do not leak PDO configuration/credentials in CLI diagnostics.
    fwrite(STDERR, 'Assignment roster synchronization failed (' . get_class($error) . ", code " . $error->getCode() . ").\n");
    exit(1);
}
