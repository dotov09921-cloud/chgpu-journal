<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
require_method('POST');

$required=['author_name','author_email','organization','title','section','abstract','keywords'];
foreach($required as $field){
  if(!isset($_POST[$field])||trim((string)$_POST[$field])==='')json_response(['error'=>"Поле {$field} обязательно"],422);
}
if(!filter_var($_POST['author_email'],FILTER_VALIDATE_EMAIL))json_response(['error'=>'Некорректный e-mail'],422);
if(empty($_FILES['manuscript'])||$_FILES['manuscript']['error']!==UPLOAD_ERR_OK)json_response(['error'=>'Необходимо приложить DOC/DOCX рукопись'],422);

$pdo=db();
$pdo->beginTransaction();
try{
  $publicId=public_id($pdo);$trackingToken=random_token();
  $stmt=$pdo->prepare('INSERT INTO submissions (public_id,author_name,author_email,organization,orcid,title,section,language,abstract,keywords,coauthors,status,tracking_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$publicId,trim($_POST['author_name']),trim($_POST['author_email']),trim($_POST['organization']),trim($_POST['orcid']??''),trim($_POST['title']),trim($_POST['section']),trim($_POST['language']??'Русский'),trim($_POST['abstract']),trim($_POST['keywords']),trim($_POST['coauthors']??''),'new',$trackingToken]);
  $submissionId=(int)$pdo->lastInsertId();

  $uploadDir=rtrim($config['app']['upload_dir'],'/\\').'/'.date('Y').'/'.$publicId;
  if(!is_dir($uploadDir)&&!mkdir($uploadDir,0750,true)&&!is_dir($uploadDir))throw new RuntimeException('Не удалось создать каталог загрузки');

  $allowed=[
    'manuscript'=>['extensions'=>['doc','docx'],'mimes'=>['application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream']],
    'pdf'=>['extensions'=>['pdf'],'mimes'=>['application/pdf','application/octet-stream']]
  ];

  foreach(['manuscript','pdf'] as $field){
    if(empty($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE)continue;
    if($_FILES[$field]['error']!==UPLOAD_ERR_OK)throw new RuntimeException("Ошибка загрузки {$field}");
    if((int)$_FILES[$field]['size']>(int)$config['app']['max_upload_bytes'])throw new RuntimeException('Файл слишком большой');

    $original=clean_filename($_FILES[$field]['name']);
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed[$field]['extensions'],true))throw new RuntimeException('Недопустимое расширение файла');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($_FILES[$field]['tmp_name']);
    if(!in_array($mime,$allowed[$field]['mimes'],true))throw new RuntimeException('Недопустимый тип файла');

    $stored=$field.'-'.random_token(10).'.'.$ext;
    $target=$uploadDir.'/'.$stored;
    if(!move_uploaded_file($_FILES[$field]['tmp_name'],$target))throw new RuntimeException('Не удалось сохранить файл');

    $sha=hash_file('sha256',$target);
    $relative=str_replace(rtrim($config['app']['upload_dir'],'/\\'),'',$target);
    $ins=$pdo->prepare('INSERT INTO submission_files (submission_id,version_no,file_type,original_name,stored_name,relative_path,mime_type,size_bytes,sha256) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->execute([$submissionId,1,$field,$original,$stored,$relative,$mime,filesize($target),$sha]);
  }

  $hist=$pdo->prepare('INSERT INTO submission_history (submission_id,event_type,to_status,comment,is_visible_to_author) VALUES (?,?,?,?,1)');
  $hist->execute([$submissionId,'submitted','new','Рукопись поступила в редакцию']);
  $pdo->commit();

  $trackingUrl=rtrim($config['app']['base_url'],'/').'/track.html?id='.urlencode($publicId).'&token='.urlencode($trackingToken);
  $authorBody='<p>Здравствуйте, '.mail_escape(trim($_POST['author_name'])).'.</p><p>Ваша рукопись <strong>'.mail_escape(trim($_POST['title'])).'</strong> зарегистрирована под номером <strong>'.$publicId.'</strong>.</p>'.mail_button($trackingUrl,'Отслеживать статус');
  send_notification($submissionId,'submission_received_author',trim($_POST['author_email']),'Рукопись '.$publicId.' принята системой',mail_layout('Рукопись зарегистрирована',$authorBody));

  $editorEmail=(string)($config['mail']['editor_email']??'');
  if($editorEmail!==''){
    $editorBody='<p>Поступила новая рукопись <strong>'.$publicId.'</strong>.</p><p><strong>'.mail_escape(trim($_POST['title'])).'</strong><br>Автор: '.mail_escape(trim($_POST['author_name'])).'</p>';
    send_notification($submissionId,'submission_received_editor',$editorEmail,'Новая рукопись '.$publicId,mail_layout('Новая рукопись',$editorBody));
  }

  json_response([
    'ok'=>true,
    'public_id'=>$publicId,
    'tracking_token'=>$trackingToken,
    'tracking_url'=>$trackingUrl
  ],201);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['error'=>'Не удалось сохранить рукопись','detail'=>$e->getMessage()],500);
}
