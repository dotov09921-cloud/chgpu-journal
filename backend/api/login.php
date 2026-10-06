<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_method('POST');

$body=json_decode(file_get_contents('php://input'),true)?:[];
$email=trim((string)($body['email']??''));
$password=(string)($body['password']??'');

$stmt=db()->prepare('SELECT id,email,password_hash,full_name,role,is_active FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);$user=$stmt->fetch();

if(!$user||!$user['is_active']||!password_verify($password,$user['password_hash']))json_response(['error'=>'Неверный e-mail или пароль'],401);

start_editor_session();
session_regenerate_id(true);
$_SESSION['user_id']=(int)$user['id'];
db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
audit_event((int)$user['id'],'login','user',(int)$user['id'],['role'=>$user['role']]);

json_response(['ok'=>true,'user'=>[
  'id'=>(int)$user['id'],
  'email'=>$user['email'],
  'full_name'=>$user['full_name'],
  'role'=>$user['role']
]]);
