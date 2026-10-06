<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

if(PHP_SAPI!=='cli'){
  $admin=require_roles(['admin']);
}else{
  $admin=null;
}

$manifestPath=dirname(__DIR__,2).'/migration/import-ready.json';
if(!is_file($manifestPath))throw new RuntimeException('Не найден migration/import-ready.json');
$data=json_decode((string)file_get_contents($manifestPath),true);
if(!is_array($data)||empty($data['issues']))throw new RuntimeException('Некорректный migration manifest');

$archiveDir=$config['app']['archive_dir']??(__DIR__.'/../storage/archive');
if(!is_dir($archiveDir)&&!mkdir($archiveDir,0750,true)&&!is_dir($archiveDir))throw new RuntimeException('Не удалось создать archive_dir');

function download_legacy_pdf(string $url,string $target): void {
  $tmp=$target.'.part';
  @unlink($tmp);
  $fp=fopen($tmp,'wb');
  if(!$fp)throw new RuntimeException('Не удалось открыть временный файл');

  if(function_exists('curl_init')){
    $ch=curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_FILE=>$fp,
      CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_CONNECTTIMEOUT=>20,
      CURLOPT_TIMEOUT=>180,
      CURLOPT_USERAGENT=>'CHGPU-Journal-Migration/1.0',
      CURLOPT_SSL_VERIFYPEER=>false,
      CURLOPT_SSL_VERIFYHOST=>0,
      CURLOPT_FAILONERROR=>true
    ]);
    $ok=curl_exec($ch);
    $err=curl_error($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fp);
    if(!$ok||$code<200||$code>=300){@unlink($tmp);throw new RuntimeException('HTTP '.$code.' '.$err);}
  }else{
    fclose($fp);
    $ctx=stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false],'http'=>['timeout'=>180,'user_agent'=>'CHGPU-Journal-Migration/1.0']]);
    $raw=@file_get_contents($url,false,$ctx);
    if($raw===false){@unlink($tmp);throw new RuntimeException('Не удалось скачать PDF');}
    file_put_contents($tmp,$raw);
  }

  $head=file_get_contents($tmp,false,null,0,5);
  if($head!=='%PDF-'){@unlink($tmp);throw new RuntimeException('Скачанный файл не PDF');}
  if(!rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('Не удалось переместить PDF');}
}

$pdo=db();
$summary=['metadata'=>0,'downloaded'=>0,'skipped'=>0,'failed'=>0];
$errors=[];

foreach($data['issues'] as $item){
  $pdo->beginTransaction();
  try{
    $stmt=$pdo->prepare("INSERT INTO issues
      (year,series,number,title,pdf_path,legacy_key,legacy_source_url,legacy_label,migration_status,legacy_sha256,legacy_pages,legacy_size_bytes,is_published)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)
      ON DUPLICATE KEY UPDATE
        series=VALUES(series),number=VALUES(number),legacy_source_url=VALUES(legacy_source_url),legacy_label=VALUES(legacy_label),
        migration_status=VALUES(migration_status),legacy_sha256=VALUES(legacy_sha256),legacy_pages=VALUES(legacy_pages),legacy_size_bytes=VALUES(legacy_size_bytes),is_published=1");
    $stmt->execute([
      (int)$item['year'],$item['series'],$item['number'],null,null,$item['legacy_key'],$item['source_url'],$item['legacy_label'],
      $item['migration_status'],$item['sha256']?:null,$item['pages']?:null,$item['size_bytes']?:null
    ]);
    $q=$pdo->prepare('SELECT id FROM issues WHERE legacy_key=? LIMIT 1');
    $q->execute([$item['legacy_key']]);$issueId=(int)$q->fetch()['id'];
    $pdo->commit();
    $summary['metadata']++;

    if($item['migration_status']!=='ready'){$summary['skipped']++;continue;}

    $yearDir=rtrim($archiveDir,'/\\').'/'.(int)$item['year'];
    if(!is_dir($yearDir)&&!mkdir($yearDir,0750,true)&&!is_dir($yearDir))throw new RuntimeException('Не удалось создать папку года');
    $target=$yearDir.'/'.$item['local_filename'];

    $validExisting=is_file($target)
      && (!empty($item['sha256']) ? hash_equals($item['sha256'],hash_file('sha256',$target)) : true)
      && (!empty($item['size_bytes']) ? filesize($target)===(int)$item['size_bytes'] : true);

    if(!$validExisting){
      download_legacy_pdf($item['source_url'],$target);
    }

    if(!empty($item['sha256'])&&!hash_equals($item['sha256'],hash_file('sha256',$target)))throw new RuntimeException('SHA-256 не совпадает');
    if(!empty($item['size_bytes'])&&filesize($target)!==(int)$item['size_bytes'])throw new RuntimeException('Размер файла не совпадает');

    $relative='/'.(int)$item['year'].'/'.$item['local_filename'];
    $upd=$pdo->prepare("UPDATE issues SET pdf_path=?,migration_status='imported' WHERE id=?");
    $upd->execute([$relative,$issueId]);
    $summary['downloaded']++;
  }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['failed']++;
    $errors[]=['legacy_key'=>$item['legacy_key'],'error'=>$e->getMessage()];
    try{
      $pdo->prepare("UPDATE issues SET migration_status='failed' WHERE legacy_key=?")->execute([$item['legacy_key']]);
    }catch(Throwable $ignore){}
    record_system_error('error','Legacy archive import failed',['legacy_key'=>$item['legacy_key'],'error'=>$e->getMessage()]);
  }
}

if($admin)audit_event((int)$admin['id'],'legacy_archive_import','system',null,$summary);
$result=['ok'=>$summary['failed']===0,'summary'=>$summary,'errors'=>$errors];

if(PHP_SAPI==='cli'){
  echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
}else{
  json_response($result,$summary['failed']?207:200);
}
