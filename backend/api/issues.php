<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_editor();
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$pdo->query("SELECT i.*,COUNT(a.id) AS article_count FROM issues i LEFT JOIN published_articles a ON a.issue_id=i.id AND a.is_visible=1 GROUP BY i.id ORDER BY i.year DESC,i.id DESC");
  json_response(['items'=>$stmt->fetchAll()]);
}

require_method('POST');
$body=json_decode(file_get_contents('php://input'),true)?:[];
$action=(string)($body['action']??'create');

if($action==='create'){
  $year=(int)($body['year']??0);
  $series=trim((string)($body['series']??''));
  $number=trim((string)($body['number']??''));
  $title=trim((string)($body['title']??''));
  $date=trim((string)($body['publication_date']??''));
  if($year<1900||$year>2100||$series===''||$number==='')json_response(['error'=>'Заполните год, серию и номер выпуска'],422);
  $stmt=$pdo->prepare('INSERT INTO issues (year,series,number,title,publication_date,is_published) VALUES (?,?,?,?,?,0)');
  $stmt->execute([$year,$series,$number,$title?:null,$date?:null]);
  json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}

if($action==='set_published'){
  $id=(int)($body['id']??0);
  $value=!empty($body['is_published'])?1:0;
  $stmt=$pdo->prepare('UPDATE issues SET is_published=? WHERE id=?');
  $stmt->execute([$value,$id]);
  json_response(['ok'=>true]);
}

json_response(['error'=>'Неизвестное действие'],422);
