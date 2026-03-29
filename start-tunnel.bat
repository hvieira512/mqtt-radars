@echo off
REM Start localtunnel for WebSocket server
REM Usage: start-tunnel.bat

echo Starting localtunnel on port 8080...
echo.
echo Waiting for tunnel URL...
echo.

npx localtunnel --port 8080
