@echo off
REM Helper script to start device-monitor.bat in background
REM This script makes it easy to run the monitor in background mode

echo Starting Device Monitor in background...
start /B "" "%~dp0device-monitor.bat" background
echo Device Monitor started in background mode.
echo Check device-monitor.status file to verify it's running.
echo Check device-monitor.log file for monitoring logs.
pause

