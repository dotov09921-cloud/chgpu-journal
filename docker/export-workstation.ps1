param(
  [string]$OutputDirectory = [Environment]::GetFolderPath('Desktop')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\docker-compose.yml')) {
  throw 'Запустите скрипт из корня проекта chgpu-journal.'
}

Write-Host '1/4 Проверяю Docker...'
docker compose ps | Out-Null

Write-Host '2/4 Создаю резервную копию базы и файлов...'
docker compose exec -T web php backend/maintenance/backup.php
if ($LASTEXITCODE -ne 0) {
  throw 'Не удалось создать резервную копию.'
}

$backupDir = Join-Path (Get-Location) 'backend\storage\backups'
$manifest = Get-ChildItem $backupDir -Filter 'manifest-*.json' |
  Sort-Object LastWriteTime -Descending |
  Select-Object -First 1

if (-not $manifest) {
  throw 'Файл manifest резервной копии не найден.'
}

$meta = Get-Content $manifest.FullName -Raw | ConvertFrom-Json
$stamp = [IO.Path]::GetFileNameWithoutExtension($manifest.Name).Replace('manifest-','')
$staging = Join-Path $env:TEMP ('chgpu-transfer-' + $stamp)

if (Test-Path $staging) {
  Remove-Item $staging -Recurse -Force
}
New-Item -ItemType Directory -Path $staging | Out-Null

Copy-Item $manifest.FullName $staging
Copy-Item (Join-Path $backupDir $meta.database.file) $staging
if ($meta.uploads -and $meta.uploads.file) {
  Copy-Item (Join-Path $backupDir $meta.uploads.file) $staging
}
if ($meta.archive -and $meta.archive.file) {
  Copy-Item (Join-Path $backupDir $meta.archive.file) $staging
}

@"
CHGPU JOURNAL — пакет переноса рабочего состояния

Содержимое:
- база данных MySQL/MariaDB;
- загруженные авторские файлы;
- архив выпусков, если он уже импортирован;
- manifest с SHA-256 для проверки целостности.

Исходный код в пакет не включён: актуальная версия хранится в GitHub.
Не загружайте этот ZIP в GitHub и не пересылайте посторонним.
"@ | Set-Content (Join-Path $staging 'README.txt') -Encoding UTF8

if (-not (Test-Path $OutputDirectory)) {
  New-Item -ItemType Directory -Path $OutputDirectory | Out-Null
}

$zip = Join-Path $OutputDirectory ('CHGPU-transfer-' + $stamp + '.zip')
if (Test-Path $zip) {
  Remove-Item $zip -Force
}

Write-Host '3/4 Проверяю SHA-256...'
$checks = @(
  @{ File = $meta.database.file; Sha = $meta.database.sha256 }
)
if ($meta.uploads -and $meta.uploads.file) {
  $checks += @{ File = $meta.uploads.file; Sha = $meta.uploads.sha256 }
}
if ($meta.archive -and $meta.archive.file) {
  $checks += @{ File = $meta.archive.file; Sha = $meta.archive.sha256 }
}
foreach ($item in $checks) {
  $actual = (Get-FileHash (Join-Path $staging $item.File) -Algorithm SHA256).Hash.ToLower()
  if ($actual -ne ([string]$item.Sha).ToLower()) {
    throw ('Ошибка проверки SHA-256: ' + $item.File)
  }
}

Write-Host '4/4 Упаковываю перенос...'
Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $zip -CompressionLevel Optimal
Remove-Item $staging -Recurse -Force

Write-Host ''
Write-Host 'ГОТОВО.'
Write-Host ('Пакет переноса: ' + $zip)
Write-Host 'Скопируйте этот ZIP на новый ноутбук. Не загружайте его в GitHub.'
