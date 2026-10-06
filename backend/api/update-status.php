<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['admin','editor']);
require_method('POST');
require_csrf();

$body=json_decode(file_get_contents('php://input'),true)?:[];
$id=(int)($body['id']??0);
$newStatus=(string)($body['status']??'');
$comment=trim((string)($body['comment']??''));
$visible=!array_key_exists('visible_to_author',$body)||(bool)$body['visible_to_author'];

$allowed=['new','screening','review','revision','accepted','rejected','published'];
if($id<1||!in_array($newStatus,$allowed,true))json_response(['error'=>'Некорректные данные'],422);

$pdo=db();$pdo->beginTransaction();
try{
  $stmt=$pdo->prepare('SELECT status FROM submissions WHERE id=? FOR UPDATE');
  $stmt->execute([$id]);$row=$stmt->fetch();
  if(!$row)throw new RuntimeException('Рукопись не найдена');
  $old=$row['status'];

  $upd=$pdo->prepare('UPDATE submissions SET status=?,assigned_editor_id=COALESCE(assigned_editor_id,?) WHERE id=?');
  $upd->execute([$newStatus,$user['id'],$id]);

  $hist=$pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,from_status,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,?,?,?)');
  $hist->execute([$id,$user['id'],'status_changed',$old,$newStatus,$comment,$visible?1:0]);

  $pdo->commit();

  if($visible){
    $s=$pdo->prepare('SELECT public_id,title,author_name,author_email,tracking_token FROM submissions WHERE id=? LIMIT 1');
    $s->execute([$id]);$mailSub=$s->fetch();
    if($mailSub){
      $statusLabels=[
        'screening'=>'Первичная проверка',
        'review'=>'На рецензировании',
        'revision'=>'Требуется доработка',
        'accepted'=>'Принята к публикации',
        'rejected'=>'Отклонена',
        'published'=>'Опубликована'
      ];
      $track=rtrim($config['app']['base_url'],'/').'/track.html?id='.urlencode($mailSub['public_id']).'&token='.urlencode($mailSub['tracking_token']);
      $body='<p>Статус рукописи <strong>'.mail_escape($mailSub['public_id']).'</strong> изменён: <strong>'.mail_escape($statusLabels[$newStatus]??$newStatus).'</strong>.</p>';
      if($comment!=='')$body.='<p>'.nl2br(mail_escape($comment)).'</p>';
      $body.=mail_button($track,'Открыть статус рукописи');
      send_notification($id,'status_'.$newStatus,$mailSub['author_email'],'Статус рукописи '.$mailSub['public_id'],mail_layout('Изменение статуса',$body));
    }
  }

  json_response(['ok'=>true,'from'=>$old,'to'=>$newStatus]);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>$e->getMessage()],500);
}
