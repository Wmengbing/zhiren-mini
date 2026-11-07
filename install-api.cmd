@echo off
setlocal

set "PHP_DIR=%~dp0..\..\Extensions\php\php8.2.9nts"
set "PHP_EXE=%PHP_DIR%\php.exe"
set "COMPOSER_PHAR=%~dp0..\..\Extensions\composer2.5.8\composer.phar"

if not exist "%PHP_EXE%" (
  echo PHP not found: %PHP_EXE%
  exit /b 1
)
if not exist "%COMPOSER_PHAR%" (
  echo Composer not found: %COMPOSER_PHAR%
  exit /b 1
)
if not exist "%PHP_DIR%\ext\php_zip.dll" (
  echo PHP zip extension not found: %PHP_DIR%\ext\php_zip.dll
  exit /b 1
)

set "PATH=%PHP_DIR%;%PATH%"
if exist "D:\Git\cmd\git.exe" set "PATH=D:\Git\cmd;%PATH%"
set "COMPOSER_CACHE_DIR=%~dp0api\.composer-cache"

pushd "%~dp0api" || exit /b 1
"%PHP_EXE%" -d extension=php_zip.dll "%COMPOSER_PHAR%" install --prefer-dist --no-interaction
set "RESULT=%ERRORLEVEL%"
popd
exit /b %RESULT%
