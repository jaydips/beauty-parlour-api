<#
.SYNOPSIS
    Builds a deployable zip of beauty-parlour-api for cPanel shared hosting.

.DESCRIPTION
    1. Copies the project into a temporary staging folder, leaving out everything
       that must never reach the server: .env, tests/, build/, vendor/ (rebuilt),
       runtime data under writable/ (cache, logs, sessions, uploads, database files).
    2. Runs `composer install --no-dev --optimize-autoloader` inside the staging
       folder, so the zip carries only production dependencies.
    3. Zips the staging folder into build\release\beauty-parlour-api-<timestamp>.zip.
       The zip contains a single top-level folder "beauty-parlour-api/".

    Requires PHP 8.2+ and Composer 2 on PATH. Run from any directory:
        powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1

.PARAMETER OutDir
    Output directory relative to the project root. Default: build\release
#>
[CmdletBinding()]
param(
    [string] $OutDir = 'build\release'
)

$ErrorActionPreference = 'Stop'

$root    = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stamp   = Get-Date -Format 'yyyyMMdd-HHmmss'
$work    = Join-Path $env:TEMP "bpapi-release-$stamp"
$staging = Join-Path $work 'beauty-parlour-api'

Write-Host "Project root : $root"
Write-Host "Staging      : $staging"
New-Item -ItemType Directory -Force $staging | Out-Null

# What goes to the server (vendor/ is rebuilt below; .env and tests/ are excluded on purpose)
$include = @('app', 'public', 'writable', 'docs', 'deploy', 'whatsapp-bridge', 'composer.json', 'composer.lock', 'env', 'spark', 'preload.php', 'LICENSE', 'README.md')
foreach ($item in $include) {
    $src = Join-Path $root $item
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $staging $item) -Recurse -Force
    }
}

# Keep writable/ structure, .htaccess and index.html only - never ship local data
Get-ChildItem (Join-Path $staging 'writable') -Recurse -File -Force |
    Where-Object { $_.Name -ne 'index.html' -and $_.Name -ne '.htaccess' } |
    Remove-Item -Force

# Belt and braces: no environment file may ever be in the release
Get-ChildItem $staging -Recurse -Force -Filter '.env' | Remove-Item -Force

# WhatsApp bridge: ship the source only - node_modules is installed on the server (npm, one-off cron),
# auth_state/ is the live WhatsApp session, .env.bridge holds the key, logs are runtime data.
$bridge = Join-Path $staging 'whatsapp-bridge'
if (Test-Path $bridge) {
    foreach ($name in @('node_modules', 'auth_state')) {
        $p = Join-Path $bridge $name
        if (Test-Path $p) { Remove-Item -Recurse -Force $p }
    }
    Get-ChildItem $bridge -Force -File | Where-Object { $_.Name -eq '.env.bridge' -or $_.Name -eq '.restart' -or $_.Extension -eq '.log' } | Remove-Item -Force
}

Push-Location $staging
try {
    Write-Host 'Installing production dependencies (composer install --no-dev)...'
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    if ($LASTEXITCODE -ne 0) { throw "composer install failed with exit code $LASTEXITCODE" }
}
finally {
    Pop-Location
}

$outPath = Join-Path $root $OutDir
New-Item -ItemType Directory -Force $outPath | Out-Null
$zip = Join-Path $outPath "beauty-parlour-api-$stamp.zip"

# Zip through PHP so the archive carries Unix permissions (dirs 0755, files 0644).
# Windows tar.exe / Compress-Archive store no modes and cPanel's unzip then
# extracts everything as 0777/0666.
Write-Host 'Writing zip with Unix permissions (deploy/make-zip.php)...'
php (Join-Path $PSScriptRoot 'make-zip.php') $work 'beauty-parlour-api' $zip
if ($LASTEXITCODE -ne 0) { throw "make-zip.php failed with exit code $LASTEXITCODE" }

Remove-Item $work -Recurse -Force

$size = [math]::Round((Get-Item $zip).Length / 1MB, 2)
Write-Host ''
Write-Host "Release zip  : $zip ($size MB)"
Write-Host 'Next steps   : see docs/deployment.md'
