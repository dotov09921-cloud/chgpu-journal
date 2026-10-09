<?php
declare(strict_types=1);
// Shared implementation; no side effects when included.
function legacy_manifest(): array {
    $data=json_decode((string)file_get_contents(dirname(__DIR__,2).'/migration/import-ready.json'),true,512,JSON_THROW_ON_ERROR);
    $items=$data['issues']??[];
    if(count($items)!==51)throw new RuntimeException('Ожидался manifest из 51 выпуска');
    $keys=[];$files=[];
    foreach($items as $i){
        if(empty($i['legacy_key'])||isset($keys[$i['legacy_key']])||!in_array($i['migration_status'],['ready','needs_recovery','duplicate_conflict'],true))throw new RuntimeException('Некорректный manifest');
        $keys[$i['legacy_key']]=true;
        if($i['migration_status']==='ready'){
            $name=$i['local_filename'];$path=$i['year'].'/'.$name;
            if($name!==basename($name)||strpbrk($name,"/\\\0")!==false||isset($files[$path])||!preg_match('/^[a-f0-9]{64}$/D',$i['sha256'])||(int)$i['size_bytes']<5||(int)$i['size_bytes']>100*1024*1024)throw new RuntimeException('Некорректные данные PDF');
            legacy_url($i['source_url']);$files[$path]=true;
        }
    }
    return $items;
}
function legacy_url(string $url): string {
    $p=parse_url($url);
    if(!$p||($p['scheme']??'')!=='https'||strtolower($p['host']??'')!=='chspu.ru'||isset($p['user'])||isset($p['pass'])||(isset($p['port'])&&$p['port']!==443))throw new RuntimeException('Разрешён только https://chspu.ru');
    // Encode Unicode and spaces in the legacy path, preserving existing escapes.
    return 'https://chspu.ru'.implode('/',array_map(static fn($s)=>rawurlencode(rawurldecode($s)),explode('/',$p['path']??'/'))).(isset($p['query'])?'?'.$p['query']:'');
}
function legacy_row(PDO $pdo,string $key): ?array {
    $q=$pdo->prepare('SELECT * FROM issues WHERE legacy_key=?');$q->execute([$key]);return $q->fetch()?:null;
}
function legacy_status(PDO $pdo,array $items): array {
    $counts=array_fill_keys(['ready','imported','failed','needs_recovery','duplicate_conflict','missing','protected'],0);
    foreach($items as $i){
        $row=legacy_row($pdo,$i['legacy_key']);
        if(in_array($i['migration_status'],['needs_recovery','duplicate_conflict'],true))$counts[$i['migration_status']]++;
        elseif(!$row)$counts['missing']++;
        elseif(in_array($row['migration_status'],['ready','imported','failed'],true))$counts[$row['migration_status']]++;
        else $counts['protected']++;
    }
    $last=$pdo->query("SELECT event_type,details_json,created_at FROM system_audit_log WHERE event_type IN ('legacy_migration_metadata','legacy_migration_pdf_batch') ORDER BY id DESC LIMIT 1")->fetch()?:null;
    return ['total'=>count($items),'counts'=>$counts,'curl_available'=>function_exists('curl_init'),'last_action'=>$last];
}
function legacy_metadata(PDO $pdo,array $items): array {
    $result=['inserted'=>0,'existing'=>0,'conflicts'=>[]];
    $pdo->beginTransaction();
    try{
        foreach($items as $i){
            if(legacy_row($pdo,$i['legacy_key'])){$result['existing']++;continue;}
            try{
                $q=$pdo->prepare('INSERT INTO issues (year,series,number,title,legacy_key,legacy_source_url,legacy_label,migration_status,legacy_sha256,legacy_pages,legacy_size_bytes,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)');
                $q->execute([(int)$i['year'],$i['series'],$i['number'],$i['legacy_label'],$i['legacy_key'],$i['source_url'],$i['legacy_label'],$i['migration_status'],$i['sha256']?:null,$i['pages']?:null,$i['size_bytes']?:null]);
                $result['inserted']++;
            }catch(PDOException $e){
                if(($e->errorInfo[1]??0)!==1062)throw $e;
                // Both unique keys protect existing native and unrelated legacy issues.
                $result['conflicts'][]=$i['legacy_key'];
            }
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return $result;
}
function legacy_valid_pdf(string $file,array $i): bool {
    return is_file($file)&&filesize($file)===(int)$i['size_bytes']&&file_get_contents($file,false,null,0,5)==='%PDF-'&&hash_equals($i['sha256'],(string)hash_file('sha256',$file));
}
function legacy_download(array $i,string $target): void {
    if(!function_exists('curl_init'))throw new RuntimeException('Для импорта необходим PHP cURL');
    $tmp=tempnam(dirname($target),'.legacy-');
    if($tmp===false)throw new RuntimeException('Не удалось создать временный файл');
    $fp=null;$ch=null;
    try{
        $fp=fopen($tmp,'wb');if(!$fp)throw new RuntimeException('Ошибка записи');
        $ch=curl_init(legacy_url($i['source_url']));$bytes=0;$expected=(int)$i['size_bytes'];
        curl_setopt_array($ch,[
            CURLOPT_FOLLOWLOCATION=>false, // Never forward the SSL exception to redirects.
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18,
            CURLOPT_LOW_SPEED_LIMIT=>1024,CURLOPT_LOW_SPEED_TIME=>8,
            CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,
            CURLOPT_USERAGENT=>'CHGPU-Archive-Migration/2.0',
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use($fp,&$bytes,$expected): int {
                $bytes+=strlen($chunk);if($bytes>$expected)return 0;
                return (int)fwrite($fp,$chunk);
            }
        ]);
        $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);$ch=null;fclose($fp);$fp=null;
        if($ok===false||$code!==200)throw new RuntimeException('Скачивание не завершено (HTTP '.$code.'); повторите партию');
        clearstatcache(true,$tmp);
        if(!legacy_valid_pdf($tmp,$i))throw new RuntimeException('PDF не прошёл проверку сигнатуры, размера или SHA-256');
        if(!rename($tmp,$target))throw new RuntimeException('Не удалось сохранить PDF');
        chmod($target,0640);
    }finally{
        if($ch!==null)curl_close($ch);if(is_resource($fp))fclose($fp);if(is_file($tmp))unlink($tmp);
    }
}
function legacy_batch(PDO $pdo,array $items,string $archiveDir): array {
    $result=['imported'=>[],'failed'=>[]];
    // Ready first; retries cannot starve the remaining queue. One PDF per request.
    foreach(['ready','failed'] as $status){
        foreach($items as $i){
            if($i['migration_status']!=='ready')continue;
            $row=legacy_row($pdo,$i['legacy_key']);
            if(!$row||$row['migration_status']!==$status)continue;
            try{
                $dir=$archiveDir.'/'.(int)$i['year'];
                if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Ошибка создания папки архива');
                $target=$dir.'/'.$i['local_filename'];
                if(!legacy_valid_pdf($target,$i))legacy_download($i,$target);
                $q=$pdo->prepare("UPDATE issues SET pdf_path=?,migration_status='imported' WHERE id=? AND legacy_key=? AND migration_status IN ('ready','failed')");
                $q->execute(['/'.(int)$i['year'].'/'.$i['local_filename'],$row['id'],$i['legacy_key']]);
                if($q->rowCount()!==1)throw new RuntimeException('Выпуск изменён другим действием');
                $result['imported'][]=$i['legacy_key'];
            }catch(Throwable $e){
                $pdo->prepare("UPDATE issues SET migration_status='failed' WHERE id=? AND legacy_key=? AND migration_status IN ('ready','failed')")->execute([$row['id'],$i['legacy_key']]);
                $result['failed'][]=['legacy_key'=>$i['legacy_key'],'error'=>$e->getMessage()];
            }
            return $result;
        }
    }
    return $result;
}
