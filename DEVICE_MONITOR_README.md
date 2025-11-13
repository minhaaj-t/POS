# Device Monitor Batch File

## Overview
The `device-monitor.bat` file is a background monitoring script that checks if the website `https://pos-two-gamma.vercel.app/` is accessible. **This script only works when run in background mode.**

## Features
- ✅ Only runs in background mode (prevents direct execution)
- ✅ Monitors website accessibility every 30 seconds
- ✅ Creates status file (`device-monitor.status`) to indicate running state
- ✅ Logs all activities to `device-monitor.log`
- ✅ Website integration checks if batch file is running

## How to Run

### Method 1: Using the Helper Script (Recommended)
Simply double-click or run:
```batch
start-monitor.bat
```

### Method 2: Using Command Line
```batch
start /B device-monitor.bat background
```

### Method 3: Using Task Scheduler
1. Open Task Scheduler
2. Create a new task
3. Set trigger (e.g., "At startup" or "At logon")
4. Set action to run: `device-monitor.bat background`
5. Configure to run whether user is logged on or not

## Status File
The script creates a `device-monitor.status` file with the following format:
```
RUNNING=RUNNING
TIMESTAMP=MM/DD/YYYY HH:MM:SS
WEBSITE=https://pos-two-gamma.vercel.app/
STATUS=ONLINE or OFFLINE
```

## Website Integration
The website at `https://pos-two-gamma.vercel.app/` automatically checks if the batch file is running:
- If the batch file is **not running**, an error message is displayed
- The "Continue" button is disabled until the batch file is running
- Status is checked every 10 seconds automatically

## API Endpoint
The website provides an API endpoint to check batch file status:
```
GET /api/batch-file-status
```

Response:
```json
{
    "running": true/false,
    "status": "ONLINE" or "OFFLINE",
    "last_update": "MM/DD/YYYY HH:MM:SS",
    "error": "error message if not running"
}
```

## Troubleshooting

### Batch file shows error when run directly
This is expected behavior. The batch file **must** be run in background mode. Use one of the methods above.

### Website shows "Batch file not running" error
1. Check if `device-monitor.status` file exists in the project root
2. Check if the file was updated within the last 2 minutes
3. Verify the batch file is running: Check Task Manager for the process
4. Check `device-monitor.log` for any errors

### Website is not accessible
The batch file will mark the status as OFFLINE if the website cannot be reached. Check:
- Internet connection
- Website availability
- Firewall settings

## Files Created
- `device-monitor.status` - Status file (updated every 30 seconds)
- `device-monitor.log` - Log file with all monitoring activities

## Stopping the Monitor
To stop the monitor:
1. Delete the `device-monitor.status` file, OR
2. End the process in Task Manager

