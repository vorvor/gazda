<?php
require __DIR__ . '/../assignments_db.php';
function check($value, $message) { if (!$value) throw new RuntimeException($message); }
check(class_exists('FelixCsvStore'), 'CSV-only store is missing');
$root = sys_get_temp_dir() . '/felix-csv-' . bin2hex(random_bytes(8));
mkdir($root); mkdir($root . '/data');
putenv('FELIX_CSV_DIR=' . $root . '/registry'); putenv('FELIX_DATA_DIR=' . $root . '/data');
$db = felixAssignmentsDb();
$db->beginTransaction();
$db->people = [['id'=>1,'demo_key'=>'test-1','name'=>'Same Name','city'=>'','fictional'=>0], ['id'=>2,'demo_key'=>'test-2','name'=>'Same Name','city'=>'','fictional'=>0]];
$day = $db->ensureDay('2028-02-29', 'Test');
$db->assign($day['id'], 1, 'test'); $db->assign($day['id'], 1, 'test'); $db->assign($day['id'], 2, 'test');
$db->commit();
$read = felixAssignmentsDb();
check(count($read->people) === 2, 'same names must remain separate');
check(count(felixFilmingAssigned($read, $day['id'])) === 2, 'unique assignments persisted');
check(is_file($read->generationPath() . '/people.csv'), 'real CSV required');
try { $read->assign($day['id'], 99, 'test'); throw new LogicException('orphan accepted'); } catch (InvalidArgumentException $expected) {}
check((fileperms($root . '/registry/CURRENT') & 0777) === 0640, 'Pointer must be group-readable for PHP service');
echo "PASS CSV persistence, duplicate prevention, identity and references\n";
