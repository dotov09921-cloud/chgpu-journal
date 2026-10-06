<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$id=trim((string)($_GET['id']??''));
$token=trim((string)($_GET['token']??''));
if($id===''||$token==='')json_response(['error'=>'Недостаточно данных'],422);

$stmt=db()->prepare('SELECT id,public_id,title,status,created_at,updated_at FROM submissions WHERE public_id=? AND tracking_token=? LIMIT 1');
$stmt->execute([$id,$token]);
$sub=$stmt->fetch();
if(!$sub)json_response(['error'=>'Рукопись не найдена'],404);

$hist=db()->prepare('SELECT event_type,from_status,to_status,comment,created_at FROM submission_history WHERE submission_id=? AND is_visible_to_author=1 ORDER BY id ASC');
$hist->execute([$sub['id']]);

json_response(['submission'=>[
  'public_id'=>$sub['public_id'],
  'title'=>$sub['title'],
  'status'=>$sub['status'],
  'created_at'=>$sub['created_at'],
  'updated_at'=>$sub['updated_at']
],'history'=>$hist->fetchAll()]);
