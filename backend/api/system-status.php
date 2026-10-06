<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin']);

$pdo=db();
$status=[];
$status['php_version']=PHP_VERSION;
$status['db']='ok';
$status['time']=date(DATE_ATOM);
$status['uploads_writable']=is_dir($config['app']['upload_dir'])&&is_writable($config['app']['upload_dir']);
$status['mail_enabled']=(bool)($config['mail']['enabled']??false);

$status['counts']=[
  'users'=>(int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
  'submissions'=>(int)$pdo->query('SELECT COUNT(*) c FROM submissions')->fetch()['c'],
  'files'=>(int)$pdo->query('SELECT COUNT(*) c FROM submission_files')->fetch()['c'],
  'published_articles'=>(int)$pdo->query('SELECT COUNT(*) c FROM published_articles')->fetch()['c'],
  'failed_notifications'=>(int)$pdo->query("SELECT COUNT(*) c FROM notification_log WHERE status='failed'")->fetch()['c'],
  'system_errors'=>(int)$pdo->query('SELECT COUNT(*) c FROM system_errors')->fetch()['c']
];

$backupDir=$config['backup']['dir']??(__DIR__.'/../storage/backups');
$manifests=glob($backupDir.'/manifest-*.json')?:[];
rsort($manifests);
$status['latest_backup']=null;
if($manifests){
  $raw=@file_get_contents($manifests[0]);
  $status['latest_backup']=$raw?json_decode($raw,true):['file'=>basename($manifests[0])];
}

$status['recent_errors']=$pdo->query('SELECT level,message,request_uri,created_at FROM system_errors ORDER BY id DESC LIMIT 10')->fetchAll();
$status['recent_failed_mail']=$pdo->query("SELECT recipient,subject,error_text,created_at FROM notification_log WHERE status='failed' ORDER BY id DESC LIMIT 10")->fetchAll();

json_response($status);
