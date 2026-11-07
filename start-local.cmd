@echo off
setlocal

set "PHP_EXE=%~dp0..\..\Extensions\php\php8.2.9nts\php.exe"
if not exist "%PHP_EXE%" (
  echo PHP not found: %PHP_EXE%
  exit /b 1
)

"%PHP_EXE%" -S 127.0.0.1:8000 -t "%~dp0api\public" "%~dp0dev-router.php"
