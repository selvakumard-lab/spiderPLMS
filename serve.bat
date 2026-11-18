@echo off
rem Starts Spider PLMS on http://127.0.0.1:8010 (port 8000 is used by another local project).
cd /d "%~dp0"
"D:\php84\php.exe" artisan serve --host=127.0.0.1 --port=8010
