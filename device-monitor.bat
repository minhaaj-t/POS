@echo off
setlocal enabledelayedexpansion

:: Get Device Name
set DEVICE_NAME=%COMPUTERNAME%
if "%DEVICE_NAME%"=="" (
    for /f "tokens=*" %%i in ('hostname') do set DEVICE_NAME=%%i
)

:: Get LAN IPv4 Address
set LAN_IP=
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4"') do (
    set IP=%%a
    set IP=!IP: =!
    
    :: Check if it's a private IP (LAN IP)
    echo !IP! | findstr /r "^10\." >nul
    if !errorlevel! equ 0 (
        set LAN_IP=!IP!
        goto :found_ip
    )
    echo !IP! | findstr /r "^172\.\(1[6-9]\|2[0-9]\|3[0-1]\)\." >nul
    if !errorlevel! equ 0 (
        set LAN_IP=!IP!
        goto :found_ip
    )
    echo !IP! | findstr /r "^192\.168\." >nul
    if !errorlevel! equ 0 (
        set LAN_IP=!IP!
        goto :found_ip
    )
)

:found_ip
if "%LAN_IP%"=="" (
    echo ERROR: Could not detect LAN IPv4 address
    pause
    exit /b 1
)

echo Device Name: %DEVICE_NAME%
echo LAN IPv4: %LAN_IP%
echo.
echo Sending data to https://pos-two-gamma.vercel.app/...
echo.

:: Send data using PowerShell with CSRF token handling
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"$ErrorActionPreference = 'Stop'; ^
try { ^
    # First, get the page to extract CSRF token ^
    Write-Host 'Fetching CSRF token...' -ForegroundColor Yellow; ^
    $pageResponse = Invoke-WebRequest -Uri 'https://pos-two-gamma.vercel.app/' -UseBasicParsing -SessionVariable session; ^
    $csrfToken = ''; ^
    if ($pageResponse.Content -match 'name=\"_token\" value=\"([^\"]+)\"') { ^
        $csrfToken = $matches[1]; ^
        Write-Host 'CSRF token found' -ForegroundColor Green; ^
    } elseif ($pageResponse.Content -match 'csrf-token\" content=\"([^\"]+)\"') { ^
        $csrfToken = $matches[1]; ^
        Write-Host 'CSRF token found (meta tag)' -ForegroundColor Green; ^
    } else { ^
        Write-Host 'Warning: CSRF token not found, attempting without token' -ForegroundColor Yellow; ^
    } ^
    ^
    # Prepare form data ^
    $body = @{ ^
        device_ip = '%LAN_IP%'; ^
        device_name = '%DEVICE_NAME%' ^
    }; ^
    if ($csrfToken) { ^
        $body['_token'] = $csrfToken; ^
    } ^
    ^
    # Send POST request ^
    Write-Host 'Sending device information...' -ForegroundColor Yellow; ^
    $response = Invoke-WebRequest -Uri 'https://pos-two-gamma.vercel.app/stage-1' -Method POST -Body $body -WebSession $session -UseBasicParsing -ErrorAction Stop; ^
    Write-Host 'SUCCESS: Data sent successfully!' -ForegroundColor Green; ^
    Write-Host 'Response Status:' $response.StatusCode; ^
    if ($response.Content -match 'stage-2|success|registered') { ^
        Write-Host 'Device registration appears successful!' -ForegroundColor Green; ^
    } ^
} catch { ^
    Write-Host 'ERROR: Failed to send data' -ForegroundColor Red; ^
    Write-Host 'Error:' $_.Exception.Message; ^
    if ($_.Exception.Response) { ^
        try { ^
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream()); ^
            $responseBody = $reader.ReadToEnd(); ^
            Write-Host 'Response:' $responseBody; ^
        } catch { ^
            Write-Host 'Could not read error response' -ForegroundColor Yellow; ^
        } ^
    } ^
    exit 1; ^
}"

echo.
echo Done!
timeout /t 3 >nul

