<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
$user = require_editor();
require_method('POST');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($body['id'] ?? 0);
$newStatus = (string)($body['status'] ?? '');
$comment = trim((string)($body['comment'] ?? ''));
$visible = !array_key_exists('visible_to_author',$body) || (bool)$body['visible_to_author'];

$allowed = ['new','screening','review','revision','accepted','rejected','published'];
if ($id < 1 || !in_array($newStatus,$allowed,true)) json_response(['error'=>'Некорректные данные'],422);

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT status FROM submissions WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Рукопись не найдена');
    $old = $row['status'];

    $upd = $pdo->prepare('UPDATE submissions SET status=?, assigned_editor_id=COALESCE(assigned_editor_id,?) WHERE id=?');
    $upd->execute([$newStatus,$user['id'],$id]);

    $hist = $pdo->prepare('INSERT INTO submission_history (submission_id,actor_user_id,event_type,from_status,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,?,?,?)');
    $hist->execute([$id,$user['id'],'status_changed',$old,$newStatus,$comment,$visible ? 1 : 0]);

    $pdo->commit();
    json_response(['ok'=>true,'from'=>$old,'to'=>$newStatus]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['error'=>$e->getMessage()],500);
}
