@echo off
REM Start WebSocket server and localtunnel
REM Usage: start-all.bat

echo ========================================
echo Starting Radar WebSocket System
echo ========================================
echo.

REM Start WebSocket server in background
echo [1/2] Starting WebSocket server on port 8080...
start "WS Server" php ws-server.php

REM Wait a moment for server to start
timeout /t 2 /nobreak > nul

REM Start localtunnel
echo [2/2] Starting localtunnel...
echo.
echo When tunnel is ready, you'll see a URL like:
echo https://xxxx.loca.lt
echo.
echo Copy that URL and update:
echo   modulos/radares/ws-config.php
echo with:
echo   define('WS_SERVER_HOST', 'xxxx.loca.lt');
echo.
npx localtunnel --port 8080
