<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['reviewer']);
require_method('POST');

$body=json_decode(file_get_contents('php://input'),true)?:[];
$assignmentId=(int)($body['assignment_id']??0);
$recommendation=(string)($body['recommendation']??'');
$comment=trim((string)($body['comment']??''));

if($assignmentId<1||!in_array($recommendation,['accept','revision','reject'],true)||$comment===''){
  json_response(['error'=>'Выберите рекомендацию и заполните заключение'],422);
}

$pdo=db();$pdo->beginTransaction();
try{
  $stmt=$pdo->prepare('SELECT ra.*,s.status AS submission_status FROM review_assignments ra JOIN submissions s ON s.id=ra.submission_id WHERE ra.id=? AND ra.reviewer_id=? FOR UPDATE');
  $stmt->execute([$assignmentId,$user['id']]);$ra=$stmt->fetch();
  if(!$ra)throw new RuntimeException('Назначение не найдено');

  $upd=$pdo->prepare("UPDATE review_assignments SET status='submitted',recommendation=?,reviewer_comment=?,submitted_at=NOW() WHERE id=?");
  $upd->execute([$recommendation,$comment,$assignmentId]);

  $pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,comment,is_visible_to_author) VALUES (?,?,?,?,0)')
    ->execute([$ra['submission_id'],$user['id'],'review_submitted','Получено заключение рецензента']);

  $pdo->commit();
  json_response(['ok'=>true]);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>$e->getMessage()],500);
}
