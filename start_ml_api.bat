@echo off
title PESO DSS — ML API Server
color 0A
echo ================================================================
echo   PESO CSJDM DSS — Machine Learning API
echo ================================================================
echo.

:: Check Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python not found. Install Python 3.10+ from https://python.org
    echo.
    pause
    exit /b 1
)

echo [1/3] Python found:
python --version
echo.

:: Move to ml folder
cd /d "%~dp0ml"

echo [2/3] Installing / updating dependencies...
pip install -r requirements.txt -q
if errorlevel 1 (
    echo [WARN] Some packages may not have installed. Continuing anyway...
)
echo      Done.
echo.

echo [3/3] Starting ML API on http://localhost:5000
echo       Keep this window open while using the system.
echo       Press Ctrl+C to stop.
echo.
echo ================================================================
echo.

python app.py

echo.
echo [INFO] ML API stopped.
pause
