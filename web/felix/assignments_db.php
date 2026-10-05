<?php
// Compatibility filename only: all runtime storage is CSV, never SQL.
function felixDataDir(): string { return getenv('FELIX_DATA_DIR') ?: __DIR__ . '/data'; }
function felixValidFilmingDate($date): bool {
    return is_string($date) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
        && checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4));
}
function felixReadCsv(string $path): array {
    $f = fopen($path, 'r'); if (!$f) throw new RuntimeException('Cannot read CSV');
    try {
        $header = fgetcsv($f, 0, ',', '"', ''); if (!$header) throw new RuntimeException('Missing CSV header');
        $rows = [];
        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($row === [null]) continue;
            if (count($row) !== count($header)) throw new RuntimeException('Invalid CSV width');
            $rows[] = array_combine($header, $row);
        }
        return $rows;
    } finally { fclose($f); }
}
final class FelixCsvStore {
    public array $people = [], $days = [], $assignments = [], $sources = [];
    private string $root;
    private bool $transaction = false;
    private $lock = null;
    private const SCHEMA = [
        'people'=>['id','demo_key','name','city','fictional'],
        'days'=>['id','date','title'],
        'assignments'=>['day_id','person_id','assigned_by','created_at'],
        'sources'=>['file','legacy_id','name','person_id','identity_key','ambiguity'],
    ];
    public function __construct() {
        $this->root = getenv('FELIX_CSV_DIR') ?: dirname(__DIR__,2) . '/private/felix-csv';
        if (!is_dir($this->root) && !mkdir($this->root,0750,true)) throw new RuntimeException('Cannot create CSV storage');
        $this->reload();
    }
    public function generationPath(): string {
        $pointer = $this->root . '/CURRENT';
        if (!is_file($pointer)) return '';
        $name = trim(file_get_contents($pointer));
        if (!preg_match('/^generation-[a-f0-9]+$/D',$name)) throw new RuntimeException('Invalid generation');
        return $this->root . '/' . $name;
    }
    public function reload(): void {
        $path = $this->generationPath();
        foreach (self::SCHEMA as $table=>$columns) {
            $this->$table = $path ? felixReadCsv($path . '/' . $table . '.csv') : [];
            foreach ($this->$table as &$row) foreach (['id','day_id','person_id','fictional'] as $key) if (isset($row[$key])) $row[$key] = (int)$row[$key];
            unset($row);
        }
    }
    public function beginTransaction(bool $alreadyLocked = false): void {
        if ($this->transaction) throw new RuntimeException('Nested transaction');
        if (!$alreadyLocked) {
            $dir = felixDataDir(); if (!is_dir($dir)) mkdir($dir,0750,true);
            $this->lock = fopen($dir . '/.save.lock','c');
            if (!$this->lock || !flock($this->lock,LOCK_EX)) throw new RuntimeException('Cannot lock CSV storage');
        }
        $this->reload(); $this->transaction = true;
    }
    public function inTransaction(): bool { return $this->transaction; }
    private function release(): void {
        $this->transaction=false;
        if ($this->lock) { flock($this->lock,LOCK_UN); fclose($this->lock); $this->lock=null; }
    }
    public function rollBack(): void { $this->reload(); $this->release(); }
    public function commit(): void {
        if (!$this->transaction) throw new RuntimeException('Transaction required');
        $people=[]; $days=[]; $dates=[]; $links=[];
        foreach ($this->people as $p) { if (isset($people[$p['id']]) || $p['id'] < 1) throw new RuntimeException('Duplicate person'); $people[$p['id']]=true; }
        foreach ($this->days as $d) { if (isset($days[$d['id']]) || isset($dates[$d['date']]) || !felixValidFilmingDate($d['date'])) throw new RuntimeException('Invalid day'); $days[$d['id']]=true; $dates[$d['date']]=true; }
        foreach ($this->assignments as $a) {
            $key=$a['day_id'].':'.$a['person_id'];
            if (!isset($people[$a['person_id']],$days[$a['day_id']]) || isset($links[$key])) throw new RuntimeException('Invalid assignment');
            $links[$key]=true;
        }
        foreach ($this->sources as $s) if (!isset($people[$s['person_id']])) throw new RuntimeException('Invalid source reference');
        $name='generation-'.bin2hex(random_bytes(12)); $dir=$this->root.'/'.$name;
        if (!mkdir($dir,0750)) throw new RuntimeException('Cannot stage CSV generation');
        foreach (self::SCHEMA as $table=>$columns) {
            $f=fopen($dir.'/'.$table.'.csv','x'); if (!$f) throw new RuntimeException('Cannot write CSV');
            try {
                if (fputcsv($f,$columns,',','"','') === false) throw new RuntimeException('CSV write failed');
                foreach ($this->$table as $row) if (fputcsv($f,array_map(fn($key)=>$row[$key] ?? '',$columns),',','"','') === false) throw new RuntimeException('CSV write failed');
                if (!fflush($f) || !fsync($f)) throw new RuntimeException('CSV flush failed');
            } finally { fclose($f); }
            chmod($dir.'/'.$table.'.csv',0640);
        }
        $tmp=tempnam($this->root,'.current-');
        if ($tmp === false || file_put_contents($tmp,$name."\n") === false || !chmod($tmp,0640) || !rename($tmp,$this->root.'/CURRENT')) throw new RuntimeException('Cannot publish CSV generation');
        $this->release();
    }
    public function ensureDay(string $date, string $title): array {
        if (!felixValidFilmingDate($date)) throw new InvalidArgumentException('Invalid date');
        foreach ($this->days as $day) if ($day['date']===$date) return $day;
        $day=['id'=>$this->nextId($this->days),'date'=>$date,'title'=>$title]; $this->days[]=$day; return $day;
    }
    public function nextId(array $rows): int { return $rows ? max(array_column($rows,'id'))+1 : 1; }
    public function person($id): ?array { foreach ($this->people as $p) if ((string)$p['id']===(string)$id) return $p; return null; }
    public function assign($dayId,$personId,string $user): void {
        if (!$this->person($personId) || !in_array($dayId,array_column($this->days,'id'))) throw new InvalidArgumentException('Unknown reference');
        foreach ($this->assignments as $a) if ($a['day_id']==$dayId && $a['person_id']==$personId) return;
        $this->assignments[]=['day_id'=>(int)$dayId,'person_id'=>(int)$personId,'assigned_by'=>$user,'created_at'=>date('c')];
    }
    public function remove($dayId,$personId): void { $this->assignments=array_values(array_filter($this->assignments,fn($a)=>!($a['day_id']==$dayId && $a['person_id']==$personId))); }
    public function search(string $q): array {
        $out=array_filter($this->people,fn($p)=>$q==='' || mb_stripos($p['name'],$q)!==false || mb_stripos($p['city'],$q)!==false);
        usort($out,fn($a,$b)=>strcmp($a['name'],$b['name']));
        return array_map(fn($p)=>array_intersect_key($p,array_flip(['id','name','city','fictional'])),$out);
    }
    public function dayList(): array { $days=$this->days; usort($days,fn($a,$b)=>strcmp($a['date'],$b['date'])); return array_map(fn($d)=>['date'=>$d['date'],'title'=>$d['title']],$days); }
}
function felixAssignmentsDb(): FelixCsvStore { return new FelixCsvStore(); }
function felixDefaultFilmingDate(FelixCsvStore $db): string { return $db->dayList()[0]['date'] ?? (new DateTimeImmutable('now',new DateTimeZone('Europe/Budapest')))->format('Y-m-d'); }
function felixFilmingDay(FelixCsvStore $db,string $date): array { foreach($db->days as $day) if($day['date']===$date) return $day; return ['id'=>null,'date'=>$date,'title'=>'Forgatási nap']; }
function felixFilmingAssigned(FelixCsvStore $db,$dayId): array {
    $ids=[]; foreach($db->assignments as $a) if($a['day_id']===$dayId) $ids[$a['person_id']]=true;
    return array_values(array_filter($db->search(''),fn($p)=>isset($ids[$p['id']])));
}
