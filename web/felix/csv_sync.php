<?php
require_once __DIR__ . '/assignments_db.php';
/** Additive import; retain original roster IDs/state and all historical links. */
function felixSyncRosters(FelixCsvStore $db): array {
    $dir=felixDataDir(); $meta=is_file($dir.'/meta.json') ? json_decode(file_get_contents($dir.'/meta.json'),true,512,JSON_THROW_ON_ERROR) : [];
    $files=glob($dir.'/*.csv') ?: []; sort($files); $report=['files'=>0,'rows'=>0,'ambiguities'=>[]];
    $identities=[]; $names=[]; $mapped=[];
    foreach($db->sources as $s) { $identities[$s['identity_key']]=$s['person_id']; $names[$s['legacy_id']][$s['name']]=true; $mapped[$s['file'].'\0'.$s['legacy_id'].'\0'.$s['name']]=true; }
    foreach($files as $file) {
        $base=basename($file);
        if(!preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)\.csv$/D',$base,$m) || !felixValidFilmingDate($m[1])) continue;
        $report['files']++; $day=$db->ensureDay($m[1],ucwords(str_replace(['-','_'],' ',$m[2])));
        $seen=[];
        foreach(felixReadCsv($file) as $row) {
            $id=(string)($row['id'] ?? ''); $name=(string)($row['name'] ?? '');
            if($id==='' || $name==='') throw new RuntimeException('Roster lacks identity: '.$base);
            if(isset($seen[$id])) throw new RuntimeException('Duplicate ID within roster: '.$base);
            $seen[$id]=true; $report['rows']++;
            $generated=($meta[$base]['source'] ?? '')==='assignments';
            $key=($generated?'assignment:':'legacy:').json_encode([$id,$name],JSON_UNESCAPED_UNICODE);
            $ambiguity=''; $personId=$identities[$key] ?? null;
            if($personId===null) {
                // Generated IDs refer to the exported registry, but a conflicting name
                // is not silently allowed to overwrite or merge another identity.
                $candidate=$generated ? $db->person($id) : null;
                if(!$generated) foreach($db->people as $p) if($p['demo_key']==='csv-id:'.$id && $p['name']===$name) { $candidate=$p; break; }
                if($candidate && $candidate['name']===$name) $personId=$candidate['id'];
                else {
                    if(($candidate && $candidate['name']!==$name) || (isset($names[$id]) && !isset($names[$id][$name]))) $ambiguity='Reused legacy ID with different name; separate provisional identity';
                    $personId=$db->nextId($db->people);
                    $db->people[]=['id'=>$personId,'demo_key'=>'roster:'.hash('sha256',$key),'name'=>$name,'city'=>'','fictional'=>0];
                }
                $identities[$key]=$personId;
            }
            $sourceKey=$base.'\0'.$id.'\0'.$name;
            if(!isset($mapped[$sourceKey])) {
                $db->sources[]=['file'=>$base,'legacy_id'=>$id,'name'=>$name,'person_id'=>$personId,'identity_key'=>$key,'ambiguity'=>$ambiguity];
                $mapped[$sourceKey]=true;
            }
            $names[$id][$name]=true;
            $db->assign($day['id'],$personId,'csv-sync');
        }
    }
    foreach($db->sources as $s) if($s['ambiguity']!=='') $report['ambiguities'][]=$s;
    $report['people']=count($db->people); $report['days']=count($db->days); $report['assignments']=count($db->assignments); $report['source_mappings']=count($db->sources);
    return $report;
}
