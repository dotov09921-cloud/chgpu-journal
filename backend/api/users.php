<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['admin']);
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->query("SELECT id,email,full_name,`role`,is_active,must_set_password,last_login_at,created_at FROM users ORDER BY full_name");
  json_response(['items'=>$stmt->fetchAll()]);
}

require_method('POST');
require_csrf();
$body=json_decode(file_get_contents('php://input'),true)?:[];
$action=(string)($body['action']??'');

if($action==='invite'){
  $email=trim((string)($body['email']??''));
  $name=trim((string)($body['full_name']??''));
  $role=(string)($body['role']??'reviewer');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name===''||!in_array($role,['admin','editor','reviewer'],true)){
    json_response(['error'=>'Некорректные данные пользователя'],422);
  }

  $existing=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
  $existing->execute([$email]);
  if($existing->fetch()){
    json_response(['error'=>'Пользователь с таким e-mail уже существует'],409);
  }

  $token=random_token(32);
  $hash=hash('sha256',$token);
  $placeholder=password_hash(random_token(24),PASSWORD_DEFAULT);

  $stmt=$pdo->prepare("INSERT INTO users (email,password_hash,full_name,`role`,is_active,must_set_password,activation_token_hash,activation_expires_at)
    VALUES (?,?,?,?,1,1,?,DATE_ADD(NOW(),INTERVAL 72 HOUR))");
  try{
    $stmt->execute([$email,$placeholder,$name,$role,$hash]);
  }catch(PDOException $e){
    // A concurrent invitation can insert the email after the existence check.
    if((int)($e->errorInfo[1]??0)===1062){
      json_response(['error'=>'Пользователь с таким e-mail уже существует'],409);
    }
    throw $e;
  }

  $id=(int)$pdo->lastInsertId();

  $invite=rtrim($config['app']['base_url'],'/').'/set-password.html?token='.urlencode($token).'&email='.urlencode($email);
  $bodyHtml='<p>Вам предоставлен доступ к редакционной системе «Известия ЧГПУ».</p><p>Роль: <strong>'.mail_escape($role).'</strong>.</p>'.mail_button($invite,'Установить пароль');
  send_notification(null,'user_invite',$email,'Приглашение в редакционную систему',mail_layout('Приглашение',$bodyHtml));
  audit_event((int)$user['id'],'user_invited','user',$id,['email'=>$email,'role'=>$role]);
  json_response(['ok'=>true,'id'=>$id,'invite_url'=>$invite],201);
}

if($action==='update'){
  $id=(int)($body['id']??0);
  $role=(string)($body['role']??'');
  $active=array_key_exists('is_active',$body)?((bool)$body['is_active']?1:0):null;
  if($id<1||($role!==''&&!in_array($role,['admin','editor','reviewer'],true)))json_response(['error'=>'Некорректные данные'],422);
  if($id===(int)$user['id']&&$active===0)json_response(['error'=>'Нельзя заблокировать собственную учётную запись'],409);

  $fields=[];$params=[];
  if($role!==''){$fields[]='`role`=?';$params[]=$role;}
  if($active!==null){$fields[]='is_active=?';$params[]=$active;}
  if(!$fields)json_response(['error'=>'Нет изменений'],422);
  $params[]=$id;
  $stmt=$pdo->prepare('UPDATE users SET '.implode(',',$fields).' WHERE id=?');
  $stmt->execute($params);
  audit_event((int)$user['id'],'user_updated','user',$id,['role'=>$role?:null,'is_active'=>$active]);
  json_response(['ok'=>true]);
}

json_response(['error'=>'Неизвестное действие'],422);
