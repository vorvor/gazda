<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments_db.php';

// Scans data/ for files named YYYY-MM-DD-projectname.csv and returns
// them sorted by date, along with each file's saved session state
// (checkin/checkout) from meta.json.

header('Content-Type: application/json; charset=utf-8');

$dataDir  = felixDataDir();
$metaPath = $dataDir . '/meta.json';

$meta = [];
if (is_file($metaPath)) {
    $decoded = json_decode(file_get_contents($metaPath), true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}

$files = [];
foreach (glob($dataDir . '/*.csv') as $path) {
    $filename = basename($path);

    // Expect: YYYY-MM-DD-projectname.csv
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)\.csv$/', $filename, $m)) {
        continue; // skip anything that doesn't match the naming convention
    }

    $date = $m[1];
    $slug = $m[2];

    // Prettify the project slug: "project1" -> "Project1", "site-b" -> "Site B"
    $label = str_replace(['-', '_'], ' ', $slug);
    $label = ucwords($label);

    $dateObj  = DateTime::createFromFormat('Y-m-d', $date);
    $dateText = $dateObj ? $dateObj->format('Y. m. d.') : $date;

    $files[] = [
        'file'    => $filename,
        'date'    => $date,
        'label'   => $dateText . ' · ' . $label,
        'session' => isset($meta[$filename]['session']) ? $meta[$filename]['session'] : 'checkin',
        'closed'  => !empty($meta[$filename]['closed']),
    ];
    if (($meta[$filename]['source'] ?? '') === 'assignments') {
        $files[count($files) - 1]['source'] = 'assignments';
        $files[count($files) - 1]['assignment_date'] = $meta[$filename]['assignment_date'] ?? $date;
    }
}

usort($files, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

echo json_encode(array_values($files));
