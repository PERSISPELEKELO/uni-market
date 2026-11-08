<#
    Starts every service UniMarket needs for local development, and
    skips anything that's already running instead of starting a
    duplicate. Run it from PowerShell:

        .\scripts\start-platform.ps1

    It starts, each running quietly in the background with its output
    going to its own log file under storage\logs\:
      1. MySQL (via XAMPP)
      2. The web server (php -S, Laravel's own router)   - http://127.0.0.1:8000
      3. The Vite dev server (npm run dev)                - compiles CSS/JS, enables hot reload
      4. The queue worker (php artisan queue:work)        - delivers queued notifications
      5. The scheduler (php artisan schedule:work)        - runs the daily/hourly jobs (student
                                                              verification reminders, the 30-day
                                                              document purge, insights pruning, etc.)
      6. Mailpit (C:\mailpit\mailpit.exe)                  - catches every email the app sends;
                                                              view them at http://127.0.0.1:8026
                                                              (not 8025 - that port is reserved for
                                                              the AI moderation microservice)

    To stop everything: close this question by checking Task Manager
    for php.exe / node.exe processes under this project, or run
        Get-CimInstance Win32_Process -Filter "Name='php.exe' OR Name='node.exe'"
    to find them, then Stop-Process -Id <id> -Force.

    This project's AGENTS.md assumes Laravel Herd serves the site
    automatically. On this machine Herd isn't actually installed -
    MySQL is XAMPP's, and the site is served directly by PHP instead.
    This script reflects what's actually here.

    Why "php -S" instead of "php artisan serve"? artisan serve wraps
    that same underlying command in a process-monitoring layer that
    can hang indefinitely with no error on this machine - this script
    runs the underlying command directly instead, which is equally
    standard and avoids it.
#>

$projectRoot = "C:\Users\PERSIS PELEKEL0\Desktop\uni-market"
$logDir = Join-Path $projectRoot "storage\logs"
Set-Location $projectRoot

function Test-PortOpen([int]$Port) {
    return [bool](Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -eq $Port })
}

function Test-ProcessRunning([string]$Pattern) {
    return [bool](Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -and $_.CommandLine -match $Pattern })
}

function Start-Background([string]$FilePath, [string[]]$ArgumentList, [string]$WorkingDirectory, [string]$LogName) {
    Start-Process -FilePath $FilePath -ArgumentList $ArgumentList -WorkingDirectory $WorkingDirectory -NoNewWindow `
        -RedirectStandardOutput (Join-Path $logDir "$LogName.out.log") `
        -RedirectStandardError (Join-Path $logDir "$LogName.err.log")
}

Write-Host "== UniMarket platform startup ==" -ForegroundColor Cyan

# 0. The public/storage symlink must point to a real Windows path
#    (C:\...). If it was ever recreated from Git Bash (`ln -s`), it
#    points to a POSIX-style path (/c/...) instead - native PHP can't
#    resolve that, and every request that touches the "public" disk
#    (any listing photo, avatar, or verification document) hangs for
#    a full 30 seconds before failing. Fix it once, here, automatically.
$storageLink = Join-Path $projectRoot "public\storage"
$brokenLink = $false
if (Test-Path $storageLink) {
    $item = Get-Item $storageLink -Force
    if ($item.LinkType -ne 'Junction' -or ($item.Target -join '') -notmatch '^[A-Za-z]:\\') {
        $brokenLink = $true
    }
}
if ($brokenLink) {
    Write-Host "[!!] public/storage is a broken (non-Windows) symlink - recreating it..." -ForegroundColor Yellow
    Remove-Item $storageLink -Force
    php artisan storage:link --force | Out-Null
    Write-Host "[OK] public/storage recreated as a proper Windows junction" -ForegroundColor Green
} elseif (-not (Test-Path $storageLink)) {
    Write-Host "[..] Creating public/storage link..." -ForegroundColor Yellow
    php artisan storage:link | Out-Null
}

# 1. MySQL (XAMPP)
if (Test-PortOpen 3306) {
    Write-Host "[OK] MySQL already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting MySQL (XAMPP)..." -ForegroundColor Yellow
    Start-Process -FilePath "C:\xampp\mysql_start.bat" -WindowStyle Minimized
    Start-Sleep -Seconds 5
    if (Test-PortOpen 3306) {
        Write-Host "[OK] MySQL started" -ForegroundColor Green
    } else {
        Write-Host "[!!] MySQL did not start - open the XAMPP Control Panel and start MySQL manually" -ForegroundColor Red
    }
}

