<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
try {
  $pdo=db();
  $pdo->query('SELECT 1');
  json_response([
    'ok'=>true,
    'service'=>'chgpu-journal-backend',
    'php'=>PHP_VERSION,
    'database'=>'connected',
    'time'=>date(DATE_ATOM)
  ]);
} catch(Throwable $e) {
  json_response(['ok'=>false,'database'=>'error','detail'=>$e->getMessage()],500);
}
