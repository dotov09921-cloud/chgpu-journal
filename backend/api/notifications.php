<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin','editor']);

$stmt=db()->query("SELECT id,submission_id,event_type,recipient,subject,status,error_text,created_at FROM notification_log ORDER BY id DESC LIMIT 300");
json_response(['items'=>$stmt->fetchAll()]);
