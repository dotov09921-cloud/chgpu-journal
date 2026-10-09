<?php
declare(strict_types=1);
// The old unbounded importer is intentionally retired, including its CLI path.
// All writes now go through the admin session + CSRF protected batch endpoint.
require_once __DIR__.'/../bootstrap.php';
require_roles(['admin']);
header('Cache-Control: no-store');
json_response(['error'=>'Используйте admin-migration.html: метаданные отдельно, затем одна PDF за запрос.'],410);
