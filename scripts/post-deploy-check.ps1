param(
    [string]$WorkspaceRoot = "D:\Projeler\ERT Appointment",
    [string]$WpRoot = "D:\xampp\htdocs\wordpress-plugin",
    [int]$DebugLogTailLines = 300,
    [switch]$RunPhpLint
)

$ErrorActionPreference = 'Stop'

$liteRoot = Join-Path $WorkspaceRoot 'ert-appointment'
$proRoot  = Join-Path $WorkspaceRoot 'ert-appointment-pro'
$debugLog = Join-Path $WpRoot 'wp-content\debug.log'

$results = New-Object System.Collections.Generic.List[pscustomobject]

function Add-Result {
    param(
        [string]$Name,
        [string]$Status,
        [string]$Detail
    )

    $results.Add([pscustomobject]@{
        Check  = $Name
        Status = $Status
        Detail = $Detail
    })
}

function Run-Check {
    param(
        [string]$Name,
        [scriptblock]$Script
    )

    try {
        & $Script
    }
    catch {
        Add-Result -Name $Name -Status 'FAIL' -Detail $_.Exception.Message
    }
}

Run-Check 'Paths exist' {
    $required = @($liteRoot, $proRoot)
    $missing = $required | Where-Object { -not (Test-Path $_) }

    if ($missing.Count -gt 0) {
        Add-Result -Name 'Paths exist' -Status 'FAIL' -Detail ("Missing: " + ($missing -join ', '))
        return
    }

    Add-Result -Name 'Paths exist' -Status 'PASS' -Detail 'Lite and Pro roots found.'
}

Run-Check 'Pro namespace migration' {
    $targets = Get-ChildItem -Path $proRoot -Recurse -File -Include *.php,composer.json

    $legacyWp  = $targets | Select-String -Pattern 'WpAppointmentPro\\' -CaseSensitive
    $legacyErt = $targets | Select-String -Pattern 'ErtAppointmentPro\\' -CaseSensitive
    $targetErt = $targets | Select-String -Pattern 'ERTAppointmentPro\\' -CaseSensitive

    if ($legacyWp.Count -gt 0 -or $legacyErt.Count -gt 0) {
        $detail = "Legacy occurrences found. WpAppointmentPro=$($legacyWp.Count), ErtAppointmentPro=$($legacyErt.Count)"
        Add-Result -Name 'Pro namespace migration' -Status 'FAIL' -Detail $detail
        return
    }

    if ($targetErt.Count -eq 0) {
        Add-Result -Name 'Pro namespace migration' -Status 'FAIL' -Detail 'ERTAppointmentPro not found.'
        return
    }

    Add-Result -Name 'Pro namespace migration' -Status 'PASS' -Detail "ERTAppointmentPro occurrences: $($targetErt.Count)"
}

Run-Check 'REST namespace migration' {
    $targets = Get-ChildItem -Path $proRoot -Recurse -File -Filter *.php
    $legacy  = $targets | Select-String -Pattern 'wpa/v1|wpa-|WPA-'

    if ($legacy.Count -gt 0) {
        Add-Result -Name 'REST namespace migration' -Status 'FAIL' -Detail "Legacy wpa markers found: $($legacy.Count)"
        return
    }

    Add-Result -Name 'REST namespace migration' -Status 'PASS' -Detail 'No legacy wpa markers in Pro PHP files.'
}

Run-Check 'Lite hidden route deprecation fix' {
    $menuFiles = @(
        (Join-Path $liteRoot 'src\\Admin\\AdminMenu.php'),
        (Join-Path $liteRoot 'src\\Provider\\ProviderMenu.php')
    )

    $hits = $menuFiles | Select-String -Pattern 'add_submenu_page\(\s*null|getHiddenParentSlug\(\):\s*\?string' -ErrorAction SilentlyContinue

    if ($hits.Count -gt 0) {
        Add-Result -Name 'Lite hidden route deprecation fix' -Status 'FAIL' -Detail "Deprecated null parent pattern found: $($hits.Count)"
        return
    }

    Add-Result -Name 'Lite hidden route deprecation fix' -Status 'PASS' -Detail 'Null parent_slug pattern not found in Lite menu files.'
}

