# Перенос рабочего стенда CHGPU на другой ноутбук

Исходный код хранится в GitHub, но локальная MySQL-база и загруженные DOCX/PDF не хранятся в репозитории.

## На старом ноутбуке

Из корня проекта:

```powershell
powershell -ExecutionPolicy Bypass -File .\docker\export-workstation.ps1
```

На рабочем столе появится файл вида:

`CHGPU-transfer-YYYYMMDD-HHMMSS.zip`

Скопируйте его на новый ноутбук через защищённый носитель/канал. Не загружайте этот ZIP в GitHub.

## На новом ноутбуке

1. Установите Docker Desktop.
2. Скачайте актуальный проект из GitHub.
3. В корне проекта выполните:

```powershell
powershell -ExecutionPolicy Bypass -File .\docker\import-workstation.ps1 -PackagePath "C:\путь\CHGPU-transfer-YYYYMMDD-HHMMSS.zip"
```

После восстановления:
- сайт: http://localhost:8080
- Mailpit: http://localhost:8025

Скрипт проверяет SHA-256 перед восстановлением.
