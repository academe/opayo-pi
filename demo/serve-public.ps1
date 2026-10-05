# Serve the Opayo Pi demo over a public HTTPS URL via ngrok.
#
#   From the repo root, just run:   .\demo\serve-public.ps1
#   Stop everything with:           Ctrl-C
#
# What it does: starts PHP's built-in web server on a local port, then opens an
# ngrok tunnel from your reserved public domain down to that port. The public
# HTTPS URL is what you open in Safari to test Apple Pay. See demo/README.md.
#
# The only setting is the domain, and it is read from .env (OPAYO_APPLE_PAY_DOMAIN)
# so there is a single source of truth. Nothing to remember or retype.

$ErrorActionPreference = 'Stop'

$Port = 8000
$Root = Split-Path $PSScriptRoot -Parent   # repo root (the folder above demo/)

# --- Read the public domain from .env (OPAYO_APPLE_PAY_DOMAIN) ---------------
$envFile = Join-Path $Root '.env'
if (-not (Test-Path $envFile)) {
    Write-Error "No .env found at $envFile. Copy .env.example to .env first."
    exit 1
}
$match  = Select-String -Path $envFile -Pattern '^\s*OPAYO_APPLE_PAY_DOMAIN\s*=\s*(.+)$' | Select-Object -First 1
$Domain = if ($match) { $match.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'") } else { $null }
if (-not $Domain) {
    Write-Error "OPAYO_APPLE_PAY_DOMAIN is not set in .env. Add your reserved ngrok domain, e.g. foo.ngrok-free.dev"
    exit 1
}

# --- Check the tools are on PATH --------------------------------------------
foreach ($tool in 'php','ngrok') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Write-Error "'$tool' is not on your PATH. (Unix 'which' here is: Get-Command $tool)"
        exit 1
    }
}

Write-Host ""
Write-Host "  PHP server : http://127.0.0.1:$Port   (serving demo/)" -ForegroundColor Cyan
Write-Host "  Public URL : https://$Domain" -ForegroundColor Green
Write-Host "  Open that public URL in your browser. Ctrl-C stops both." -ForegroundColor DarkGray
Write-Host ""

# --- Pick the domain flag this ngrok build understands ----------------------
# Current ngrok uses --url (value includes the scheme); older builds use
# --domain (bare hostname). Detect it so this works across versions.
$help = (& ngrok http --help 2>&1 | Out-String)
if     ($help -match '--url\b')    { $flag = '--url';    $flagValue = "https://$Domain" }
elseif ($help -match '--domain\b') { $flag = '--domain'; $flagValue = $Domain }
else                               { $flag = '--url';    $flagValue = "https://$Domain" }

# --- Start PHP in the background, then run ngrok in the foreground -----------
$php = Start-Process php -ArgumentList '-S',"127.0.0.1:$Port",'-t','demo' -WorkingDirectory $Root -PassThru
try {
    & ngrok http $Port $flag $flagValue
}
finally {
    if ($php -and -not $php.HasExited) {
        Write-Host "`nStopping PHP server..." -ForegroundColor Cyan
        Stop-Process -Id $php.Id -Force -ErrorAction SilentlyContinue
    }
}
