<?php
/** Explicit, CLI-only canonical demo replacement. No authentication secrets needed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 2 || $argv[1] !== '--reset') {
    fwrite(STDERR, "Usage: php demo_reset.php --reset\nReplaces active dated rosters and assignments after a private backup.\n");
    exit(2);
}
require_once __DIR__ . '/csv_sync.php';
require_once __DIR__ . '/audit_log.php';

/** Back up regular files only; refusing symlinks avoids copying outside scope. */
function felixDemoCopy(string $from, string $to): void {
    if (is_link($from)) throw new RuntimeException('Symlink in backup scope');
    if (is_dir($from)) {
        if (!mkdir($to, 0750, true)) throw new RuntimeException('Cannot create backup directory');
        foreach (new DirectoryIterator($from) as $entry) {
            if ($entry->isDot() || $entry->getFilename() === '.save.lock') continue;
            felixDemoCopy($entry->getPathname(), $to . '/' . $entry->getFilename());
        }
    } else {
        if (!is_file($from) || !copy($from, $to) || !chmod($to, 0640)
            || hash_file('sha256', $from) !== hash_file('sha256', $to)) throw new RuntimeException('Backup verification failed');
    }
}

$lock = null; $db = null; $backup = null; $snapshot = []; $destructive = false;
try {
    $data = realpath(felixDataDir());
    $registry = realpath(getenv('FELIX_CSV_DIR') ?: dirname(__DIR__, 2) . '/private/felix-csv');
    if (!$data || !$registry) throw new RuntimeException('Existing data and canonical registry required');
    $backupRoot = getenv('FELIX_DEMO_BACKUP_DIR') ?: dirname(__DIR__, 2) . '/private/felix-backups';
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true)) throw new RuntimeException('Cannot create backup root');
    $backupRoot = realpath($backupRoot);
    $webroot = realpath(dirname(__DIR__));
    foreach ([$webroot, $data, $registry] as $excluded) {
        if ($backupRoot === $excluded || str_starts_with($backupRoot, $excluded . '/')) throw new RuntimeException('Backup must be outside webroot and active storage');
    }
    $lock = fopen($data . '/.save.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock reset');
    $db = felixAssignmentsDb();
    $db->beginTransaction(true);
    if (!$db->people) throw new RuntimeException('Canonical registry is empty; run additive setup first');
    $originalPeople = $db->people;
    $dates = ['2026-10-05', '2026-10-06', '2026-10-07'];
    $active = array_values(array_filter(glob($data . '/*.csv') ?: [], fn($p) => preg_match('/^\d{4}-\d{2}-\d{2}-.+\.csv$/D', basename($p))));
    $logs = dirname($data) . '/logs';
    $backup = $backupRoot . '/demo-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup, 0750)) throw new RuntimeException('Cannot create backup');
    felixDemoCopy($data, $backup . '/data');
    felixDemoCopy($registry, $backup . '/registry');
    if (is_dir($logs)) felixDemoCopy($logs, $backup . '/logs');
    $targets = array_merge($active, [$data . '/meta.json', $registry . '/CURRENT', $logs . '/general-log.csv']);
    foreach ($dates as $date) {
        $targets[] = $data . '/' . $date . '-forgatas.csv';
        $targets[] = $logs . '/' . $date . '-forgatas-log.csv';
    }
    foreach (array_unique($targets) as $path) {
        if (is_link($path) || (file_exists($path) && !is_file($path))) throw new RuntimeException('Invalid reset target');
        $snapshot[$path] = is_file($path) ? file_get_contents($path) : null;
        if ($snapshot[$path] === false) throw new RuntimeException('Cannot snapshot reset target');
    }
    $manifest = ['created_at'=>date('c'), 'data'=>$data, 'registry'=>$registry, 'old_people'=>count($db->people),
        'old_days'=>count($db->days), 'old_assignments'=>count($db->assignments), 'removed_active_files'=>array_map('basename',$active), 'dates'=>$dates];
    if (file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot write manifest');
    chmod($backup . '/manifest.json', 0640);
    $destructive = true;
    foreach ($active as $path) if (!unlink($path)) throw new RuntimeException('Cannot remove old active roster');
    $meta = isset($snapshot[$data . '/meta.json']) ? json_decode($snapshot[$data . '/meta.json'], true, 512, JSON_THROW_ON_ERROR) : [];
    foreach ($active as $path) unset($meta[basename($path)]);
    foreach ($dates as $date) unset($meta[$date . '-forgatas.csv']);
    if (file_put_contents($data . '/meta.json', json_encode($meta, JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot reset metadata');
    $db->days = []; $db->assignments = []; $db->sources = [];
    $people = $db->people;
    usort($people, fn($a,$b) => $a['id'] <=> $b['id']);
    $counts = [];
    foreach ($dates as $dayIndex => $date) {
        $day = $db->ensureDay($date, 'Demó forgatás');
        $rows = [];
        foreach ($people as $index => $person) {
            // Each ID appears on two days; equal names never merge.
            if ($index % 3 === $dayIndex) continue;
            $slot = count($rows);
            $cancelled = $slot % 6 === 0;
            $arrived = !$cancelled && ($dayIndex > 0 || $slot % 3 !== 2);
            $checkedOut = $arrived && ($dayIndex === 2 || ($dayIndex === 1 && $slot % 2 === 0));
            $diet = [['',''], ['Vegan','vegan'], ['Gluten','gluten']][$slot % 3];
            $rows[] = ['id'=>(string)$person['id'], 'name'=>$person['name'], 'allergy'=>$diet[0], 'cls'=>$diet[1],
                'time'=>$arrived ? '08:15' : '', 'arrived'=>$arrived, 'late'=>$arrived && $slot % 4 === 1,
                'cancelled'=>$cancelled, 'reason'=>$cancelled ? 'Demó: előre jelzett lemondás' : '',
                'notes'=>$slot % 3 === 1 ? 'Demó: jelmezpróba a koordinátorral' : '',
                'checkedOut'=>$checkedOut, 'flag'=>['','PRIO','DATA ERROR','PROB'][$slot % 4]];
            $db->assign($day['id'], $person['id'], 'demo-reset');
        }
        $filename = $date . '-forgatas.csv';
        saveRosterWithAudit($data, $filename, $dayIndex === 0 ? 'checkin' : 'checkout', $rows, 'demo-reset', 'assignment', [
            'lock'=>$lock, 'create'=>true, 'metadata'=>['source'=>'assignments', 'assignment_date'=>$date, 'closed'=>$dayIndex === 2, 'demo'=>true],
        ]);
        $counts[$date] = count($rows);
    }
    // Stable provenance as well as stable day/person pairs on repeated resets.
    foreach ($db->assignments as &$assignment) $assignment['created_at'] = '2026-10-04T12:00:00+02:00';
    unset($assignment);
    $sync = felixSyncRosters($db);
    if ($db->people !== $originalPeople || count($db->assignments) !== count($people) * 2 || count($db->days) !== 3) throw new RuntimeException('Canonical demo validation failed');
    foreach ($db->sources as $source) if ((string)$source['person_id'] !== $source['legacy_id']) throw new RuntimeException('Noncanonical demo source');
    $db->commit();
    $db->reload();
    if ($db->people !== $originalPeople || count($db->sources) !== array_sum($counts)) throw new RuntimeException('Readback failed');
    echo json_encode(['ok'=>true, 'backup'=>$backup, 'dates'=>$counts, 'people'=>count($db->people), 'assignments'=>count($db->assignments),
        'source_mappings'=>count($db->sources), 'removed_active_rosters'=>count($active), 'sync'=>$sync], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    if ($db && $db->inTransaction()) $db->rollBack();
    $failed = [];
    if ($destructive) foreach ($snapshot as $path=>$contents) {
        if ($contents === null) { if (is_file($path) && !unlink($path)) $failed[]=$path; }
        elseif (file_put_contents($path,$contents) === false) $failed[]=$path;
    }
    fwrite(STDERR, $error->getMessage() . ($backup ? "\nBackup: " . $backup : '') . ($failed ? "\nRESTORE FAILED: " . implode(', ', $failed) : '') . "\n");
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
