<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['reviewer']);

$sql="SELECT ra.id AS assignment_id,ra.status AS review_status,ra.deadline,ra.recommendation,ra.reviewer_comment,ra.editor_note,
            s.id AS submission_id,s.public_id,s.title,s.section,s.abstract,s.keywords,s.created_at
      FROM review_assignments ra
      JOIN submissions s ON s.id=ra.submission_id
      WHERE ra.reviewer_id=? AND ra.status<>'cancelled'
      ORDER BY CASE ra.status WHEN 'assigned' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'submitted' THEN 3 ELSE 4 END, ra.created_at DESC";
$stmt=db()->prepare($sql);$stmt->execute([$user['id']]);
json_response(['items'=>$stmt->fetchAll()]);
