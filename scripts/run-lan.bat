@echo off
set "SCRIPT_DIR=%~dp0"
cd /d C:\SparkPair\GarmentsOS
docker compose up -d
docker compose ps
pause