# 2. Web server - runs Laravel's own dev-server router script directly
#    with php -S (see the note at the top for why not `php artisan serve`).
#    Uses Windows' short (8.3) path form, with no spaces - passing the
#    real path (it contains a space, "PERSIS PELEKEL0") through
#    Start-Process -ArgumentList corrupts the argument on this machine,
#    however it's quoted; the space-free short path sidesteps that.
if (Test-PortOpen 8000) {
    Write-Host "[OK] Web server already running on http://127.0.0.1:8000" -ForegroundColor Green
} else {
    Write-Host "[..] Starting web server..." -ForegroundColor Yellow
    $fso = New-Object -ComObject Scripting.FileSystemObject
    $shortRoot = $fso.GetFolder($projectRoot).ShortPath
    $routerScript = Join-Path $projectRoot "vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
    $shortRouter = $fso.GetFile($routerScript).ShortPath
    Start-Background "C:\php84\php.exe" @('-S', '127.0.0.1:8000', $shortRouter) "$shortRoot\public" "server"
    Start-Sleep -Seconds 2
}

# 3. Vite dev server - started through cmd.exe because npm itself is a
#    .cmd shim, which Start-Process can't launch directly.
if (Test-PortOpen 5173) {
    Write-Host "[OK] Vite dev server already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting Vite dev server (npm run dev)..." -ForegroundColor Yellow
    Start-Background "$env:WINDIR\System32\cmd.exe" @('/c', 'npm run dev') $projectRoot "vite"
    Start-Sleep -Seconds 2
}

# 4. Queue worker - delivers the notifications that ARE queued
#    (saved-listing price drops, saved-search matches, new listing alerts).
if (Test-ProcessRunning 'queue:work|queue:listen') {
    Write-Host "[OK] Queue worker already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting queue worker..." -ForegroundColor Yellow
    Start-Background "C:\php84\php.exe" @('artisan', 'queue:work', '--tries=1') $projectRoot "queue"
}

# 5. Scheduler - runs every Schedule::command(...) entry in routes/console.php
#    on its own cadence (daily/hourly) for as long as this process stays alive.
if (Test-ProcessRunning 'schedule:work') {
    Write-Host "[OK] Scheduler already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting scheduler..." -ForegroundColor Yellow
    Start-Background "C:\php84\php.exe" @('artisan', 'schedule:work') $projectRoot "schedule"
}

# 6. Mailpit - a local SMTP catch-all so every email the app sends (student
#    verification, password reset, notifications) actually lands somewhere
#    visible, instead of a real-but-unreachable address. Web UI on 8026,
#    not Mailpit's default 8025 - that's reserved for the AI moderation
#    microservice (see AI_MODERATION_URL in .env).
if (Test-PortOpen 1025) {
    Write-Host "[OK] Mailpit already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting Mailpit..." -ForegroundColor Yellow
    Start-Background "C:\mailpit\mailpit.exe" @('--smtp', '127.0.0.1:1025', '--listen', '127.0.0.1:8026') $projectRoot "mailpit"
}

# 7. AI dispute-analysis microservice (Flask, ai-service/app.py) - sentiment
#    + a trained TF-IDF/Logistic Regression classifier for dispute reports.
#    Advisory only: Laravel still works fine if this is down. Takes a
#    couple of minutes to load its model on first start - be patient.
if (Test-PortOpen 8025) {
    Write-Host "[OK] AI dispute-analysis service already running" -ForegroundColor Green
} else {
    Write-Host "[..] Starting AI dispute-analysis service (this can take a couple of minutes to load its model)..." -ForegroundColor Yellow
    $aiServiceDir = Join-Path $projectRoot "ai-service"
    $aiPython = Join-Path $aiServiceDir ".venv\Scripts\python.exe"
    $env:PORT = "8025"
    Start-Background $aiPython @('app.py') $aiServiceDir "ai-service"
}

Write-Host ""
Write-Host "== Done ==" -ForegroundColor Cyan
Write-Host "App:        http://127.0.0.1:8000"
Write-Host "Admin:      http://127.0.0.1:8000/admin"
Write-Host "Mail inbox: http://127.0.0.1:8026"
Write-Host "Logs:       storage\logs\*.out.log / *.err.log - one pair per service"
