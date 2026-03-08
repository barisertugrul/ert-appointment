param(
    [string]$OutputDir = ".\test-results",
    [string]$Tester = "",
    [string]$Environment = "",
    [string]$TemplateFile = ".\docs\live-booking-mode-test-report.md"
)

$ErrorActionPreference = 'Stop'

$manualScript = ".\scripts\scope-override-manual-test.ps1"
$fillScript = ".\scripts\fill-live-test-report.ps1"

if (-not (Test-Path -LiteralPath $manualScript)) {
    throw "Manual test script not found: $manualScript"
}

if (-not (Test-Path -LiteralPath $fillScript)) {
    throw "Fill report script not found: $fillScript"
}

if (-not (Test-Path -LiteralPath $TemplateFile)) {
    throw "Template file not found: $TemplateFile"
}

if (-not (Test-Path -LiteralPath $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$jsonOutput = Join-Path $OutputDir "scope-override-$timestamp.json"
$mdOutput = Join-Path $OutputDir "live-report-$timestamp.md"

Write-Host "[1/2] Manual test başlatılıyor..." -ForegroundColor Cyan
& powershell -ExecutionPolicy Bypass -File $manualScript -OutputFile $jsonOutput -Tester $Tester -Environment $Environment

if (-not (Test-Path -LiteralPath $jsonOutput)) {
    throw "JSON report was not generated: $jsonOutput"
}

Write-Host "[2/2] Markdown rapor üretiliyor..." -ForegroundColor Cyan
& powershell -ExecutionPolicy Bypass -File $fillScript -JsonReport $jsonOutput -TemplateFile $TemplateFile -OutputFile $mdOutput

if (-not (Test-Path -LiteralPath $mdOutput)) {
    throw "Markdown report was not generated: $mdOutput"
}

Write-Host "`nTamamlandı." -ForegroundColor Green
Write-Host "JSON: $jsonOutput"
Write-Host "MD  : $mdOutput"
