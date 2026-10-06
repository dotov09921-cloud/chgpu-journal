<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_editor();
json_response(['user'=>[
  'id'=>(int)$user['id'],
  'email'=>$user['email'],
  'full_name'=>$user['full_name'],
  'role'=>$user['role']
]]);
