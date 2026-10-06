<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
$user = require_editor();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string)($_GET['status'] ?? ''));
    $sql = 'SELECT s.id,s.public_id,s.author_name,s.author_email,s.organization,s.title,s.section,s.status,s.created_at,s.updated_at,u.full_name AS editor_name
            FROM submissions s LEFT JOIN users u ON u.id=s.assigned_editor_id';
    $params = [];
    if ($status !== '') {
        $sql .= ' WHERE s.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY s.created_at DESC LIMIT 500';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(['items' => $stmt->fetchAll()]);
}
json_response(['error' => 'Method not allowed'], 405);
