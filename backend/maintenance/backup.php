<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

if(PHP_SAPI!=='cli'){
  $user=require_roles(['admin']);
}else{
  $user=null;
}

$backupDir=$config['backup']['dir']??(__DIR__.'/../storage/backups');
$retention=(int)($config['backup']['retention_days']??30);
$includeUploads=(bool)($config['backup']['include_uploads']??true);

if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)&&!is_dir($backupDir)){
  throw new RuntimeException('Не удалось создать каталог резервных копий');
}

$stamp=date('Ymd-His');
$dbFile=$backupDir.'/db-'.$stamp.'.sql';
$uploadsFile=$backupDir.'/uploads-'.$stamp.'.tar.gz';
$manifestFile=$backupDir.'/manifest-'.$stamp.'.json';

$db=$config['db'];
$cmd=sprintf(
  'mysqldump --single-transaction --quick --skip-lock-tables -h %s -u %s --password=%s %s > %s',
  escapeshellarg($db['host']),
  escapeshellarg($db['user']),
  escapeshellarg($db['pass']),
  escapeshellarg($db['name']),
  escapeshellarg($dbFile)
);
exec($cmd,$out,$code);
if($code!==0||!is_file($dbFile)||filesize($dbFile)===0){
  @unlink($dbFile);
  record_system_error('error','Database backup failed',['exit_code'=>$code]);
  throw new RuntimeException('Не удалось создать резервную копию базы');
}

$uploadsOk=false;
if($includeUploads&&is_dir($config['app']['upload_dir'])){
  $parent=dirname($config['app']['upload_dir']);
  $name=basename($config['app']['upload_dir']);
  $tar=sprintf('tar -czf %s -C %s %s',escapeshellarg($uploadsFile),escapeshellarg($parent),escapeshellarg($name));
  exec($tar,$o2,$c2);
  $uploadsOk=$c2===0&&is_file($uploadsFile)&&filesize($uploadsFile)>0;
  if(!$uploadsOk)@unlink($uploadsFile);
}

$manifest=[
  'created_at'=>date(DATE_ATOM),
  'database'=>[
    'file'=>basename($dbFile),
    'size_bytes'=>filesize($dbFile),
    'sha256'=>hash_file('sha256',$dbFile)
  ],
  'uploads'=>$uploadsOk?[
    'file'=>basename($uploadsFile),
    'size_bytes'=>filesize($uploadsFile),
    'sha256'=>hash_file('sha256',$uploadsFile)
  ]:null
];
file_put_contents($manifestFile,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);

$cutoff=time()-($retention*86400);
foreach(glob($backupDir.'/*')?:[] as $file){
  if(is_file($file)&&filemtime($file)<$cutoff)@unlink($file);
}

if($user)audit_event((int)$user['id'],'backup_created','system',null,['manifest'=>basename($manifestFile)]);

if(PHP_SAPI==='cli'){
  echo json_encode(['ok'=>true,'manifest'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}else{
  json_response(['ok'=>true,'manifest'=>$manifest]);
}
