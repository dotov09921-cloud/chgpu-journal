<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_method('POST');

$body=json_decode(file_get_contents('php://input'),true)?:[];
$email=trim((string)($body['email']??''));
$token=trim((string)($body['token']??''));
$password=(string)($body['password']??'');

if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$token===''||strlen($password)<10){
  json_response(['error'=>'Пароль должен содержать минимум 10 символов'],422);
}

$stmt=db()->prepare('SELECT id,activation_token_hash,activation_expires_at,is_active FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);$u=$stmt->fetch();
if(!$u||!$u['is_active'])json_response(['error'=>'Приглашение недействительно'],404);
if(!$u['activation_token_hash']||!hash_equals($u['activation_token_hash'],hash('sha256',$token)))json_response(['error'=>'Неверная ссылка приглашения'],403);
if(!$u['activation_expires_at']||strtotime($u['activation_expires_at'])<time())json_response(['error'=>'Срок действия приглашения истёк'],410);

$upd=db()->prepare('UPDATE users SET password_hash=?,must_set_password=0,activation_token_hash=NULL,activation_expires_at=NULL WHERE id=?');
$upd->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);
audit_event((int)$u['id'],'password_set','user',(int)$u['id'],[]);
json_response(['ok'=>true]);
