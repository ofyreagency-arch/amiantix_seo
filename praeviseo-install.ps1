[CmdletBinding()]
param(
    [string]$ProjectPath = (Get-Location).Path,
    [string]$ConnectionCode = "",
    [string]$PraeviseoUrl = "https://app.praeviseo.com"
)

$ErrorActionPreference = "Stop"

function Write-Ok([string]$Message) {
    Write-Host "✓ $Message" -ForegroundColor Green
}

function Write-Fail([string]$Message) {
    throw $Message
}

function Require-Command([string]$Command) {
    if (-not (Get-Command $Command -ErrorAction SilentlyContinue)) {
        Write-Fail "$Command non détecté."
    }
}

function Detect-Framework {
    $composerJson = Get-Content -Raw "composer.json"

    if ($composerJson -match '"laravel/framework"') {
        return "laravel"
    }

    if ($composerJson -match '"symfony/framework-bundle"') {
        return "symfony"
    }

    return "unknown"
}

function Read-EnvValue([string]$Key) {
    foreach ($file in @(".env.local", ".env")) {
        if (Test-Path $file) {
            $match = Select-String -Path $file -Pattern "^$Key=" | Select-Object -Last 1
            if ($match) {
                return ($match.Line -replace "^$Key=", "").Trim('"')
            }
        }
    }

    return ""
}

function Write-EnvLocal([string]$Key, [string]$Value) {
    $path = ".env.local"
    if (-not (Test-Path $path)) {
        Set-Content -Path $path -Value ""
    }

    $content = Get-Content -Raw $path
    $line = "$Key=""$Value"""

    if ($content -match "(?m)^$Key=") {
        $updated = [regex]::Replace($content, "(?m)^$Key=.*$", $line)
    } else {
        $updated = ($content.TrimEnd("`r", "`n") + "`r`n" + $line + "`r`n")
    }

    Set-Content -Path $path -Value $updated
}

function Ensure-AppUrl {
    $current = Read-EnvValue "APP_URL"
    if ($current) {
        Write-Ok "APP_URL détecté"
        return
    }

    $url = Read-Host "URL publique du site (ex: https://monsite.com)"
    if (-not $url) {
        Write-Fail "APP_URL requis pour connecter le site."
    }

    Write-EnvLocal "APP_URL" $url
    Write-Ok "APP_URL configuré"
}

function Install-SymfonyBridge {
    & composer config --no-plugins allow-plugins.praeviseo/symfony-bridge true | Out-Null

    & composer show praeviseo/symfony-bridge *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Mise à jour du bridge Symfony…"
        & composer update praeviseo/symfony-bridge
    } else {
        Write-Host "Installation du bridge Symfony…"
        & composer require praeviseo/symfony-bridge
    }

    & composer dump-autoload --no-interaction
}

function Install-LaravelBridge {
    & composer show praeviseo/laravel-bridge *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Mise à jour du bridge Laravel…"
        & composer update praeviseo/laravel-bridge
    } else {
        Write-Host "Installation du bridge Laravel…"
        & composer require praeviseo/laravel-bridge
    }
}

Write-Host "PraeviSEO installer"
Write-Host "Projet cible: $ProjectPath"

if (-not (Test-Path $ProjectPath)) {
    Write-Fail "Chemin projet introuvable."
}

Set-Location $ProjectPath

if (-not (Test-Path "composer.json")) {
    Write-Fail "composer.json introuvable dans $ProjectPath."
}

Require-Command "php"
Write-Ok "PHP détecté"
Require-Command "composer"
Write-Ok "Composer détecté"

$framework = Detect-Framework
switch ($framework) {
    "symfony" { Write-Ok "Symfony détecté" }
    "laravel" { Write-Ok "Laravel détecté" }
    default { Write-Fail "Framework non supporté. Laravel ou Symfony attendu." }
}

if (-not $ConnectionCode) {
    $ConnectionCode = Read-Host "Code de connexion PraeviSEO"
}

if (-not $ConnectionCode) {
    Write-Fail "Code de connexion requis."
}

Ensure-AppUrl

if ($framework -eq "symfony") {
    Install-SymfonyBridge
    Write-Ok "Bridge Symfony installé"
    & php bin/console cache:clear
    & php bin/console praeviseo:connect $ConnectionCode --praeviseo-url=$PraeviseoUrl
    & php bin/console praeviseo:connect --help *> $null
} else {
    Install-LaravelBridge
    Write-Ok "Bridge Laravel installé"
    & php artisan praeviseo:connect $ConnectionCode
    & php artisan praeviseo:connect --help *> $null
}

Write-Ok "Bridge prêt"
Write-Ok "Connexion PraeviSEO active"
Write-Ok "Monitoring actif"
