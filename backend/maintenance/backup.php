<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

function create_backup(?array $actor=null): array {
  global $config;

  $backupDir=$config['backup']['dir']??(__DIR__.'/../storage/backups');
  $retention=(int)($config['backup']['retention_days']??30);
  $includeUploads=(bool)($config['backup']['include_uploads']??true);

  if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)&&!is_dir($backupDir)){
    throw new RuntimeException('Не удалось создать каталог резервных копий');
  }

  $stamp=date('Ymd-His');
  $dbFile=$backupDir.'/db-'.$stamp.'.sql';
  $uploadsFile=$backupDir.'/uploads-'.$stamp.'.tar.gz';
  $archiveFile=$backupDir.'/archive-'.$stamp.'.tar.gz';
  $manifestFile=$backupDir.'/manifest-'.$stamp.'.json';

  $db=$config['db'];
  $defaults=tempnam(sys_get_temp_dir(),'chgpu-mysql-');
  if($defaults===false)throw new RuntimeException('Не удалось подготовить безопасный backup');
  file_put_contents($defaults,"[client]\nhost=".$db['host']."\nuser=".$db['user']."\npassword=".$db['pass']."\n");
  chmod($defaults,0600);
  $cmd=sprintf(
    'mysqldump --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables %s > %s',
    escapeshellarg($defaults),
    escapeshellarg($db['name']),
    escapeshellarg($dbFile)
  );
  exec($cmd,$out,$code);
  @unlink($defaults);
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

  $archiveOk=false;
  $archiveDir=$config['app']['archive_dir']??null;
  if($archiveDir&&is_dir($archiveDir)){
    $parent=dirname($archiveDir);$name=basename($archiveDir);
    $tar=sprintf('tar -czf %s -C %s %s',escapeshellarg($archiveFile),escapeshellarg($parent),escapeshellarg($name));
    exec($tar,$o3,$c3);
    $archiveOk=$c3===0&&is_file($archiveFile)&&filesize($archiveFile)>0;
    if(!$archiveOk)@unlink($archiveFile);
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
    ]:null,
    'archive'=>$archiveOk?[
      'file'=>basename($archiveFile),
      'size_bytes'=>filesize($archiveFile),
      'sha256'=>hash_file('sha256',$archiveFile)
    ]:null
  ];
  file_put_contents($manifestFile,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);

  $cutoff=time()-($retention*86400);
  foreach(glob($backupDir.'/*')?:[] as $file){
    if(is_file($file)&&filemtime($file)<$cutoff)@unlink($file);
  }

  if($actor)audit_event((int)$actor['id'],'backup_created','system',null,['manifest'=>basename($manifestFile)]);
  return $manifest;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
  if(PHP_SAPI==='cli'){
    try{
      $manifest=create_backup(null);
      echo json_encode(['ok'=>true,'manifest'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
      exit(0);
    }catch(Throwable $e){
      fwrite(STDERR,$e->getMessage().PHP_EOL);
      exit(1);
    }
  }
  json_response(['error'=>'Run this maintenance script from CLI or the admin endpoint'],403);
}
