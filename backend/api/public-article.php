<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$slug=trim((string)($_GET['slug']??''));
if($slug==='')json_response(['error'=>'Не указан slug'],422);

$stmt=db()->prepare('SELECT a.*,i.year,i.series,i.number,i.title AS issue_title FROM published_articles a JOIN issues i ON i.id=a.issue_id WHERE a.slug=? AND a.is_visible=1 AND i.is_published=1 LIMIT 1');
$stmt->execute([$slug]);
$article=$stmt->fetch();
if(!$article)json_response(['error'=>'Статья не найдена'],404);

json_response(['article'=>$article]);
