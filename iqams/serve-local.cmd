@echo off
setlocal
cd /d "%~dp0public"
if not exist "build\manifest.json" (
    echo Run npm.cmd run build from the iqams folder first.
    exit /b 1
)
if exist "hot" (
    echo Stop npm run dev before using the local performance server.
    echo Then move public\hot out of public so Laravel uses the built assets.
    exit /b 1
)
echo IQAMS local server: http://127.0.0.1:8081
echo Keep this window open. Press Ctrl+C to stop.
php -d zend_extension=opcache -d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.memory_consumption=128 -d opcache.max_accelerated_files=20000 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=2 -S 127.0.0.1:8081 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
