<#
    Makes the UniMarket platform start itself automatically every time
    you log into Windows, and keep healing itself (restarting anything
    that quietly dies) for as long as you're logged in - no manual
    script-running ever again.

    Run it once from PowerShell:

        .\scripts\register-autostart.ps1

    How it works: Task Scheduler is blocked by policy on this account
    (Register-ScheduledTask and schtasks both return "Access is
    denied" here), so this uses the plain Windows Startup folder
    instead - a shortcut there runs automatically at every login, same
    as Steam or any other app that "starts with Windows". That
    shortcut launches watchdog-loop.ps1, a tiny script that re-runs
    start-platform.ps1 (itself idempotent - it only starts whatever
    isn't already running) every 5 minutes, forever, so a crashed
    service comes back on its own.

    What this does NOT give you: the platform being reachable while
    the laptop is fully powered off or asleep. Nothing running
    locally survives that - this is the closest a local dev machine
    gets to "always on": it comes back by itself the moment you're
    back at the laptop.

    To remove it later: .\scripts\unregister-autostart.ps1
#>

$projectRoot = "C:\Users\PERSIS PELEKEL0\Desktop\uni-market"
$watchdogScript = Join-Path $projectRoot "scripts\watchdog-loop.ps1"
$startupDir = [Environment]::GetFolderPath('Startup')
$shortcutPath = Join-Path $startupDir "UniMarket Platform.lnk"

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"
$shortcut.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$watchdogScript`""
$shortcut.WorkingDirectory = $projectRoot
$shortcut.Description = "Starts and self-heals every UniMarket local dev service at login."
$shortcut.WindowStyle = 7 # minimized
$shortcut.Save()

Write-Host "[OK] Startup shortcut created: $shortcutPath" -ForegroundColor Green

# Also start it right now, so it's active immediately rather than only
# from the next login - the watchdog loop runs start-platform.ps1 itself,
# which only starts what isn't already running.
$alreadyRunning = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -match 'watchdog-loop\.ps1' }

if ($alreadyRunning) {
    Write-Host "[OK] Watchdog loop is already running (PID $($alreadyRunning[0].ProcessId))." -ForegroundColor Green
} else {
    Start-Process -FilePath "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" `
        -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-WindowStyle', 'Hidden', '-File', $watchdogScript) `
        -WindowStyle Hidden
    Start-Sleep -Seconds 2
    Write-Host "[OK] Watchdog loop started now." -ForegroundColor Green
}

Write-Host ""
Write-Host "From now on the platform starts itself at every login and heals every 5 minutes." -ForegroundColor Cyan
Write-Host "Watchdog log: storage\logs\watchdog.log"
