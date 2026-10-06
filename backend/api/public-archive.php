<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';

$stmt=db()->query("SELECT id,year,series,number,title,migration_status,legacy_pages,
  CASE WHEN pdf_path IS NOT NULL THEN 1 ELSE 0 END AS has_pdf
  FROM issues
  WHERE is_published=1
  ORDER BY year DESC,id DESC");
json_response(['items'=>$stmt->fetchAll()]);
