<?php
return [
  'db'=>['host'=>'localhost','name'=>'chgpu_journal','user'=>'chgpu_user','pass'=>'CHANGE_ME','charset'=>'utf8mb4'],
  'app'=>[
    'base_url'=>'https://journal.example.ru',
    'upload_dir'=>__DIR__.'/storage/uploads',
    'max_upload_bytes'=>25*1024*1024,
    'session_name'=>'chgpu_editor_session'
  ],
  'security'=>[
    'app_secret'=>'CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
    'login_max_attempts'=>5,
    'login_window_minutes'=>15,
    'login_block_minutes'=>15,
    'submit_max_per_hour'=>5,
    'revision_max_per_hour'=>10,
    'min_form_seconds'=>3,
    'max_total_upload_bytes'=>40*1024*1024
  ],
  'backup'=>[
    'dir'=>__DIR__.'/storage/backups',
    'retention_days'=>30,
    'include_uploads'=>true
  ],
  'mail'=>[
    'enabled'=>false,
    'from_email'=>'journal@example.ru',
    'from_name'=>'Известия ЧГПУ',
    'editor_email'=>'journal@example.ru',
    'subject_prefix'=>'[Известия ЧГПУ] '
  ]
];
