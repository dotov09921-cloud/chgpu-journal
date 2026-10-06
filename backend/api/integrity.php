<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin']);

$rows=db()->query('SELECT id,submission_id,original_name,relative_path,size_bytes,sha256 FROM submission_files ORDER BY id')->fetchAll();
$base=realpath($config['app']['upload_dir']);
$total=count($rows);$ok=0;$missing=[];$mismatch=[];

foreach($rows as $f){
  $path=realpath(rtrim($config['app']['upload_dir'],'/\\').'/'.ltrim($f['relative_path'],'/\\'));
  if(!$base||!$path||strpos($path,$base)!==0||!is_file($path)){
    $missing[]=['id'=>$f['id'],'submission_id'=>$f['submission_id'],'name'=>$f['original_name']];
    continue;
  }
  $hash=hash_file('sha256',$path);
  $size=filesize($path);
  if(!hash_equals((string)$f['sha256'],$hash)||(int)$f['size_bytes']!==$size){
    $mismatch[]=['id'=>$f['id'],'submission_id'=>$f['submission_id'],'name'=>$f['original_name']];
    continue;
  }
  $ok++;
}

audit_event((int)require_editor()['id'],'integrity_check','system',null,['total'=>$total,'ok'=>$ok,'missing'=>count($missing),'mismatch'=>count($mismatch)]);
json_response(['total'=>$total,'ok'=>$ok,'missing'=>$missing,'mismatch'=>$mismatch]);
