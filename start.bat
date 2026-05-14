@echo off
title Aviva HealthCare — Local Server
echo.
echo =========================================
echo   Aviva HealthCare — Starting Services
echo =========================================
echo.

set PHP=D:\xampp\php\php.exe
set ROOT=%~dp0

echo [1/4] Warming caches...
%PHP% "%ROOT%artisan" config:cache
%PHP% "%ROOT%artisan" event:cache

echo.
echo [2/4] Starting queue worker (persistent, auto-restarts)...
start "Aviva Queue Worker" cmd /c "title Aviva Queue Worker & :loop & %PHP% "%ROOT%artisan" queue:work --tries=3 --timeout=300 --sleep=3 & echo Queue worker exited, restarting... & timeout /t 3 /nobreak > nul & goto loop"

echo.
echo [3/4] Starting task scheduler (runs every 60s)...
start "Aviva Scheduler" cmd /c "title Aviva Scheduler & :loop & %PHP% "%ROOT%artisan" schedule:run & timeout /t 60 /nobreak > nul & goto loop"

echo.
echo [4/4] Starting web server on http://localhost:8000
echo       Press Ctrl+C to stop all services.
echo.
%PHP% "%ROOT%artisan" serve --port=8000
