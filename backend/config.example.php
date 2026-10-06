<?php
return [
  'db'=>['host'=>'localhost','name'=>'chgpu_journal','user'=>'chgpu_user','pass'=>'CHANGE_ME','charset'=>'utf8mb4'],
  'app'=>[
    'base_url'=>'https://journal.example.ru',
    'upload_dir'=>__DIR__.'/storage/uploads',
    'max_upload_bytes'=>25*1024*1024,
    'session_name'=>'chgpu_editor_session'
  ],
  'mail'=>[
    'enabled'=>false,
    'from_email'=>'journal@example.ru',
    'from_name'=>'Известия ЧГПУ',
    'editor_email'=>'journal@example.ru',
    'subject_prefix'=>'[Известия ЧГПУ] '
  ]
];
