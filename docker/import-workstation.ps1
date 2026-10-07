param(
  [Parameter(Mandatory=$true)]
  [string]$PackagePath
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\docker-compose.yml')) {
  throw 'Запустите скрипт из корня проекта chgpu-journal.'
}
if (-not (Test-Path $PackagePath)) {
  throw ('Пакет не найден: ' + $PackagePath)
}

$temp = Join-Path $env:TEMP ('chgpu-restore-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp | Out-Null

try {
  Expand-Archive -Path $PackagePath -DestinationPath $temp -Force
  $manifest = Get-ChildItem $temp -Filter 'manifest-*.json' | Select-Object -First 1
  if (-not $manifest) {
    throw 'В пакете отсутствует manifest.'
  }
  $meta = Get-Content $manifest.FullName -Raw | ConvertFrom-Json

  Write-Host '1/5 Проверяю SHA-256...'
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
    $filePath = Join-Path $temp $item.File
    if (-not (Test-Path $filePath)) {
      throw ('В пакете отсутствует файл: ' + $item.File)
    }
    $actual = (Get-FileHash $filePath -Algorithm SHA256).Hash.ToLower()
    if ($actual -ne ([string]$item.Sha).ToLower()) {
      throw ('Ошибка SHA-256: ' + $item.File)
    }
  }

  Write-Host '2/5 Запускаю локальную систему...'
  docker compose up -d --build
  if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose не запустился.'
  }

  Write-Host '3/5 Восстанавливаю базу данных...'
  $dbFile = Join-Path $temp $meta.database.file
  docker compose cp $dbFile db:/tmp/chgpu-restore.sql
  if ($LASTEXITCODE -ne 0) {
    throw 'Не удалось скопировать SQL в контейнер.'
  }
  docker compose exec -T db sh -lc "mariadb -uchgpu -pchgpu_dev_password chgpu_journal < /tmp/chgpu-restore.sql && rm -f /tmp/chgpu-restore.sql"
  if ($LASTEXITCODE -ne 0) {
    throw 'Не удалось восстановить базу данных.'
  }

  Write-Host '4/5 Восстанавливаю загруженные файлы...'
  $storage = Join-Path (Get-Location) 'backend\storage'
  New-Item -ItemType Directory -Path $storage -Force | Out-Null

  if ($meta.uploads -and $meta.uploads.file) {
    $uploadsDir = Join-Path $storage 'uploads'
    if (Test-Path $uploadsDir) {
      Get-ChildItem $uploadsDir -Force | Where-Object { $_.Name -ne '.gitkeep' } | Remove-Item -Recurse -Force
    }
    tar -xzf (Join-Path $temp $meta.uploads.file) -C $storage
    if ($LASTEXITCODE -ne 0) {
      throw 'Не удалось восстановить uploads.'
    }
  }

  if ($meta.archive -and $meta.archive.file) {
    $archiveDir = Join-Path $storage 'archive'
    if (Test-Path $archiveDir) {
      Get-ChildItem $archiveDir -Force | Where-Object { $_.Name -ne '.gitkeep' } | Remove-Item -Recurse -Force
    }
    tar -xzf (Join-Path $temp $meta.archive.file) -C $storage
    if ($LASTEXITCODE -ne 0) {
      throw 'Не удалось восстановить archive.'
    }
  }

  Write-Host '5/5 Перезапускаю сайт...'
  docker compose restart web
  if ($LASTEXITCODE -ne 0) {
    throw 'Не удалось перезапустить web.'
  }

  Write-Host ''
  Write-Host 'ГОТОВО.'
  Write-Host 'Сайт: http://localhost:8080'
  Write-Host 'Тестовая почта: http://localhost:8025'
} finally {
  if (Test-Path $temp) {
    Remove-Item $temp -Recurse -Force
  }
}
