<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

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


function record_system_error(string $level,string $message,array $context=[]): void {
  global $config;
  $entry=[
    'time'=>date(DATE_ATOM),
    'level'=>$level,
    'message'=>$message,
    'context'=>$context,
    'uri'=>(string)($_SERVER['REQUEST_URI']??'CLI')
  ];

  $logDir=__DIR__.'/storage';
  if(!is_dir($logDir))@mkdir($logDir,0750,true);
  @file_put_contents($logDir.'/system.log',json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);

  try{
    $stmt=db()->prepare('INSERT INTO system_errors (level,message,context_json,request_uri) VALUES (?,?,?,?)');
    $stmt->execute([$level,$message,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$entry['uri']]);
  }catch(Throwable $e){}
}

register_shutdown_function(function(): void {
  $e=error_get_last();
  if(!$e)return;
  if(in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    record_system_error('fatal',(string)$e['message'],['file'=>$e['file'],'line'=>$e['line']]);
  }
});


function security_secret(): string {
  global $config;
  $secret=(string)($config['security']['app_secret']??'');
  if($secret===''||str_starts_with($secret,'CHANGE_ME')){
    record_system_error('warning','Security app_secret is not configured');
  }
  return $secret;
}

function client_ip(): string {
  return (string)($_SERVER['REMOTE_ADDR']??'0.0.0.0');
}

function security_subject_hash(string $subject): string {
  return hash_hmac('sha256',$subject,security_secret()?:'fallback-not-for-production');
}

function validate_same_origin(): void {
  global $config;
  if(PHP_SAPI==='cli')return;
  $method=$_SERVER['REQUEST_METHOD']??'GET';
  if(in_array($method,['GET','HEAD','OPTIONS'],true))return;

  $base=(string)($config['app']['base_url']??'');
  $host=parse_url($base,PHP_URL_HOST);
  if(!$host)return;

  foreach(['HTTP_ORIGIN','HTTP_REFERER'] as $key){
    if(empty($_SERVER[$key]))continue;
    $h=parse_url((string)$_SERVER[$key],PHP_URL_HOST);
    if($h&&strcasecmp($h,$host)!==0){
      record_system_error('warning','Blocked cross-origin request',['origin'=>(string)$_SERVER[$key],'ip'=>client_ip()]);
      json_response(['error'=>'Cross-origin request blocked'],403);
    }
    return;
  }
}

function csrf_token(): string {
  start_editor_session();
  if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=random_token(32);
  return (string)$_SESSION['csrf_token'];
}

function require_csrf(): void {
  start_editor_session();
  $sent=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['_csrf']??''));
  $known=(string)($_SESSION['csrf_token']??'');
  if($known===''||$sent===''||!hash_equals($known,$sent)){
    record_system_error('warning','CSRF validation failed',['ip'=>client_ip()]);
    json_response(['error'=>'Security token invalid'],403);
  }
}

function rate_limit_check(string $action,string $subject,int $maxAttempts,int $windowSeconds,int $blockSeconds): void {
  $pdo=db();
  $hash=security_subject_hash($subject);
  $stmt=$pdo->prepare('SELECT attempts,window_started_at,blocked_until FROM security_rate_limits WHERE action_key=? AND subject_hash=? LIMIT 1');
  $stmt->execute([$action,$hash]);
  $row=$stmt->fetch();
  $now=time();

  if($row&&$row['blocked_until']&&strtotime($row['blocked_until'])>$now){
    json_response(['error'=>'Слишком много попыток. Повторите позже.'],429);
  }

  if(!$row||strtotime($row['window_started_at'])<=($now-$windowSeconds)){
    $up=$pdo->prepare('INSERT INTO security_rate_limits (action_key,subject_hash,attempts,window_started_at,blocked_until) VALUES (?,?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE attempts=1,window_started_at=NOW(),blocked_until=NULL');
    $up->execute([$action,$hash]);
    return;
  }

  $attempts=(int)$row['attempts']+1;
  $blocked=$attempts>$maxAttempts;
  $up=$pdo->prepare('UPDATE security_rate_limits SET attempts=?,blocked_until=? WHERE action_key=? AND subject_hash=?');
  $up->execute([$attempts,$blocked?date('Y-m-d H:i:s',$now+$blockSeconds):null,$action,$hash]);
  if($blocked){
    audit_event(null,'rate_limit_blocked','security',null,['action'=>$action,'ip'=>client_ip()]);
    json_response(['error'=>'Слишком много попыток. Повторите позже.'],429);
  }
}

function rate_limit_reset(string $action,string $subject): void {
  $stmt=db()->prepare('DELETE FROM security_rate_limits WHERE action_key=? AND subject_hash=?');
  $stmt->execute([$action,security_subject_hash($subject)]);
}

function enforce_upload_budget(): void {
  global $config;
  $max=(int)($config['security']['max_total_upload_bytes']??(40*1024*1024));
  $total=0;
  foreach($_FILES as $file){
    if(is_array($file['size']??null)){
      foreach($file['size'] as $s)$total+=(int)$s;
    }else{
      $total+=(int)($file['size']??0);
    }
  }
  if($total>$max)json_response(['error'=>'Суммарный размер файлов превышает допустимый'],413);
}

function form_guard_check(string $startedAt,string $honeypot=''): void {
  global $config;
  if(trim($honeypot)!=='')json_response(['error'=>'Spam rejected'],422);
  $min=(int)($config['security']['min_form_seconds']??3);
  $ts=(int)$startedAt;
  if($ts<=0||time()-$ts<$min)json_response(['error'=>'Форма отправлена слишком быстро'],422);
}

validate_same_origin();
