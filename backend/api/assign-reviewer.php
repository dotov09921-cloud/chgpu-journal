<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['admin','editor']);
require_method('POST');

$body=json_decode(file_get_contents('php://input'),true)?:[];
$submissionId=(int)($body['submission_id']??0);
$reviewerId=(int)($body['reviewer_id']??0);
$deadline=trim((string)($body['deadline']??''));
$editorNote=trim((string)($body['editor_note']??''));

if($submissionId<1||$reviewerId<1)json_response(['error'=>'Нужно выбрать статью и рецензента'],422);

$pdo=db();
$pdo->beginTransaction();
try{
  $s=$pdo->prepare('SELECT id,status FROM submissions WHERE id=? FOR UPDATE');
  $s->execute([$submissionId]);$sub=$s->fetch();
  if(!$sub)throw new RuntimeException('Рукопись не найдена');

  $r=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='reviewer' AND is_active=1 LIMIT 1");
  $r->execute([$reviewerId]);
  if(!$r->fetch())throw new RuntimeException('Рецензент не найден');

  $stmt=$pdo->prepare("INSERT INTO review_assignments (submission_id,reviewer_id,assigned_by_user_id,status,deadline,editor_note)
    VALUES (?,?,?,'assigned',?,?)
    ON DUPLICATE KEY UPDATE assigned_by_user_id=VALUES(assigned_by_user_id),status='assigned',deadline=VALUES(deadline),editor_note=VALUES(editor_note),recommendation=NULL,reviewer_comment=NULL,submitted_at=NULL");
  $stmt->execute([$submissionId,$reviewerId,$user['id'],$deadline?:null,$editorNote?:null]);

  if($sub['status']!=='review'){
    $pdo->prepare("UPDATE submissions SET status='review' WHERE id=?")->execute([$submissionId]);
    $pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,from_status,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,?,?,1)')
      ->execute([$submissionId,$user['id'],'review_assigned',$sub['status'],'review','Материал передан на рецензирование']);
  }else{
    $pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,comment,is_visible_to_author) VALUES (?,?,?,?,0)')
      ->execute([$submissionId,$user['id'],'reviewer_assigned','Назначен рецензент']);
  }

  $pdo->commit();
  json_response(['ok'=>true]);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>$e->getMessage()],500);
}
