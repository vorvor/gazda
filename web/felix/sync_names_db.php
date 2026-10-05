<?php
// Legacy command name retained; CSV only. Never opens a database connection.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/csv_sync.php';
try {
    $db=felixAssignmentsDb(); $db->beginTransaction();
    // Optional one-time read-only SQL export supplied as a private JSON file.
    if (isset($argv[1])) {
        if ($db->people || $db->days || $db->assignments) throw new RuntimeException('Import requires empty CSV registry');
        $export=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
        $db->people=$export['people']; $db->days=$export['filming_days']; $db->assignments=$export['day_assignments'];
        foreach(['people','days','assignments'] as $table) foreach($db->$table as &$row) foreach(['id','day_id','person_id','fictional'] as $key) if(isset($row[$key])) $row[$key]=(int)$row[$key];
        unset($row);
    }
    $report=felixSyncRosters($db); $db->commit();
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $e) {
    if(isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR,'CSV synchronization failed: '.$e->getMessage()."\n"); exit(1);
}
