<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$id=(int)($_GET['id']??0);
if($id<1){http_response_code(404);exit;}

$stmt=db()->prepare("SELECT pdf_path,number,year FROM issues WHERE id=? AND is_published=1 AND pdf_path IS NOT NULL LIMIT 1");
$stmt->execute([$id]);$issue=$stmt->fetch();
if(!$issue){http_response_code(404);exit;}

$base=realpath($config['app']['archive_dir']??(__DIR__.'/../storage/archive'));
$path=realpath(rtrim($config['app']['archive_dir']??(__DIR__.'/../storage/archive'),'/\\').'/'.ltrim($issue['pdf_path'],'/\\'));
if(!$base||!$path||strpos($path,$base)!==0||!is_file($path)){http_response_code(404);exit;}

header('Content-Type: application/pdf');
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="issue-'.$issue['year'].'-'.$issue['id'].'.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
