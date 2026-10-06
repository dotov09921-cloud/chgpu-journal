<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['admin']);
require_method('POST');

ob_start();
try{
  require __DIR__.'/../maintenance/backup.php';
}catch(Throwable $e){
  ob_end_clean();
  record_system_error('error','Manual backup failed',['error'=>$e->getMessage()]);
  json_response(['error'=>$e->getMessage()],500);
}
