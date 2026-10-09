<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_once __DIR__.'/../maintenance/legacy-migration.php';
header('Cache-Control: no-store');
$user=require_roles(['admin']);
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,['GET','POST'],true))json_response(['error'=>'Method not allowed'],405);
if($method==='POST')require_csrf();
// Release the session lock so status checks and the rest of the admin UI remain usable.
session_write_close();
$lock=null;$locked=false;
try{
    $items=legacy_manifest();$pdo=db();
    $archiveDir=rtrim($config['app']['archive_dir']??(__DIR__.'/../storage/archive'),'/\\');
    if($method==='GET')json_response(legacy_status($pdo,$items));
    $input=json_decode((string)file_get_contents('php://input'),true);
    $action=$input['action']??'';
    if(!in_array($action,['metadata','pdf_batch'],true))json_response(['error'=>'Unknown migration action'],400);
    if(!is_dir($archiveDir)&&!mkdir($archiveDir,0750,true)&&!is_dir($archiveDir))throw new RuntimeException('Archive directory unavailable');
    $lock=fopen($archiveDir.'/.migration.lock','c');
    if(!$lock)throw new RuntimeException('Migration lock unavailable');
    $locked=flock($lock,LOCK_EX|LOCK_NB);
    if(!$locked){fclose($lock);$lock=null;json_response(['error'=>'Миграция уже выполняется. Подождите и обновите статус.'],409);}
    $result=$action==='metadata'?legacy_metadata($pdo,$items):legacy_batch($pdo,$items,$archiveDir);
    audit_event((int)$user['id'],'legacy_migration_'.$action,'system',null,$result);
    $response=legacy_status($pdo,$items)+['action'=>$action,'result'=>$result];
}catch(Throwable $e){
    error_log('Legacy migration: '.$e->getMessage());
    $response=['error'=>'Миграция не завершена. Проверьте конфигурацию, права записи и журнал PHP.'];
    $httpStatus=500;
}finally{
    if(is_resource($lock)){if($locked)flock($lock,LOCK_UN);fclose($lock);}
}
json_response($response,$httpStatus??200);
