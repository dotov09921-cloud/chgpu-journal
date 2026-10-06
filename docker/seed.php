<?php
declare(strict_types=1);

$config=require dirname(__DIR__).'/backend/config.php';
$db=$config['db'];
$pdo=new PDO(
  sprintf('mysql:host=%s;dbname=%s;charset=%s',$db['host'],$db['name'],$db['charset']),
  $db['user'],
  $db['pass'],
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);

$users=[
  ['admin@chgpu.local','Локальный администратор','admin','TestAdmin2026!'],
  ['editor@chgpu.local','Локальный редактор','editor','TestEditor2026!'],
  ['reviewer@chgpu.local','Локальный рецензент','reviewer','TestReviewer2026!']
];

$stmt=$pdo->prepare("INSERT IGNORE INTO users
  (email,password_hash,full_name,role,is_active,must_set_password)
  VALUES (?,?,?,?,1,0)");

foreach($users as [$email,$name,$role,$password]){
  $stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name,$role]);
}

echo "Local test users are ready.\n";
