<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$config=[
  'db'=>[
    'host'=>getenv('DB_HOST')?:'db',
    'name'=>getenv('DB_NAME')?:'chgpu_journal',
    'user'=>getenv('DB_USER')?:'chgpu',
    'pass'=>getenv('DB_PASS')?:'chgpu_dev_password',
    'charset'=>'utf8mb4'
  ],
  'app'=>[
    'base_url'=>getenv('APP_URL')?:'http://localhost:8080',
    'upload_dir'=>$root.'/backend/storage/uploads',
    'archive_dir'=>$root.'/backend/storage/archive',
    'max_upload_bytes'=>25*1024*1024,
    'session_name'=>'chgpu_editor_session'
  ],
  'security'=>[
    'app_secret'=>getenv('APP_SECRET')?:'local-dev-secret-change-before-production-2026',
    'login_max_attempts'=>5,
    'login_window_minutes'=>15,
    'login_block_minutes'=>15,
    'submit_max_per_hour'=>50,
    'revision_max_per_hour'=>50,
    'min_form_seconds'=>1,
    'max_total_upload_bytes'=>40*1024*1024
  ],
  'backup'=>[
    'dir'=>$root.'/backend/storage/backups',
    'retention_days'=>30,
    'include_uploads'=>true
  ],
  'mail'=>[
    'enabled'=>true,
    'from_email'=>getenv('MAIL_FROM')?:'journal@chgpu.local',
    'from_name'=>'Известия ЧГПУ — тест',
    'editor_email'=>getenv('MAIL_EDITOR')?:'editor@chgpu.local',
    'subject_prefix'=>'[LOCAL TEST] '
  ]
];

file_put_contents($root.'/backend/config.php',"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX);
