<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_method('POST');
$user=require_roles(['admin']);
require_csrf();

global $config;
$mailCfg=$config['mail']??[];
$to=trim((string)($mailCfg['editor_email']??$mailCfg['from_email']??''));
if(!filter_var($to,FILTER_VALIDATE_EMAIL)){
  json_response(['error'=>'Не задан корректный адрес редакции'],422);
}

$body='<p>Тестовое письмо из редакционной системы «Известия ЧГПУ».</p><p>Если вы получили это сообщение, SMTP настроен и работает.</p>';
$ok=send_notification(null,'mail_test',$to,'Проверка почты',mail_layout('Проверка почты',$body));

audit_event((int)$user['id'],'mail_test','system',null,[
  'recipient'=>$to,
  'ok'=>$ok
]);

if(!$ok){
  $stmt=db()->prepare("SELECT error_text FROM notification_log WHERE event_type='mail_test' AND recipient=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$to]);
  $error=(string)($stmt->fetch()['error_text']??'Неизвестная ошибка');
  json_response(['error'=>'Письмо не отправлено: '.$error],502);
}

json_response(['ok'=>true,'recipient'=>$to]);
