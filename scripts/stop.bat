@echo off
set "SCRIPT_DIR=%~dp0"
echo Stopping Docker services...
cd /d C:\SparkPair\GarmentsOS
docker compose down
if errorlevel 1 (
    echo Failed to stop Docker services.
    pause
    exit /b 1
)
exit /b 0
