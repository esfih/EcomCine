@echo off
setlocal

set PORT=%1
if "%PORT%"=="" set PORT=9191

where node >nul 2>nul
if errorlevel 1 (
  echo Node.js was not found on PATH.
  exit /b 1
)

echo Starting standalone server-side converter on http://127.0.0.1:%PORT%
node "%~dp0server.js" %PORT%