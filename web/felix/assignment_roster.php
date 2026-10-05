<?php
require_once __DIR__ . '/audit_log.php';

/** Call with a transaction callback; all assignment writers share the CSV lock.
 * Files are restored on reported failures, including commit errors. This is not
 * crash-atomic across the registry, roster and audit files.
 */
function felixAssignmentRosterTransaction(FelixCsvStore $db, string $date, string $user, callable $mutate): ?string {
    $dataDir = felixDataDir();
    if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true) && !is_dir($dataDir)) throw new RuntimeException('Cannot create data directory');
    $lock = fopen($dataDir . '/.save.lock', 'c');
    if (!$lock) throw new RuntimeException('Cannot open save lock');
    $snapshot = [];
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock saves');
        $filename = $date . '-forgatas.csv';
        $paths = [$dataDir . '/' . $filename, $dataDir . '/meta.json', dirname($dataDir) . '/logs/' . $date . '-forgatas-log.csv', dirname($dataDir) . '/logs/general-log.csv'];
        foreach ($paths as $path) {
            if (is_link($path) || (file_exists($path) && !is_file($path))) throw new RuntimeException('Invalid storage target');
            $contents = is_file($path) ? file_get_contents($path) : null;
            if ($contents === false) throw new RuntimeException('Cannot snapshot storage');
            $snapshot[$path] = $contents;
        }
        $db->beginTransaction(true);
        $mutate($db);
        $day = felixFilmingDay($db, $date);
        $people = felixFilmingAssigned($db, $day['id']);
        $path = $dataDir . '/' . $filename;
        $meta = $snapshot[$dataDir . '/meta.json'] === null ? [] : json_decode($snapshot[$dataDir . '/meta.json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($meta)) throw new RuntimeException('Invalid metadata');
        $entry = $meta[$filename] ?? [];
        $exists = is_file($path);
        if ($exists && (($entry['source'] ?? '') !== 'assignments' || ($entry['assignment_date'] ?? '') !== $date)) {
            throw new RuntimeException('A fájlnév már kézi névsorhoz tartozik.', 409);
        }
        $before = [];
        if ($exists) {
            $input = fopen($path, 'r');
            if (!$input) throw new RuntimeException('Cannot read roster');
            try {
                $columns = fgetcsv($input, 0, ',', '"', '');
                if (!is_array($columns) || array_slice($columns, 0, 2) !== ['id', 'name']) throw new RuntimeException('Invalid roster header');
                while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                    if (!isset($row[0]) || $row[0] === '') continue;
                    if (isset($before[$row[0]])) throw new RuntimeException('Duplicate roster ID');
                    $person = [];
                    foreach ($columns as $i => $column) $person[$column] = $row[$i] ?? '';
                    $before[$row[0]] = $person;
                }
            } finally { fclose($input); }
        }
        $after = [];
        foreach ($people as $person) {
            $id = (string)$person['id'];
            // Re-adding a removed person deliberately starts fresh; audit remains.
            $after[$id] = $before[$id] ?? ['id' => $id, 'name' => $person['name']];
        }
        $changed = array_diff_key($before, $after) || array_diff_key($after, $before);
        if ($changed || (!$exists && $people)) {
            saveRosterWithAudit($dataDir, $filename, $entry['session'] ?? 'checkin', array_values($after), $user, 'assignment', [
                'lock' => $lock, 'create' => !$exists,
                'metadata' => ['source' => 'assignments', 'assignment_date' => $date],
            ]);
        }
        $db->commit();
        return $exists || $people ? $filename : null;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        $failed = false;
        foreach ($snapshot as $path => $contents) {
            if ($contents === null) {
                if (is_file($path) && !unlink($path)) $failed = true;
            } elseif (!is_file($path) || file_get_contents($path) !== $contents) {
                if (file_put_contents($path, $contents) === false) $failed = true;
            }
        }
        if ($failed) throw new RuntimeException('Assignment rollback could not restore storage', 0, $error);
        throw $error;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
