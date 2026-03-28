@echo off
title Aviva HealthCare — Task Scheduler
echo Running Laravel scheduler every minute. Press Ctrl+C to stop.
echo.

set PHP=D:\xampp\php\php.exe
set ROOT=%~dp0

:loop
%PHP% "%ROOT%artisan" schedule:run
timeout /t 60 /nobreak > nul
goto loop
