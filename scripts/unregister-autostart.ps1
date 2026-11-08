<#
    Undoes register-autostart.ps1: removes the Startup-folder shortcut
    and stops the watchdog loop so the platform stops auto-starting
    and auto-healing. This does NOT stop any services currently
    running (web server, queue worker, etc.) - only the thing that
    was restarting them for you.
#>

$startupDir = [Environment]::GetFolderPath('Startup')
$shortcutPath = Join-Path $startupDir "UniMarket Platform.lnk"

if (Test-Path $shortcutPath) {
    Remove-Item $shortcutPath -Force
    Write-Host "[OK] Startup shortcut removed." -ForegroundColor Green
} else {
    Write-Host "[OK] No Startup shortcut was present." -ForegroundColor Green
}

$watchdogProcesses = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -match 'watchdog-loop\.ps1' }

if ($watchdogProcesses) {
    $watchdogProcesses | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
    Write-Host "[OK] Watchdog loop stopped." -ForegroundColor Green
} else {
    Write-Host "[OK] Watchdog loop was not running." -ForegroundColor Green
}
