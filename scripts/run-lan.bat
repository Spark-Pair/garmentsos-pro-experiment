@echo off
set "SCRIPT_DIR=%~dp0"
echo Starting Docker services...
cd /d C:\SparkPair\GarmentsOS
docker compose up -d
if errorlevel 1 (
    echo Failed to start Docker services.
    pause
    exit /b 1
)
docker compose ps
exit /b 0
