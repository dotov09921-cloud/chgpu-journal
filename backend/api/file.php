<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin','editor']);

$id=(int)($_GET['id']??0);
if($id<1){http_response_code(404);exit;}

$stmt=db()->prepare('SELECT original_name,relative_path,mime_type,size_bytes FROM submission_files WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$file=$stmt->fetch();
if(!$file){http_response_code(404);exit;}

$base=realpath($config['app']['upload_dir']);
$path=realpath(rtrim($config['app']['upload_dir'],'/\\').'/'.ltrim($file['relative_path'],'/\\'));
if(!$base||!$path||strpos($path,$base)!==0||!is_file($path)){http_response_code(404);exit;}

header('Content-Type: '.$file['mime_type']);
header('Content-Length: '.filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['original_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
