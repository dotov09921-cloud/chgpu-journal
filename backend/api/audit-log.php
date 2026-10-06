<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin']);

$sql="SELECT a.id,a.event_type,a.entity_type,a.entity_id,a.details_json,a.ip_address,a.created_at,u.full_name AS actor_name,u.email AS actor_email
      FROM system_audit_log a
      LEFT JOIN users u ON u.id=a.actor_user_id
      ORDER BY a.id DESC LIMIT 500";
$stmt=db()->query($sql);
json_response(['items'=>$stmt->fetchAll()]);
