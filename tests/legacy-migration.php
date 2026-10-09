<?php
declare(strict_types=1);
require __DIR__.'/../backend/maintenance/legacy-migration.php';
function check(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);}
$pdo=new PDO('mysql:host=127.0.0.1;dbname=journal_test;charset=utf8mb4','root','',[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo->exec((string)file_get_contents(__DIR__.'/../backend/schema.sql'));
$items=legacy_manifest();
check(count($items)===51,'manifest count');
check(legacy_metadata($pdo,$items)['inserted']===51,'first metadata');
check(legacy_metadata($pdo,$items)['inserted']===0,'idempotent metadata');
check((int)$pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn()===51,'no duplicates');
$i=array_values(array_filter($items,static fn($i)=>$i['migration_status']==='ready'))[0];
$pdo->prepare("UPDATE issues SET migration_status='imported',pdf_path='/keep.pdf' WHERE legacy_key=?")->execute([$i['legacy_key']]);
legacy_metadata($pdo,$items);
check(legacy_row($pdo,$i['legacy_key'])['pdf_path']==='/keep.pdf','imported preserved');
check(legacy_row($pdo,$i['legacy_key'])['migration_status']==='imported','status preserved');
$pdo->exec('DELETE FROM issues');
$pdo->prepare("INSERT INTO issues (year,series,number,title) VALUES (?,?,?,'Native title')")->execute([$i['year'],$i['series'],$i['number']]);
$r=legacy_metadata($pdo,$items);
check($r['inserted']===50&&count($r['conflicts'])===1,'native collision reported');
$native=$pdo->query("SELECT * FROM issues WHERE migration_status='native'")->fetch();
check($native['title']==='Native title'&&$native['legacy_key']===null,'native preserved');
check(legacy_metadata($pdo,$items)['inserted']===0,'collision repeat');
// Test resumed local-file adoption, one-file bound, and failed retry without network.
$ready=array_values(array_filter($items,static fn($i)=>$i['migration_status']==='ready'));
$fixtures=[$ready[1],$ready[2]];$dir=sys_get_temp_dir().'/legacy-test-'.bin2hex(random_bytes(6));
foreach($fixtures as &$f){$raw='%PDF-test-'.$f['legacy_key'];$f['size_bytes']=strlen($raw);$f['sha256']=hash('sha256',$raw);@mkdir($dir.'/'.$f['year'],0750,true);file_put_contents($dir.'/'.$f['year'].'/'.$f['local_filename'],$raw);}unset($f);
$r=legacy_batch($pdo,$fixtures,$dir);
check(count($r['imported'])===1,'one PDF per request');
check(legacy_row($pdo,$fixtures[1]['legacy_key'])['migration_status']==='ready','second queued');
$pdo->prepare("UPDATE issues SET migration_status='failed' WHERE legacy_key=?")->execute([$fixtures[1]['legacy_key']]);
$r=legacy_batch($pdo,$fixtures,$dir);check(count($r['imported'])===1,'failed resumed');
check(legacy_batch($pdo,$fixtures,$dir)['imported']===[],'imported skipped');
$file=$dir.'/'.$fixtures[0]['year'].'/'.$fixtures[0]['local_filename'];file_put_contents($file,'<html>bad');check(!legacy_valid_pdf($file,$fixtures[0]),'invalid rejected');
foreach(['https://evil.example/a.pdf','https://chspu.ru.evil.example/a.pdf','http://chspu.ru/a.pdf','https://user@chspu.ru/a.pdf','https://chspu.ru:444/a.pdf'] as $url){$rejected=false;try{legacy_url($url);}catch(RuntimeException $e){$rejected=true;}check($rejected,'URL rejected');}
check(legacy_status($pdo,$items)['counts']['needs_recovery']===11,'recovery untouched');
check(legacy_status($pdo,$items)['counts']['duplicate_conflict']===9,'duplicates untouched');
foreach($fixtures as $f)unlink($dir.'/'.$f['year'].'/'.$f['local_filename']);foreach(array_unique(array_column($fixtures,'year')) as $year)rmdir($dir.'/'.$year);rmdir($dir);
echo "Legacy migration tests passed\n";
