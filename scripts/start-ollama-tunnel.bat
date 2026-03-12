@echo off
:: ============================================================
:: Aviva HealthCare — Ollama Cloudflare Tunnel Launcher
:: ============================================================
:: Run this script on your Windows PC to expose your local
:: Ollama instance to the internet via a Cloudflare Tunnel.
::
:: Steps:
::   1. Run this script (double-click or from a terminal)
::   2. Wait for a *.trycloudflare.com URL to appear
::   3. Copy the URL and paste it into:
::      Platform Settings → AI Provider → API Base URL
::   4. Leave this window open while using AI features
::
:: Requirements: cloudflared.exe must be in PATH or this folder
:: Download: https://github.com/cloudflare/cloudflared/releases
:: ============================================================

title Aviva HealthCare — Ollama Tunnel

:: Check if cloudflared is available
where cloudflared >nul 2>&1
if errorlevel 1 (
    echo [ERROR] cloudflared.exe not found in PATH.
    echo.
    echo Download it from:
    echo   https://github.com/cloudflare/cloudflared/releases
    echo.
    echo Place cloudflared.exe in this folder or add it to your PATH,
    echo then run this script again.
    echo.
    pause
    exit /b 1
)

:: Check if Ollama is running
curl -s http://localhost:11434/api/version >nul 2>&1
if errorlevel 1 (
    echo [WARNING] Ollama does not appear to be running on port 11434.
    echo Make sure Ollama is started before using AI analysis features.
    echo Continuing anyway...
    echo.
)

echo ============================================================
echo  Aviva HealthCare — Ollama Cloudflare Tunnel
echo ============================================================
echo.
echo Starting tunnel to http://localhost:11434 ...
echo.
echo Look for a line like:
echo   https://xxxx-xxxx.trycloudflare.com
echo.
echo Copy that URL and paste it into Platform Settings.
echo Keep this window open to maintain the tunnel.
echo.
echo Press Ctrl+C to stop.
echo ============================================================
echo.

:LOOP
cloudflared tunnel --url http://localhost:11434 2>&1
echo.
echo [INFO] Tunnel exited. Restarting in 5 seconds...
echo        (Close this window to stop)
timeout /t 5 /nobreak >nul
goto LOOP
