<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
$user=require_roles(['admin']);
require_method('POST');
require_once __DIR__.'/../maintenance/backup.php';

try{
  $manifest=create_backup($user);
  json_response(['ok'=>true,'manifest'=>$manifest]);
}catch(Throwable $e){
  record_system_error('error','Manual backup failed',['error'=>$e->getMessage()]);
  json_response(['error'=>$e->getMessage()],500);
}
