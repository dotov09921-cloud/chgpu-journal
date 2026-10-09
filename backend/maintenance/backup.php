<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

/** Write completely, including on filesystems that perform partial writes. */
function backup_write($handle, string $text): void {
  while ($text !== '') {
    $written = fwrite($handle, $text);
    if ($written === false || $written === 0) throw new RuntimeException('Не удалось записать резервную копию');
    $text = substr($text, $written);
  }
}

function backup_identifier(string $name): string {
  return '`'.str_replace('`', '``', $name).'`';
}

function backup_metadata(string $path): array {
  clearstatcache(true, $path);
  $size = filesize($path);
  $hash = hash_file('sha256', $path);
  if ($size === false || $hash === false) throw new RuntimeException('Не удалось проверить резервную копию');
  return ['file'=>basename($path), 'size_bytes'=>$size, 'sha256'=>$hash];
}

/** Symlinks are excluded; ZIP entries are always relative to the source root. */
function backup_zip(?string $source, string $target, string $backupDir): bool {
  if (!class_exists('ZipArchive') || !$source || !is_dir($source)) return false;
  $root = realpath($source);
  $excluded = realpath($backupDir);
  if ($root === false || $root === $excluded) return false;
  $zip = new ZipArchive();
  if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) return false;
  try {
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($directory, static function ($entry) use ($excluded): bool {
      if ($entry->isLink()) return false;
      $path = $entry->getRealPath();
      return $path !== false && ($excluded === false || ($path !== $excluded && !str_starts_with($path, $excluded.DIRECTORY_SEPARATOR)));
    });
    $files = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $entry) {
      $path = $entry->getRealPath();
      if ($path === false || !str_starts_with($path, $root.DIRECTORY_SEPARATOR)) continue;
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)+1));
      if (str_contains($relative, '\\') || str_contains($relative, "\0") ||
          preg_match('~(^|/)\\.\\.?(/|$)~', $relative)) throw new RuntimeException('Небезопасный путь в ZIP');
      $ok = $entry->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($path, $relative);
      if (!$ok) throw new RuntimeException('Не удалось добавить файл в ZIP');
    }
    if (!$zip->close()) throw new RuntimeException('Не удалось записать ZIP');
    return is_file($target);
  } catch (Throwable $e) {
    try { $zip->close(); } catch (Throwable $ignored) {}
    @unlink($target);
    return false;
  }
}

