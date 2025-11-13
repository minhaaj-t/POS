@echo off
REM Device Monitor Batch File
REM This script only works when run in background
REM It monitors the website https://pos-two-gamma.vercel.app/

REM Check if running in background mode
if "%1"=="background" goto :background_mode

REM If run directly (not in background), show error and exit
echo.
echo ========================================
echo ERROR: Batch file must run in background
echo ========================================
echo.
echo To run in background, use one of these methods:
echo   1. start /B device-monitor.bat background
echo   2. wscript invis.vbs device-monitor.bat background
echo   3. Use Task Scheduler to run it in background
echo.
echo The batch file will only work when run in background mode.
echo.
pause
exit /b 1

:background_mode
REM Set working directory to script location
cd /d "%~dp0"

REM Create status file to indicate running
set STATUS_FILE=%~dp0device-monitor.status
set LOG_FILE=%~dp0device-monitor.log
set WEBSITE_URL=https://pos-two-gamma.vercel.app/

REM Initialize status file
echo RUNNING=RUNNING > "%STATUS_FILE%"
echo TIMESTAMP=%date% %time% >> "%STATUS_FILE%"
echo WEBSITE=%WEBSITE_URL% >> "%STATUS_FILE%"

REM Log start
echo [%date% %time%] Device Monitor started in background mode >> "%LOG_FILE%"
echo [%date% %time%] Monitoring website: %WEBSITE_URL% >> "%LOG_FILE%"

:monitor_loop
REM Update timestamp in status file
echo RUNNING=RUNNING > "%STATUS_FILE%"
echo TIMESTAMP=%date% %time% >> "%STATUS_FILE%"
echo WEBSITE=%WEBSITE_URL% >> "%STATUS_FILE%"

REM Check if website is accessible using PowerShell (more reliable than curl)
powershell -Command "try { $response = Invoke-WebRequest -Uri '%WEBSITE_URL%' -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop; if ($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" > nul 2>&1
if %errorlevel% equ 0 (
    echo STATUS=ONLINE >> "%STATUS_FILE%"
    echo [%date% %time%] Website is accessible >> "%LOG_FILE%"
) else (
    echo STATUS=OFFLINE >> "%STATUS_FILE%"
    echo [%date% %time%] Website is not accessible >> "%LOG_FILE%"
)

REM Wait 30 seconds before next check
timeout /t 30 /nobreak > nul

REM Check if status file still exists (if deleted, exit)
if not exist "%STATUS_FILE%" (
    echo [%date% %time%] Status file removed, exiting >> "%LOG_FILE%"
    exit /b
)

goto :monitor_loop

