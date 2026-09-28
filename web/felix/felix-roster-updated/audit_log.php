<?php
// Server-side CSV audit trail. Call only after authenticating and validating input.
function saveRosterWithAudit($dataDir, $filename, $session, array $rows, $user, $action) {
    $columns = ['id', 'name', 'allergy', 'cls', 'time', 'arrived', 'late', 'cancelled', 'reason', 'notes', 'checkedOut', 'flag'];
    $booleans = ['arrived', 'late', 'cancelled', 'checkedOut'];
    $csvPath = $dataDir . '/' . $filename;
    $metaPath = $dataDir . '/meta.json';
    $logDir = dirname($dataDir) . '/logs';
    $lock = fopen($dataDir . '/.save.lock', 'c');
    if (!$lock) throw new RuntimeException('Could not open save lock');
    $log = null;
    $generalLog = null;
    $csvTemp = null;
    $metaTemp = null;
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock saves');
        $oldCsv = file_get_contents($csvPath);
        $oldMeta = is_file($metaPath) ? file_get_contents($metaPath) : null;
        if ($oldCsv === false || $oldMeta === false) throw new RuntimeException('Could not read existing data');
        $meta = $oldMeta !== null ? json_decode($oldMeta, true) : [];
        if (!is_array($meta)) throw new RuntimeException('Invalid session metadata');
        $oldSession = $meta[$filename]['session'] ?? 'checkin';
        $before = [];
        $input = fopen($csvPath, 'r');
        if (!$input) throw new RuntimeException('Could not read roster');
        fgetcsv($input, 0, ',', '"', '');
        while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
            if (!isset($row[0]) || $row[0] === '') continue;
            $person = [];
            foreach ($columns as $i => $column) $person[$column] = $row[$i] ?? '';
            foreach ($booleans as $column) $person[$column] = $person[$column] === '1' ? '1' : '0';
            $before[$person['id']] = $person;
        }
        fclose($input);
        $after = [];
        foreach ($rows as $row) {
            $person = [];
            foreach ($columns as $column) {
                $person[$column] = in_array($column, $booleans, true)
                    ? (!empty($row[$column]) ? '1' : '0') : (string) ($row[$column] ?? '');
            }
            $after[$person['id']] = $person;
        }
        $changes = [];
        if ($oldSession !== $session) $changes[] = ['', '', 'session', $oldSession, $session];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $id) {
            $old = $before[$id] ?? null;
            $new = $after[$id] ?? null;
            $name = $new['name'] ?? $old['name'];
            if ($old === null || $new === null) {
                $changes[] = [(string) $id, $name, $old === null ? 'person_added' : 'person_removed',
                    $old === null ? '' : json_encode($old, JSON_UNESCAPED_UNICODE),
                    $new === null ? '' : json_encode($new, JSON_UNESCAPED_UNICODE)];
            } else {
                foreach ($columns as $column) {
                    if ($old[$column] !== $new[$column]) $changes[] = [(string) $id, $name, $column, $old[$column], $new[$column]];
                }
            }
        }
        if (!$changes) $changes[] = ['', '', 'no_change', '', ''];
        if (!is_dir($logDir) && !mkdir($logDir, 0750, true) && !is_dir($logDir)) {
            throw new RuntimeException('Could not create audit directory');
        }
        $logPath = $logDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '-log.csv';
        $log = fopen($logPath, 'c+');
        if (!$log || !flock($log, LOCK_EX)) throw new RuntimeException('Could not open audit log');
        fseek($log, 0, SEEK_END);
        $logSize = ftell($log);
        $generalLog = fopen($logDir . '/general-log.csv', 'c+');
        if (!$generalLog || !flock($generalLog, LOCK_EX)) throw new RuntimeException('Could not open general audit log');
        fseek($generalLog, 0, SEEK_END);
        $generalLogSize = ftell($generalLog);
        $event = bin2hex(random_bytes(16));
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d\TH:i:s.uP');
        $csvTemp = tempnam($dataDir, '.roster-');
        $metaTemp = tempnam($dataDir, '.meta-');
        if (!$csvTemp || !$metaTemp) throw new RuntimeException('Could not stage roster');
        $output = fopen($csvTemp, 'w');
        if (!$output) throw new RuntimeException('Could not write roster');
        try {
            if (fputcsv($output, $columns, ',', '"', '') === false) throw new RuntimeException('Could not write roster header');
            foreach ($after as $person) {
                if (fputcsv($output, array_values($person), ',', '"', '') === false) throw new RuntimeException('Could not write roster row');
            }
            if (!fflush($output)) throw new RuntimeException('Could not flush roster');
        } finally {
            fclose($output);
        }
        $meta[$filename] = ['session' => $session];
        if (file_put_contents($metaTemp, json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Could not write session metadata');
        }
        // Roll back on reported I/O failures; files are not a crash-safe database.
        $csvWritten = false;
        $metaWritten = false;
        try {
            if ($logSize === 0 && fputcsv($log, ['event_id', 'timestamp', 'user', 'project', 'action', 'person_id', 'person_name', 'field', 'old_value', 'new_value'], ',', '"', '') === false) {
                throw new RuntimeException('Could not write audit header');
            }
            if ($generalLogSize === 0 && fputcsv($generalLog, ['event_id', 'timestamp', 'user', 'project', 'action', 'person_id', 'person_name', 'field', 'old_value', 'new_value'], ',', '"', '') === false) {
                throw new RuntimeException('Could not write general audit header');
            }
            foreach ($changes as $change) {
                $entry = array_merge([$event, $timestamp, $user, $filename, $action], $change);
                // Prevent user-entered text from becoming a spreadsheet formula.
                $entry = array_map(function ($value) {
                    return preg_match('/^[=+@\-\t\r\n]/', $value) ? "'" . $value : $value;
                }, $entry);
                if (fputcsv($log, $entry, ',', '"', '') === false) throw new RuntimeException('Could not append audit event');
                if (fputcsv($generalLog, $entry, ',', '"', '') === false) throw new RuntimeException('Could not append general audit event');
            }
            if (!fflush($log)) throw new RuntimeException('Could not flush audit log');
            if (!fflush($generalLog)) throw new RuntimeException('Could not flush general audit log');
            if (!rename($csvTemp, $csvPath)) throw new RuntimeException('Could not replace roster');
            $csvWritten = true;
            if (!rename($metaTemp, $metaPath)) throw new RuntimeException('Could not replace session metadata');
            $metaWritten = true;
        } catch (Throwable $error) {
            if ($csvWritten) file_put_contents($csvPath, $oldCsv);
            if ($metaWritten) {
                if ($oldMeta === null) unlink($metaPath);
                else file_put_contents($metaPath, $oldMeta);
            }
            ftruncate($log, $logSize);
            fflush($log);
            ftruncate($generalLog, $generalLogSize);
            fflush($generalLog);
            throw $error;
        }
    } finally {
        foreach ([$csvTemp, $metaTemp] as $temporary) {
            if ($temporary && is_file($temporary)) unlink($temporary);
        }
        if (is_resource($log)) { flock($log, LOCK_UN); fclose($log); }
        if (is_resource($generalLog)) { flock($generalLog, LOCK_UN); fclose($generalLog); }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
