<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_editor();

$id=(int)($_GET['id']??0);
if($id<1)json_response(['error'=>'Некорректный id'],422);

$pdo=db();
$stmt=$pdo->prepare('SELECT * FROM submissions WHERE id=? LIMIT 1');
$stmt->execute([$id]);$sub=$stmt->fetch();
if(!$sub)json_response(['error'=>'Не найдено'],404);

$files=$pdo->prepare('SELECT id,version_no,file_type,original_name,mime_type,size_bytes,sha256,created_at FROM submission_files WHERE submission_id=? ORDER BY version_no DESC,id DESC');
$files->execute([$id]);

$history=$pdo->prepare('SELECT h.*,u.full_name AS actor_name FROM submission_history h LEFT JOIN users u ON u.id=h.actor_user_id WHERE h.submission_id=? ORDER BY h.id ASC');
$history->execute([$id]);

json_response(['submission'=>$sub,'files'=>$files->fetchAll(),'history'=>$history->fetchAll()]);
