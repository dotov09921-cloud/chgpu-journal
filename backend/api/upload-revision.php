<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_method('POST');

$publicId=trim((string)($_POST['id']??''));
$token=trim((string)($_POST['token']??''));
if($publicId===''||$token==='')json_response(['error'=>'Некорректная ссылка'],422);

$pdo=db();
$stmt=$pdo->prepare('SELECT id,status,public_id FROM submissions WHERE public_id=? AND tracking_token=? LIMIT 1');
$stmt->execute([$publicId,$token]);
$sub=$stmt->fetch();
if(!$sub)json_response(['error'=>'Рукопись не найдена'],404);
if($sub['status']!=='revision')json_response(['error'=>'Новая версия сейчас не запрашивается редакцией'],409);

if(empty($_FILES['manuscript'])||$_FILES['manuscript']['error']!==UPLOAD_ERR_OK){
  json_response(['error'=>'Необходимо приложить DOC/DOCX'],422);
}

$allowed=[
  'manuscript'=>['extensions'=>['doc','docx'],'mimes'=>[
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip','application/octet-stream'
  ]],
  'pdf'=>['extensions'=>['pdf'],'mimes'=>['application/pdf','application/octet-stream']]
];

$pdo->beginTransaction();
try{
  $v=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 AS next_version FROM submission_files WHERE submission_id=?');
  $v->execute([$sub['id']]);
  $version=(int)$v->fetch()['next_version'];

  $uploadDir=rtrim($config['app']['upload_dir'],'/\\').'/'.date('Y').'/'.$sub['public_id'];
  if(!is_dir($uploadDir)&&!mkdir($uploadDir,0750,true)&&!is_dir($uploadDir)){
    throw new RuntimeException('Не удалось создать каталог загрузки');
  }

  foreach(['manuscript','pdf'] as $field){
    if(empty($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE)continue;
    if($_FILES[$field]['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Ошибка загрузки файла');
    if((int)$_FILES[$field]['size']>(int)$config['app']['max_upload_bytes'])throw new RuntimeException('Файл слишком большой');

    $original=clean_filename($_FILES[$field]['name']);
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed[$field]['extensions'],true))throw new RuntimeException('Недопустимое расширение');

    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=(string)$finfo->file($_FILES[$field]['tmp_name']);
    if(!in_array($mime,$allowed[$field]['mimes'],true))throw new RuntimeException('Недопустимый тип файла');

    $stored=$field.'-v'.$version.'-'.random_token(10).'.'.$ext;
    $target=$uploadDir.'/'.$stored;
    if(!move_uploaded_file($_FILES[$field]['tmp_name'],$target))throw new RuntimeException('Не удалось сохранить файл');

    $relative=str_replace(rtrim($config['app']['upload_dir'],'/\\'),'',$target);
    $ins=$pdo->prepare('INSERT INTO submission_files (submission_id,version_no,file_type,original_name,stored_name,relative_path,mime_type,size_bytes,sha256) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->execute([$sub['id'],$version,$field,$original,$stored,$relative,$mime,filesize($target),hash_file('sha256',$target)]);
  }

  $pdo->prepare("UPDATE submissions SET status='screening' WHERE id=?")->execute([$sub['id']]);
  $comment=trim((string)($_POST['comment']??''));
  $text='Автор загрузил новую версию №'.$version;
  if($comment!=='')$text.='. Комментарий автора: '.$comment;

  $hist=$pdo->prepare('INSERT INTO submission_history (submission_id,event_type,from_status,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,?,1)');
  $hist->execute([$sub['id'],'revision_uploaded','revision','screening',$text]);

  $pdo->commit();

  $editorEmail=(string)($config['mail']['editor_email']??'');
  if($editorEmail!==''){
    $body='<p>Автор загрузил новую версию <strong>№'.$version.'</strong> для рукописи <strong>'.mail_escape($sub['public_id']).'</strong>.</p>';
    if($comment!=='')$body.='<p>Комментарий автора: '.nl2br(mail_escape($comment)).'</p>';
    $body.=mail_button(rtrim($config['app']['base_url'],'/').'/editor.html','Открыть редакционную систему');
    send_notification((int)$sub['id'],'revision_uploaded_editor',$editorEmail,'Новая версия '.$sub['public_id'],mail_layout('Получена доработанная версия',$body));
  }

  json_response(['ok'=>true,'version'=>$version,'status'=>'screening'],201);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>$e->getMessage()],500);
}
