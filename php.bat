@echo off
rem Spider PLMS needs PHP >= 8.4 (Laravel 13). XAMPP's PHP on PATH is 8.2, so when you run
rem "php ..." from this folder in cmd.exe, this shim runs PHP 8.4 instead (cmd checks the
rem current directory before PATH). Delete this file once PHP 8.4 is first on your PATH.
"D:\php84\php.exe" %*
