@echo off
title Aviva HealthCare — Local Server
echo.
echo =========================================
echo   Aviva HealthCare — Starting Services
echo =========================================
echo.

set PHP=D:\xampp\php\php.exe
set ROOT=%~dp0

echo [1/3] Warming caches...
%PHP% "%ROOT%artisan" config:cache
%PHP% "%ROOT%artisan" event:cache

echo.
echo [2/3] Starting queue worker (background)...
start "Aviva Queue Worker" /B %PHP% "%ROOT%artisan" queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=500 --max-time=3600

echo.
echo [3/3] Starting web server on http://localhost:8000
echo       Press Ctrl+C to stop.
echo.
%PHP% "%ROOT%artisan" serve --port=8000
