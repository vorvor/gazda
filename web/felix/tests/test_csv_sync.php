<?php
require __DIR__ . '/../assignments_db.php';
require __DIR__ . '/../csv_sync.php';
$root=sys_get_temp_dir().'/felix-sync-'.bin2hex(random_bytes(6)); mkdir($root); mkdir($root.'/data');
putenv('FELIX_DATA_DIR='.$root.'/data'); putenv('FELIX_CSV_DIR='.$root.'/registry');
file_put_contents($root.'/data/2026-01-01-a.csv',"id,name\n1,Same\n2,Same\n3,Original\n");
file_put_contents($root.'/data/2026-01-02-b.csv',"id,name\n1,Same\n3,Collision\n");
$db=felixAssignmentsDb(); $db->beginTransaction(); $report=felixSyncRosters($db); $db->commit();
if(count($db->people)!==4 || count($db->sources)!==5 || count($db->assignments)!==5) throw new RuntimeException('Identity/coverage failure');
$before=[$db->people,$db->sources,$db->assignments];
$db->beginTransaction(); felixSyncRosters($db); $db->commit();
if($before!==[$db->people,$db->sources,$db->assignments]) throw new RuntimeException('Unstable rerun');
if(count($report['ambiguities'])!==1) throw new RuntimeException('Missing collision report');
echo "PASS all rosters, duplicate names, ID collision, stable rerun\n";
