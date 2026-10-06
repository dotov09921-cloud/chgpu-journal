<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_editor();
require_method('POST');
$body=json_decode(file_get_contents('php://input'),true)?:[];

$submissionId=(int)($body['submission_id']??0);
$issueId=(int)($body['issue_id']??0);
$pages=trim((string)($body['pages']??''));
$doi=trim((string)($body['doi']??''));
$udc=trim((string)($body['udc']??''));
$htmlBody=(string)($body['html_body']??'');

if($submissionId<1||$issueId<1)json_response(['error'=>'Нужно выбрать статью и выпуск'],422);

$pdo=db();
$pdo->beginTransaction();
try{
  $stmt=$pdo->prepare('SELECT * FROM submissions WHERE id=? FOR UPDATE');
  $stmt->execute([$submissionId]);
  $sub=$stmt->fetch();
  if(!$sub)throw new RuntimeException('Рукопись не найдена');
  if(!in_array($sub['status'],['accepted','published'],true))throw new RuntimeException('Публиковать можно только принятую статью');

  $issue=$pdo->prepare('SELECT id FROM issues WHERE id=? LIMIT 1');
  $issue->execute([$issueId]);
  if(!$issue->fetch())throw new RuntimeException('Выпуск не найден');

  $pdf=$pdo->prepare("SELECT relative_path FROM submission_files WHERE submission_id=? AND file_type='pdf' ORDER BY version_no DESC,id DESC LIMIT 1");
  $pdf->execute([$submissionId]);
  $pdfRow=$pdf->fetch();
  $pdfPath=$pdfRow['relative_path']??null;

  $slug=strtolower($sub['public_id']);
  $authors=$sub['author_name'];
  if(trim((string)$sub['coauthors'])!=='')$authors.='; '.$sub['coauthors'];

  $existing=$pdo->prepare('SELECT id FROM published_articles WHERE submission_id=? LIMIT 1');
  $existing->execute([$submissionId]);
  $existingRow=$existing->fetch();
  $articleId=(int)($existingRow['id']??0);

  if($articleId){
    $upd=$pdo->prepare('UPDATE published_articles SET issue_id=?,title=?,authors=?,abstract=?,keywords=?,pages=?,doi=?,udc=?,html_body=?,pdf_path=?,is_visible=1,published_at=NOW() WHERE id=?');
    $upd->execute([$issueId,$sub['title'],$authors,$sub['abstract'],$sub['keywords'],$pages?:null,$doi?:null,$udc?:null,$htmlBody?:null,$pdfPath,$articleId]);
  }else{
    $ins=$pdo->prepare('INSERT INTO published_articles (submission_id,issue_id,slug,title,authors,abstract,keywords,pages,doi,udc,html_body,pdf_path,is_visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)');
    $ins->execute([$submissionId,$issueId,$slug,$sub['title'],$authors,$sub['abstract'],$sub['keywords'],$pages?:null,$doi?:null,$udc?:null,$htmlBody?:null,$pdfPath]);
    $articleId=(int)$pdo->lastInsertId();
  }

  $pdo->prepare("UPDATE submissions SET status='published',issue_id=? WHERE id=?")->execute([$issueId,$submissionId]);
  $pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,from_status,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,?,?,1)')
      ->execute([$submissionId,$user['id'],'published',$sub['status'],'published','Статья опубликована в выпуске']);

  $pdo->commit();
  json_response(['ok'=>true,'article_id'=>$articleId,'slug'=>$slug,'public_url'=>rtrim($config['app']['base_url'],'/').'/published.html?slug='.urlencode($slug)]);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>$e->getMessage()],500);
}
