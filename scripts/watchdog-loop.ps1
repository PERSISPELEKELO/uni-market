<#
    Runs forever in the background: calls start-platform.ps1 every 5
    minutes so every service comes back up automatically, both right
    after you log in and any time one of them quietly dies while
    you're working. start-platform.ps1 is idempotent - each pass only
    starts whatever isn't already running, so this never duplicates
    processes.

    You never run this file directly - register-autostart.ps1 launches
    it hidden, once, from a Startup-folder shortcut, and it keeps
    itself alive from there for as long as you're logged in.
#>

$projectRoot = "C:\Users\PERSIS PELEKEL0\Desktop\uni-market"
$startScript = Join-Path $projectRoot "scripts\start-platform.ps1"
$logFile = Join-Path $projectRoot "storage\logs\watchdog.log"

while ($true) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "[$timestamp] Checking platform services..." | Out-File -FilePath $logFile -Append -Encoding utf8

    try {
        $output = & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $startScript 2>&1 | Out-String
        $output | Out-File -FilePath $logFile -Append -Encoding utf8
    } catch {
        "[$timestamp] start-platform.ps1 failed: $($_.Exception.Message)" | Out-File -FilePath $logFile -Append -Encoding utf8
    }

    Start-Sleep -Seconds 300
}
