<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Backend is not configured'],JSON_UNESCAPED_UNICODE);exit;}
$config=require $configFile;

function db(): PDO {
  static $pdo=null; global $config;
  if($pdo instanceof PDO) return $pdo;
  $dsn=sprintf('mysql:host=%s;dbname=%s;charset=%s',$config['db']['host'],$config['db']['name'],$config['db']['charset']);
  $pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  return $pdo;
}
function json_response(array $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function require_method(string $method): void {if($_SERVER['REQUEST_METHOD']!==$method) json_response(['error'=>'Method not allowed'],405);}
function random_token(int $bytes=32): string {return bin2hex(random_bytes($bytes));}
function clean_filename(string $name): string {$name=preg_replace('/[^\pL\pN._ -]+/u','_',$name)??'file';$name=trim($name," ._-\t\n\r\0\x0B");return mb_substr($name?:'file',0,180);}
function public_id(PDO $pdo): string {$year=date('Y');$stmt=$pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(public_id,'-',-1) AS UNSIGNED)) AS n FROM submissions WHERE public_id LIKE ?");$stmt->execute(["CHGPU-{$year}-%"]);$next=((int)($stmt->fetch()['n']??0))+1;return sprintf('CHGPU-%s-%04d',$year,$next);}
function start_editor_session(): void {global $config;if(session_status()===PHP_SESSION_NONE){session_name($config['app']['session_name']);session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax']);session_start();}}
function require_editor(): array {start_editor_session();if(empty($_SESSION['user_id']))json_response(['error'=>'Unauthorized'],401);$stmt=db()->prepare('SELECT id,email,full_name,role,is_active FROM users WHERE id=? LIMIT 1');$stmt->execute([$_SESSION['user_id']]);$user=$stmt->fetch();if(!$user||!$user['is_active'])json_response(['error'=>'Unauthorized'],401);return $user;}

function require_roles(array $roles): array {
  $user=require_editor();
  if(!in_array($user['role'],$roles,true))json_response(['error'=>'Forbidden'],403);
  return $user;
}


function send_notification(?int $submissionId,string $eventType,string $to,string $subject,string $html): bool {
  global $config;
  $mailCfg=$config['mail']??[];
  $enabled=(bool)($mailCfg['enabled']??false);
  $prefix=(string)($mailCfg['subject_prefix']??'');
  $fullSubject=$prefix.$subject;
  $status='disabled';$error=null;$sent=false;

  if($enabled){
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)){
      $status='failed';$error='Invalid recipient';
    }else{
      $fromEmail=(string)($mailCfg['from_email']??'');
      $fromName=(string)($mailCfg['from_name']??'Известия ЧГПУ');
      $safeFrom=preg_replace('/[\r\n]+/','',$fromEmail);
      $safeName=preg_replace('/[\r\n]+/','',$fromName);
      $headers=[
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: '.mb_encode_mimeheader($safeName,'UTF-8')." <".$safeFrom.">",
        'Reply-To: '.$safeFrom
      ];
      try{
        $sent=@mail($to,mb_encode_mimeheader($fullSubject,'UTF-8'),$html,implode("\r\n",$headers));
        $status=$sent?'sent':'failed';
        if(!$sent)$error='mail() returned false';
      }catch(Throwable $e){
        $status='failed';$error=$e->getMessage();
      }
    }
  }

  try{
    $stmt=db()->prepare('INSERT INTO notification_log (submission_id,event_type,recipient,subject,status,error_text) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$submissionId,$eventType,$to,$fullSubject,$status,$error]);
  }catch(Throwable $e){}

  return $sent;
}

function mail_escape(string $value): string {
  return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function mail_button(string $url,string $label): string {
  $u=mail_escape($url);$l=mail_escape($label);
  return '<p style="margin:24px 0"><a href="'.$u.'" style="display:inline-block;background:#22342f;color:#fff;text-decoration:none;padding:12px 18px;border-radius:3px">'.$l.'</a></p>';
}

function mail_layout(string $title,string $body): string {
  return '<!doctype html><html><body style="margin:0;background:#f3f1eb;font-family:Arial,sans-serif;color:#1a1a1a"><div style="max-width:680px;margin:0 auto;padding:28px"><div style="background:#fff;border:1px solid #d8d5cd;padding:28px"><div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#666">Известия ЧГПУ</div><h2 style="font-family:Georgia,serif;font-weight:500">'.$title.'</h2>'.$body.'<hr style="border:0;border-top:1px solid #ddd;margin:28px 0"><p style="font-size:12px;color:#777">Это автоматическое уведомление редакционной системы.</p></div></div></body></html>';
}


function audit_event(?int $actorUserId,string $eventType,string $entityType,?int $entityId,array $details=[]): void {
  try{
    $ip=(string)($_SERVER['REMOTE_ADDR']??'');
    $stmt=db()->prepare('INSERT INTO system_audit_log (actor_user_id,event_type,entity_type,entity_id,details_json,ip_address) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$actorUserId,$eventType,$entityType,$entityId,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$ip?:null]);
  }catch(Throwable $e){}
}