function create_backup(?array $actor=null): array {
  global $config;
  $backupDir = rtrim($config['backup']['dir'] ?? (__DIR__.'/../storage/backups'), '/');
  $retention = (int)($config['backup']['retention_days'] ?? 30);
  if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Не удалось создать каталог резервных копий');
  }
  // Serialize runs so retention cannot remove a backup being created.
  $lock = fopen($backupDir.'/.backup.lock', 'c');
  if ($lock === false) throw new RuntimeException('Не удалось открыть блокировку backup');
  if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    throw new RuntimeException('Резервное копирование уже выполняется');
  }
  $handle = null;
  $pdo = null;
  $created = [];
  try {
    $stampTime = time();
    $stamp = date('Ymd-His', $stampTime);
    while (is_file($backupDir.'/db-'.$stamp.'.sql') || is_file($backupDir.'/manifest-'.$stamp.'.json')) {
      $stamp = date('Ymd-His', ++$stampTime);
    }
    $dbFile = $backupDir.'/db-'.$stamp.'.sql';
    $manifestFile = $backupDir.'/manifest-'.$stamp.'.json';
    $handle = fopen($dbFile, 'x');
    if ($handle === false) throw new RuntimeException('Не удалось создать SQL backup');
    $created[] = $dbFile;
    $db = $config['db'];
    // A dedicated connection leaves the API's PDO session untouched.
    $pdo = new PDO(
      'mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',
      $db['user'], $db['pass'],
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
       PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false]
    );
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET SESSION sql_mode=''");
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $statement = $pdo->query('SHOW FULL TABLES');
    $objects = $statement->fetchAll(PDO::FETCH_NUM);
    $statement->closeCursor();
    backup_write($handle, "-- PDO database backup; transactional tables use a consistent snapshot.\nSET NAMES utf8mb4;\nSET @BACKUP_OLD_SQL_MODE=@@SQL_MODE;\nSET SQL_MODE='';\nSET FOREIGN_KEY_CHECKS=0;\n");
    $views = [];
    foreach ($objects as [$name, $kind]) {
      $identifier = backup_identifier($name);
      $statement = $pdo->query('SHOW CREATE TABLE '.$identifier);
      $definition = $statement->fetch(PDO::FETCH_NUM);
      $statement->closeCursor();
      if (!$definition || !isset($definition[1])) throw new RuntimeException('Не удалось прочитать структуру '.$name);
      if ($kind === 'VIEW') {
        $views[] = 'CREATE OR REPLACE '.substr($definition[1], strlen('CREATE ')).";\n";
        continue;
      }
      backup_write($handle, "\nDROP TABLE IF EXISTS ".$identifier.";\n".$definition[1].";\n");
      $statement = $pdo->query('SHOW FULL COLUMNS FROM '.$identifier);
      $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
      $statement->closeCursor();
      $names = [];
      $expressions = [];
      $binary = [];
      foreach ($columns as $column) {
        if (preg_match('/(?:VIRTUAL|STORED) GENERATED/i', $column['Extra'])) continue;
        $quoted = backup_identifier($column['Field']);
        $isBinary = (bool)preg_match('/^(?:binary|varbinary|tinyblob|blob|mediumblob|longblob|bit|geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)\\b/i', $column['Type']);
        $names[] = $quoted;
        $expressions[] = $isBinary ? 'HEX('.$quoted.') AS '.$quoted : $quoted;
        $binary[] = $isBinary;
      }
      // A table consisting only of generated columns needs empty-column inserts.
      $statement = $pdo->query('SELECT '.($expressions ? implode(',', $expressions) : '1').' FROM '.$identifier);
      while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
        $values = [];
        foreach ($names as $index=>$unused) {
          $value = $row[$index];
          if ($value === null) $values[] = 'NULL';
          elseif ($binary[$index]) $values[] = "X'".$value."'";
          else {
            // Quoting numeric strings preserves BIGINT/DECIMAL precision.
            $quoted = $pdo->quote((string)$value, PDO::PARAM_STR);
            if ($quoted === false) throw new RuntimeException('PDO quote failed');
            $values[] = $quoted;
          }
        }
        backup_write($handle, 'INSERT INTO '.$identifier.' ('.implode(',', $names).') VALUES ('.implode(',', $values).");\n");
      }
      $statement->closeCursor();
    }
    foreach ($views as $view) backup_write($handle, "\n".$view);
    backup_write($handle, "\nSET FOREIGN_KEY_CHECKS=1;\nSET SQL_MODE=@BACKUP_OLD_SQL_MODE;\n");
    $pdo->commit();
    if (!fclose($handle)) throw new RuntimeException('Не удалось завершить SQL backup');
    $handle = null;
    $manifest = ['created_at'=>date(DATE_ATOM), 'database'=>backup_metadata($dbFile), 'uploads'=>null, 'archive'=>null];
    foreach (['uploads','archive'] as $type) {
      $source = $type === 'uploads' ? ($config['app']['upload_dir'] ?? null) : ($config['app']['archive_dir'] ?? null);
      if ($type === 'uploads' && !($config['backup']['include_uploads'] ?? true)) continue;
      $target = $backupDir.'/'.$type.'-'.$stamp.'.zip';
      if (backup_zip($source, $target, $backupDir)) {
        $created[] = $target;
        $manifest[$type] = backup_metadata($target);
      }
    }
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $created[] = $manifestFile;
    if (file_put_contents($manifestFile, $json, LOCK_EX) !== strlen($json)) {
      throw new RuntimeException('Не удалось записать manifest');
    }
    if ($retention > 0) {
      $cutoff = time()-($retention*86400);
      foreach (glob($backupDir.'/*') ?: [] as $file) {
        if (!in_array($file, $created, true) && !is_link($file) && is_file($file) &&
            preg_match('/^(?:db-\\d{8}-\\d{6}\\.sql|(?:uploads|archive)-\\d{8}-\\d{6}\\.(?:zip|tar\\.gz)|manifest-\\d{8}-\\d{6}\\.json)$/', basename($file)) &&
            filemtime($file) < $cutoff) @unlink($file);
      }
    }
    // A logging failure must not delete an already completed backup.
    $created = [];
    if ($actor) audit_event((int)$actor['id'], 'backup_created', 'system', null, ['manifest'=>basename($manifestFile)]);
    return $manifest;
  } catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($handle)) fclose($handle);
    foreach ($created as $file) @unlink($file);
    throw $e;
  } finally {
    flock($lock, LOCK_UN);
    fclose($lock);
  }
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
  if(PHP_SAPI==='cli'){
    try{
      $manifest=create_backup(null);
      echo json_encode(['ok'=>true,'manifest'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
      exit(0);
    }catch(Throwable $e){
      fwrite(STDERR,$e->getMessage().PHP_EOL);
      exit(1);
    }
  }
  json_response(['error'=>'Run this maintenance script from CLI or the admin endpoint'],403);
}
