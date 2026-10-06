<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_roles(['admin','editor']);

$stmt=db()->query("SELECT id,email,full_name,role FROM users WHERE is_active=1 AND role='reviewer' ORDER BY full_name");
json_response(['items'=>$stmt->fetchAll()]);
