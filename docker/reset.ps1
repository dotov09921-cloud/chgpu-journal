$ErrorActionPreference = "Stop"
Write-Host "WARNING: local database and Docker volume will be deleted."
docker compose down -v
docker compose up -d --build
Write-Host "Local CHGPU environment reset complete."
