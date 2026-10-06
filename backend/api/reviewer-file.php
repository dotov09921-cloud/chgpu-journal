<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['reviewer']);

$id=(int)($_GET['id']??0);
if($id<1){http_response_code(404);exit;}

$sql="SELECT f.original_name,f.relative_path,f.mime_type,f.size_bytes
      FROM submission_files f
      JOIN review_assignments ra ON ra.submission_id=f.submission_id
      WHERE f.id=? AND ra.reviewer_id=? AND ra.status<>'cancelled'
      LIMIT 1";
$stmt=db()->prepare($sql);
$stmt->execute([$id,$user['id']]);
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
