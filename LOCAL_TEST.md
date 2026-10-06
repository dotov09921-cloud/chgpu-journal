# Локальный тест «Известия ЧГПУ»

## Запуск

На Windows нужен Docker Desktop.

Открой PowerShell в папке проекта:

```powershell
docker compose up -d --build
```

Адреса:
- сайт: http://localhost:8080
- вход: http://localhost:8080/login.html
- Mailpit: http://localhost:8025
- health: http://localhost:8080/backend/health.php

## Тестовые пользователи

Администратор: `admin@chgpu.local` / `TestAdmin2026!`

Редактор: `editor@chgpu.local` / `TestEditor2026!`

Рецензент: `reviewer@chgpu.local` / `TestReviewer2026!`

## Проверка

1. Подать статью с DOCX.
2. Проверить письма в Mailpit.
3. Войти редактором.
4. Назначить рецензента.
5. Войти рецензентом и отправить заключение.
6. Вернуть статью на доработку.
7. Загрузить Версию 2.
8. Принять статью.
9. Создать выпуск.
10. Опубликовать статью.
11. Проверить публичную страницу.
12. Администратором проверить пользователей, аудит, backup и целостность.

## Импорт старого архива

```powershell
docker compose exec web php backend/maintenance/import-legacy.php
```

## Остановка

```powershell
docker compose down
```

## Полный сброс

```powershell
docker compose down -v
docker compose up -d --build
```
