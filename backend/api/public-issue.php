<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$id=(int)($_GET['id']??0);
if($id<1)json_response(['error'=>'Некорректный id'],422);

$stmt=db()->prepare('SELECT id,year,series,number,title,publication_date,pdf_path FROM issues WHERE id=? AND is_published=1 LIMIT 1');
$stmt->execute([$id]);
$issue=$stmt->fetch();
if(!$issue)json_response(['error'=>'Выпуск не найден'],404);

$a=db()->prepare('SELECT id,slug,title,authors,abstract,keywords,pages,doi,udc,pdf_path,published_at FROM published_articles WHERE issue_id=? AND is_visible=1 ORDER BY id ASC');
$a->execute([$id]);

json_response(['issue'=>$issue,'articles'=>$a->fetchAll()]);
