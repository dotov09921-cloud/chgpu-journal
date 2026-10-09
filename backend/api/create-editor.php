<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$count=(int)db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
if($count>0)json_response(['error'=>'Первый пользователь уже создан'],403);

require_method('POST');
require_csrf();
$body=json_decode(file_get_contents('php://input'),true)?:[];
$email=trim((string)($body['email']??''));
$password=(string)($body['password']??'');
$name=trim((string)($body['full_name']??''));

if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10||$name==='')json_response(['error'=>'Нужны корректный e-mail, ФИО и пароль минимум 10 символов'],422);

$stmt=db()->prepare('INSERT INTO users (email,password_hash,full_name,`role`) VALUES (?,?,?,?)');
$stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name,'admin']);
json_response(['ok'=>true,'message'=>'Администратор создан. Удалите api/create-editor.php с сервера.'],201);
