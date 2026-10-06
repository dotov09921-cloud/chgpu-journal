<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
$user=require_roles(['admin']);

$tables=['users','issues','submissions','submission_files','submission_history','published_articles','review_assignments','notification_log','system_audit_log','system_errors'];
$data=['exported_at'=>date(DATE_ATOM),'schema_version'=>1,'tables'=>[]];
foreach($tables as $table){
  $data['tables'][$table]=db()->query('SELECT * FROM '.$table)->fetchAll();
}
audit_event((int)$user['id'],'data_exported','system',null,['tables'=>$tables]);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="chgpu-export-'.date('Ymd-His').'.json"');
echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
exit;