Run-Check 'Pro autoload smoke-check' {
    $autoload = Join-Path $proRoot 'vendor\autoload.php'
    if (-not (Test-Path $autoload)) {
        Add-Result -Name 'Pro autoload smoke-check' -Status 'WARN' -Detail 'vendor/autoload.php not found; run composer install/dump-autoload first.'
        return
    }

    Push-Location $proRoot
    try {
        if (Get-Command composer -ErrorAction SilentlyContinue) {
            composer dump-autoload --no-interaction | Out-Null
        }
        elseif (Test-Path "$env:APPDATA\\Composer\\latest.phar") {
            php "$env:APPDATA\\Composer\\latest.phar" dump-autoload --no-interaction | Out-Null
        }

        $out = php -r "require 'vendor/autoload.php'; echo (class_exists('ERTAppointmentPro\ProPlugin') ? 'OK_ProPlugin' : 'MISS_ProPlugin'), PHP_EOL; echo (class_exists('ERTAppointmentPro\Payment\PaymentService') ? 'OK_PaymentService' : 'MISS_PaymentService'), PHP_EOL;"
        $text = ($out | Out-String)

        if ($text -match 'OK_ProPlugin' -and $text -match 'OK_PaymentService') {
            Add-Result -Name 'Pro autoload smoke-check' -Status 'PASS' -Detail 'ProPlugin and PaymentService resolved via Composer autoload.'
        }
        else {
            Add-Result -Name 'Pro autoload smoke-check' -Status 'FAIL' -Detail ($text.Trim())
        }
    }
    finally {
        Pop-Location
    }
}

Run-Check 'Debug log deprecated scan' {
    if (-not (Test-Path $debugLog)) {
        Add-Result -Name 'Debug log deprecated scan' -Status 'WARN' -Detail "debug.log not found at $debugLog"
        return
    }

    $tail = Get-Content $debugLog -Tail $DebugLogTailLines
    $patterns = @(
        'strpos\(\): Passing null to parameter #1 \(\$haystack\)',
        'str_replace\(\): Passing null to parameter #3 \(\$subject\)'
    )

    $count = 0
    foreach ($p in $patterns) {
        $count += (@($tail | Select-String -Pattern $p)).Count
    }

    if ($count -gt 0) {
        Add-Result -Name 'Debug log deprecated scan' -Status 'WARN' -Detail "Found $count deprecated entries in last $DebugLogTailLines lines."
        return
    }

    Add-Result -Name 'Debug log deprecated scan' -Status 'PASS' -Detail "No target deprecations in last $DebugLogTailLines lines."
}

if ($RunPhpLint) {
    Run-Check 'Targeted PHP lint' {
        $phpFiles = @(
            (Join-Path $liteRoot 'src\\Admin\\AdminMenu.php'),
            (Join-Path $liteRoot 'src\\Provider\\ProviderMenu.php'),
            (Join-Path $proRoot  'ert-appointment-pro.php'),
            (Join-Path $proRoot  'src\\ProPlugin.php')
        )

        $errors = New-Object System.Collections.Generic.List[string]
        foreach ($f in $phpFiles) {
            if (-not (Test-Path $f)) {
                $errors.Add("Missing file: $f")
                continue
            }
            $out = php -l $f | Out-String
            if ($out -notmatch 'No syntax errors detected') {
                $errors.Add($out.Trim())
            }
        }

        if ($errors.Count -gt 0) {
            Add-Result -Name 'Targeted PHP lint' -Status 'FAIL' -Detail ($errors -join ' | ')
            return
        }

        Add-Result -Name 'Targeted PHP lint' -Status 'PASS' -Detail 'No syntax errors in targeted files.'
    }
}

Write-Host "`n=== ERT Appointment Post-Deploy Check ===" -ForegroundColor Cyan
$results | Format-Table -AutoSize

$fails = @($results | Where-Object { $_.Status -eq 'FAIL' }).Count
$warns = @($results | Where-Object { $_.Status -eq 'WARN' }).Count

if ($fails -gt 0) {
    Write-Host "`nResult: FAIL ($fails fail, $warns warn)" -ForegroundColor Red
    exit 1
}

if ($warns -gt 0) {
    Write-Host "`nResult: PASS WITH WARNINGS ($warns warn)" -ForegroundColor Yellow
    exit 0
}

Write-Host "`nResult: PASS" -ForegroundColor Green
exit 0
