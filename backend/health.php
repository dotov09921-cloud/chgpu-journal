<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
try{
  db()->query('SELECT 1');
  json_response(['ok'=>true,'service'=>'chgpu-journal-backend']);
}catch(Throwable $e){
  record_system_error('error','Health check failed',['error'=>$e->getMessage()]);
  json_response(['ok'=>false,'service'=>'chgpu-journal-backend'],500);
}
